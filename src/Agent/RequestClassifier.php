<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use Closure;
use Exception;
use HelgeSverre\Swarm\Enums\Agent\RequestType;
use HelgeSverre\Swarm\Prompts\PromptTemplates;
use Psr\Log\LoggerInterface;

final class RequestClassifier
{
    public function __construct(
        private readonly ConversationBuffer $conversationBuffer,
        private readonly Closure $llmCallback,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function analyze(string $userInput): array
    {
        $context = $this->conversationBuffer->getOptimalContext($userInput);

        $analysisPrompt = "PRIVATE ANALYSIS - Internal reasoning only, not shown to user.
        
        Analyze this request deeply:
        REQUEST: {$userInput}
        
        CONTEXT ANALYSIS:
        - What is the user's actual intent beyond the literal words?
        - What context from conversation history is most relevant?
        - What potential ambiguities or edge cases exist?
        - What domain knowledge or expertise is required?
        - What are the likely success/failure scenarios?
        
        STRATEGIC ASSESSMENT:
        - Complexity level (simple/moderate/complex)
        - Required capabilities and tools
        - Potential risks or safety concerns
        - Success probability and confidence level
        
        Provide structured analysis for internal decision-making in JSON format.";

        $messages = array_merge($context, [
            ['role' => 'user', 'content' => $analysisPrompt],
        ]);

        try {
            $response = $this->callLlm($messages, [
                'response_format' => ['type' => 'json_object'],
            ]);

            $decoded = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger?->warning('JSON decode error in request analysis', [
                    'error' => json_last_error_msg(),
                    'response' => mb_substr($response, 0, 500),
                ]);

                return [];
            }

            return is_array($decoded) ? $decoded : [];
        } catch (Exception $e) {
            $this->logger?->error('Request analysis failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function classify(string $userInput, array $analysis): array
    {
        $approaches = [
            'literal' => "Analyze literally: What words and phrases directly indicate the user's intent?",
            'contextual' => 'Analyze contextually: How does this request fit within our conversation history?',
            'pragmatic' => 'Analyze pragmatically: What outcome does the user actually want to achieve?',
        ];

        $results = [];
        foreach ($approaches as $name => $approach) {
            $results[$name] = $this->singleReasoningPath($userInput, $approach, $analysis);
        }

        return $this->selectMostConsistentClassification($results, $userInput);
    }

    public function quickClassify(string $input): array
    {
        $input = trim(mb_strtolower($input));

        if (preg_match('/^(hi|hello|hey|greetings?|good (morning|afternoon|evening))!?$/i', $input)) {
            return [
                'request_type' => RequestType::Conversation->value,
                'requires_tools' => false,
                'confidence' => 0.95,
                'complexity' => 'simple',
                'reasoning' => 'Standard greeting pattern detected',
            ];
        }

        if (str_starts_with($input, 'what') || str_starts_with($input, 'how') || str_ends_with($input, '?')) {
            if (str_contains($input, 'code') || str_contains($input, 'implement') || str_contains($input, 'build')) {
                return [
                    'request_type' => RequestType::Implementation->value,
                    'requires_tools' => true,
                    'confidence' => 0.75,
                    'complexity' => 'moderate',
                    'reasoning' => 'Question about coding/implementation',
                ];
            }

            return [
                'request_type' => RequestType::Query->value,
                'requires_tools' => false,
                'confidence' => 0.8,
                'complexity' => 'simple',
                'reasoning' => 'General question pattern',
            ];
        }

        if (preg_match('/^(create|make|build|generate|write)\s+/i', $input)) {
            return [
                'request_type' => RequestType::Implementation->value,
                'requires_tools' => true,
                'confidence' => 0.85,
                'complexity' => 'moderate',
                'reasoning' => 'Imperative command for creation/implementation',
            ];
        }

        if (preg_match('/^(explain|describe|tell me about)\s+/i', $input)) {
            return [
                'request_type' => RequestType::Explanation->value,
                'requires_tools' => false,
                'confidence' => 0.8,
                'complexity' => 'simple',
                'reasoning' => 'Request for explanation or description',
            ];
        }

        if (mb_strlen($input) < 100) {
            $llmResult = $this->llmQuickClassification($input);
            if ($llmResult !== null) {
                return $llmResult;
            }
        }

        return [
            'request_type' => RequestType::Conversation->value,
            'requires_tools' => false,
            'confidence' => 0.5,
            'complexity' => mb_strlen($input) >= 100 ? 'complex' : 'moderate',
            'reasoning' => 'Default classification - requires deeper analysis',
        ];
    }

    private function singleReasoningPath(string $userInput, string $approach, array $analysis): array
    {
        $context = $this->conversationBuffer->getOptimalContext($userInput, 2000);

        $prompt = PromptTemplates::classificationSystemWithAnalysis($approach, $analysis) . "

        User request: {$userInput}
        
        Using the {$approach} approach, classify this request and explain your reasoning. Return your classification in JSON format.";

        $messages = array_merge($context, [
            ['role' => 'user', 'content' => $prompt],
        ]);

        try {
            $response = $this->callLlm($messages, [
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $this->getClassificationSchema(),
                ],
            ]);

            $decoded = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger?->warning('JSON decode error in classification', [
                    'error' => json_last_error_msg(),
                    'response' => mb_substr($response, 0, 500),
                    'approach' => $approach,
                ]);

                return [];
            }

            if (! is_array($decoded)) {
                $this->logger?->warning('Invalid classification response structure', [
                    'response' => $decoded,
                    'approach' => $approach,
                ]);

                return [];
            }

            $type = RequestType::fromString((string) ($decoded['request_type'] ?? ''));

            if ($type === null || ! isset($decoded['confidence'], $decoded['reasoning'])) {
                $this->logger?->warning('Invalid classification response structure', [
                    'response' => $decoded,
                    'approach' => $approach,
                ]);

                return [];
            }

            $decoded['request_type'] = $type->value;

            return $decoded;
        } catch (Exception $e) {
            $this->logger?->error('Classification reasoning path failed', [
                'approach' => $approach,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function selectMostConsistentClassification(array $results, string $userInput): array
    {
        $votes = [];

        foreach ($results as $result) {
            if (isset($result['request_type'])) {
                $type = $result['request_type'];
                $votes[$type] = ($votes[$type] ?? 0) + 1;
            }
        }

        if ($votes === []) {
            $this->logger?->warning('Using fallback classification due to consistency failure');

            return $this->quickClassify($userInput);
        }

        $winner = array_keys($votes, max($votes), true)[0];
        $bestResult = null;
        $bestConfidence = 0.0;

        foreach ($results as $result) {
            if (($result['request_type'] ?? '') !== $winner) {
                continue;
            }

            $confidence = (float) ($result['confidence'] ?? 0);
            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestResult = $result;
            }
        }

        if (! is_array($bestResult)) {
            return $this->quickClassify($userInput);
        }

        $bestResult['consistency_score'] = $votes[$winner] / count($results);
        $bestResult['reasoning_approaches'] = array_keys($results);

        return $bestResult;
    }

    private function llmQuickClassification(string $input): ?array
    {
        try {
            $prompt = "Classify this request quickly (one word): '{$input}'
Response format: conversation|implementation|explanation|demonstration|query
Also rate: simple|moderate|complex
Confidence: 0.0-1.0";

            $messages = [
                ['role' => 'system', 'content' => 'You are a quick request classifier. Respond with JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ];

            $response = $this->callLlm($messages, [
                'max_completion_tokens' => 100,
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = json_decode($response, true);

            if (! is_array($result)) {
                return null;
            }

            $type = RequestType::fromString((string) ($result['type'] ?? ''));

            if ($type !== null) {
                return [
                    'request_type' => $type->value,
                    'requires_tools' => $type->requiresTools(),
                    'confidence' => (float) ($result['confidence'] ?? 0.7),
                    'complexity' => $result['complexity'] ?? 'simple',
                    'reasoning' => 'Quick LLM classification',
                ];
            }
        } catch (Exception $e) {
            $this->logger?->debug('Quick LLM classification failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function getClassificationSchema(): array
    {
        return [
            'name' => 'request_classification',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'request_type' => [
                        'type' => 'string',
                        'enum' => RequestType::values(),
                    ],
                    'requires_tools' => ['type' => 'boolean'],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    'reasoning' => ['type' => 'string'],
                    'complexity' => ['type' => 'string', 'enum' => ['simple', 'moderate', 'complex']],
                    'estimated_effort' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                ],
                'required' => ['request_type', 'requires_tools', 'confidence', 'reasoning'],
            ],
        ];
    }

    private function callLlm(array $messages, array $options = []): string
    {
        return ($this->llmCallback)($messages, $options);
    }
}
