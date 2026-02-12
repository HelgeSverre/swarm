<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use Exception;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Enums\Agent\RequestType;
use HelgeSverre\Swarm\Events\ProcessingEvent;
use HelgeSverre\Swarm\Prompts\PromptTemplates;
use HelgeSverre\Swarm\Task\Task;
use HelgeSverre\Swarm\Task\TaskManager;
use HelgeSverre\Swarm\Traits\EventAware;
use HelgeSverre\Swarm\Traits\Loggable;
use OpenAI;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Enhanced Coding Agent with OpenAI GPT models and intelligent context management
 *
 * Key improvements:
 * - Intelligent conversation buffer with relevance-based context selection
 * - Self-consistent reasoning for better classification accuracy
 * - Multi-channel processing (analysis, planning, execution, reflection)
 * - Reflexive error recovery with pattern learning
 * - Clean architecture without massive processRequest() method
 */
class CodingAgent
{
    use EventAware, Loggable;

    protected ConversationBuffer $conversationBuffer;

    protected bool $useCustomTools = true;

    // Progress callback for real-time feedback
    protected $progressCallback = null;

    public function __construct(
        protected readonly ToolExecutor $toolExecutor,
        protected readonly TaskManager $taskManager,
        protected readonly OpenAI\Contracts\ClientContract $llmClient,
        protected readonly ?LoggerInterface $logger = null,
        protected readonly string $model = 'gpt-4o-mini',
        protected readonly string $reasoningEffort = 'medium',
        protected readonly string $verbosity = 'medium',
    ) {
        $this->conversationBuffer = new ConversationBuffer;
    }

    /**
     * Main request processing with clean architecture
     */
    public function processRequest(string $userInput): AgentResponse
    {
        // Input validation
        if (empty(trim($userInput))) {
            return AgentResponse::error(
                error: 'Empty input provided',
                partialContent: 'Please provide a valid request.'
            );
        }

        if (mb_strlen($userInput) > 10000) {
            return AgentResponse::error(
                error: 'Input too long',
                partialContent: 'Please shorten your request to under 10,000 characters.'
            );
        }

        $this->logger?->info('Processing request', [
            'input_length' => mb_strlen($userInput),
            'model' => $this->model,
        ]);

        // Add to intelligent conversation buffer
        $this->conversationBuffer->addMessage('user', $userInput);

        $startTime = microtime(true);

        try {
            // Multi-channel processing: analyze, classify, route, execute
            $response = $this->processWithChannelsAndRecovery($userInput);

            $processingTime = microtime(true) - $startTime;
            // Create new response with processing time since properties are readonly
            $response = new AgentResponse(
                content: $response->content,
                success: $response->success,
                metadata: array_merge($response->metadata, ['processing_time' => $processingTime]),
                error: $response->error,
                processingTime: $processingTime,
                toolCalls: $response->toolCalls,
                classificationData: $response->classificationData
            );

            return $response;
        } catch (Exception $e) {
            $processingTime = microtime(true) - $startTime;

            $this->logger?->error('Request processing failed', [
                'error' => $e->getMessage(),
                'input' => mb_substr($userInput, 0, 200) . '...',
                'processing_time' => $processingTime,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->handleErrorWithRecovery($e, $userInput, $processingTime);
        }
    }

    /**
     * Enhanced LLM call with retry logic
     */
    public function callLLMWithEnhancements(array $messages, array $options = []): string
    {
        // Strip tools from options if custom tools are disabled
        if (! $this->useCustomTools) {
            unset($options['tools']);
        }

        $requestOptions = ModelCapabilities::buildRequestOptions(
            model: $options['model'] ?? $this->model,
            messages: $messages,
            options: $options,
            reasoningEffort: $this->reasoningEffort,
            verbosity: $this->verbosity,
        );

        $this->logger?->debug('LLM call', [
            'model' => $requestOptions['model'],
            'message_count' => count($messages),
            'use_custom_tools' => $this->useCustomTools,
        ]);

        $retryCount = 0;
        $maxRetries = 3;
        $backoffDelay = 1; // Start with 1 second

        while ($retryCount <= $maxRetries) {
            try {
                $this->reportProgress('calling_openai', [
                    'model' => $requestOptions['model'],
                    'attempt' => $retryCount + 1,
                    'max_retries' => $maxRetries + 1,
                ]);

                $response = $this->llmClient->chat()->create($requestOptions);

                $choice = $response->choices[0] ?? null;
                if (! $choice) {
                    throw new RuntimeException('No choices in LLM response');
                }

                $message = $choice->message ?? null;
                if (! $message) {
                    throw new RuntimeException('No message in LLM response choice');
                }

                $content = $message->content ?? '';

                if (empty($content)) {
                    throw new RuntimeException('Empty content from LLM response');
                }

                // Add to conversation buffer
                $this->conversationBuffer->addMessage('assistant', $content);

                // Log successful response
                $this->logger?->debug('LLM response received', [
                    'content_length' => mb_strlen($content),
                ]);

                return $content;
            } catch (Exception $e) {
                $retryCount++;
                $isLastAttempt = $retryCount > $maxRetries;

                $this->logger?->warning('LLM call failed', [
                    'error' => $e->getMessage(),
                    'model' => $requestOptions['model'],
                    'attempt' => $retryCount,
                    'max_retries' => $maxRetries + 1,
                    'will_retry' => ! $isLastAttempt,
                ]);

                if ($isLastAttempt) {
                    $this->logger?->error('LLM call failed after all retries', [
                        'error' => $e->getMessage(),
                        'model' => $requestOptions['model'],
                        'total_attempts' => $retryCount,
                    ]);

                    // Return a graceful fallback response
                    return $this->generateFallbackResponse($messages, $e);
                }

                // Exponential backoff with jitter
                $delay = $backoffDelay + random_int(0, 1000000) / 1000000; // Add up to 1s jitter
                $this->logger?->info("Retrying LLM call after {$delay}s delay");
                sleep((int) $delay);
                $backoffDelay *= 2; // Double the delay for next retry
            }
        }

        // This should never be reached due to the loop logic, but added for static analysis
        return $this->generateFallbackResponse($messages, new RuntimeException('Max retries exceeded'));
    }

    /**
     * Set progress callback for real-time feedback
     */
    public function setProgressCallback(?callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * Get conversation buffer statistics for monitoring
     */
    public function getConversationStats(): array
    {
        return $this->conversationBuffer->getStats();
    }

    /**
     * Enable/disable custom tools usage
     */
    public function setUseCustomTools(bool $use): void
    {
        $this->useCustomTools = $use;
    }

    /**
     * Set conversation history (for backward compatibility with state restoration)
     */
    public function setConversationHistory(array $history): void
    {
        // Convert old history format to new ConversationBuffer
        foreach ($history as $entry) {
            if (isset($entry['role'], $entry['content'])) {
                $this->conversationBuffer->addMessage($entry['role'], $entry['content']);
            }
        }
    }

    /**
     * Get conversation history (for backward compatibility with state saving)
     */
    public function getConversationHistory(): array
    {
        // Return recent context for state saving
        return $this->conversationBuffer->getRecentContext(50);
    }

    /**
     * Get task manager (for backward compatibility)
     */
    public function getTaskManager(): TaskManager
    {
        return $this->taskManager;
    }

    /**
     * Get agent status (for backward compatibility with WorkerProcess)
     */
    public function getStatus(): array
    {
        $tasks = $this->taskManager->getTasks();
        $currentTask = $this->taskManager->currentTask;  // Use property directly

        return [
            'tasks' => array_map(fn (Task $task) => [
                'id' => $task->id,
                'description' => $task->description,
                'status' => $task->status->value,
            ], $tasks),
            'current_task' => $currentTask ? [
                'id' => $currentTask->id,
                'description' => $currentTask->description,
                'status' => $currentTask->status->value,
            ] : null,
            'conversation_stats' => $this->conversationBuffer->getStats(),
        ];
    }

    /**
     * Multi-channel processing: separate analysis, planning, execution, reflection
     */
    protected function processWithChannels(string $userInput): AgentResponse
    {
        // Quick Initial Assessment - Low effort for immediate feedback
        $this->reportProgress('quick_assessment', ['message' => 'Quick initial assessment...']);
        $quickClassification = $this->quickClassifyRequest($userInput);

        // If we have high confidence and it's simple, provide immediate response
        if ($quickClassification['confidence'] > 0.85 && $quickClassification['complexity'] === 'simple') {
            $this->reportProgress('quick_response', [
                'message' => 'Providing quick response...',
                'type' => $quickClassification['request_type'],
                'confidence' => $quickClassification['confidence'],
            ]);

            $handler = $this->getRequestHandler($quickClassification);
            $response = $handler->handle($userInput, $quickClassification, []);

            // Log quick response
            $this->logger?->info('Quick response provided', [
                'type' => $quickClassification['request_type'],
                'confidence' => $quickClassification['confidence'],
            ]);

            return $response;
        }

        // For complex requests, use deep processing channels
        $this->reportProgress('deep_processing', ['message' => 'Processing complex request with multi-channel analysis...']);

        // Channel 1: Private analysis (enhanced understanding)
        $this->reportProgress('analyzing', ['message' => 'Analyzing request with enhanced intelligence...']);
        $analysis = $this->privateAnalysisChannel($userInput);

        // Channel 2: Public classification and routing (transparent to user)
        $this->reportProgress('classifying', ['message' => 'Classifying request type with self-consistent reasoning...']);
        $classification = $this->classifyRequestWithConsistency($userInput, $analysis);

        // Channel 3: Route to appropriate handler
        $handler = $this->getRequestHandler($classification);

        // Channel 4: Execute with monitoring and reflection
        $response = $handler->handle($userInput, $classification, $analysis);

        // Channel 5: Post-execution reflection and learning
        $this->reflectOnExecution($userInput, $response, $classification);

        return $response;
    }

    /**
     * Private analysis channel - enhanced understanding not shown to user
     */
    protected function privateAnalysisChannel(string $userInput): array
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
            $response = $this->callLLMWithEnhancements($messages, [
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

    /**
     * Self-consistent classification with multiple reasoning paths
     */
    protected function classifyRequestWithConsistency(string $userInput, array $analysis): array
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

        // Select most consistent result
        return $this->selectMostConsistentClassification($results, $userInput);
    }

    /**
     * Single reasoning path for classification
     */
    protected function singleReasoningPath(string $userInput, string $approach, array $analysis): array
    {
        $context = $this->conversationBuffer->getOptimalContext($userInput, 2000);

        $prompt = PromptTemplates::classificationSystemWithAnalysis($approach, $analysis) . "

        User request: {$userInput}
        
        Using the {$approach} approach, classify this request and explain your reasoning. Return your classification in JSON format.";

        $messages = array_merge($context, [
            ['role' => 'user', 'content' => $prompt],
        ]);

        try {
            $response = $this->callLLMWithEnhancements($messages, [
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

            // Validate required fields
            if (! isset($decoded['request_type'], $decoded['confidence'], $decoded['reasoning'])) {
                $this->logger?->warning('Invalid classification response structure', [
                    'response' => $decoded,
                    'approach' => $approach,
                ]);

                return [];
            }

            return $decoded;
        } catch (Exception $e) {
            $this->logger?->error('Classification reasoning path failed', [
                'approach' => $approach,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Select most consistent classification from multiple paths
     */
    protected function selectMostConsistentClassification(array $results, string $userInput): array
    {
        // Count votes for each classification
        $votes = [];
        $confidenceSum = [];

        foreach ($results as $approach => $result) {
            if (isset($result['request_type'])) {
                $type = $result['request_type'];
                $votes[$type] = ($votes[$type] ?? 0) + 1;
                $confidenceSum[$type] = ($confidenceSum[$type] ?? 0) + ($result['confidence'] ?? 0);
            }
        }

        if (empty($votes)) {
            // Fallback to simple classification if consistency check fails
            return $this->fallbackClassification($userInput);
        }

        // Select type with most votes, tie-breaking by confidence
        $winner = array_keys($votes, max($votes))[0];

        // Find the result with highest confidence for this type
        $bestResult = null;
        $bestConfidence = 0;

        foreach ($results as $result) {
            if (($result['request_type'] ?? '') === $winner) {
                $confidence = $result['confidence'] ?? 0;
                if ($confidence > $bestConfidence) {
                    $bestConfidence = $confidence;
                    $bestResult = $result;
                }
            }
        }

        // Enhance with consistency score
        $bestResult['consistency_score'] = $votes[$winner] / count($results);
        $bestResult['reasoning_approaches'] = array_keys($results);

        return $bestResult;
    }

    /**
     * Quick classification with low reasoning effort for immediate response
     */
    protected function quickClassifyRequest(string $input): array
    {
        // Simple pattern matching for common cases
        $input = trim(mb_strtolower($input));

        // Greeting patterns - high confidence, simple
        if (preg_match('/^(hi|hello|hey|greetings?|good (morning|afternoon|evening))!?$/i', $input)) {
            return [
                'request_type' => 'conversation',
                'requires_tools' => false,
                'confidence' => 0.95,
                'complexity' => 'simple',
                'reasoning' => 'Standard greeting pattern detected',
            ];
        }

        // Question patterns - medium confidence
        if (str_starts_with($input, 'what') || str_starts_with($input, 'how') || str_ends_with($input, '?')) {
            if (str_contains($input, 'code') || str_contains($input, 'implement') || str_contains($input, 'build')) {
                return [
                    'request_type' => 'implementation',
                    'requires_tools' => true,
                    'confidence' => 0.75,
                    'complexity' => 'moderate',
                    'reasoning' => 'Question about coding/implementation',
                ];
            }

            return [
                'request_type' => 'query',
                'requires_tools' => false,
                'confidence' => 0.8,
                'complexity' => 'simple',
                'reasoning' => 'General question pattern',
            ];
        }

        // Command patterns - high confidence for simple commands
        if (preg_match('/^(create|make|build|generate|write)\s+/i', $input)) {
            return [
                'request_type' => 'implementation',
                'requires_tools' => true,
                'confidence' => 0.85,
                'complexity' => 'moderate',
                'reasoning' => 'Imperative command for creation/implementation',
            ];
        }

        // Explanation requests
        if (preg_match('/^(explain|describe|tell me about)\s+/i', $input)) {
            return [
                'request_type' => 'explanation',
                'requires_tools' => false,
                'confidence' => 0.8,
                'complexity' => 'simple',
                'reasoning' => 'Request for explanation or description',
            ];
        }

        // For uncertain cases, use low-effort LLM classification
        if (mb_strlen($input) < 100) {
            return $this->llmQuickClassification($input);
        }

        // Default for complex cases - requires deeper analysis
        return [
            'request_type' => 'conversation',
            'requires_tools' => false,
            'confidence' => 0.6,
            'complexity' => 'complex',
            'reasoning' => 'Complex request requiring detailed analysis',
        ];
    }

    /**
     * Use LLM for quick classification with minimal reasoning effort
     */
    protected function llmQuickClassification(string $input): array
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

            $response = $this->callLLMWithEnhancements($messages, [
                'max_completion_tokens' => 100,
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = json_decode($response, true);

            if ($result && isset($result['type'])) {
                return [
                    'request_type' => $result['type'],
                    'requires_tools' => in_array($result['type'], ['implementation', 'demonstration']),
                    'confidence' => (float) ($result['confidence'] ?? 0.7),
                    'complexity' => $result['complexity'] ?? 'simple',
                    'reasoning' => 'Quick LLM classification',
                ];
            }
        } catch (Exception $e) {
            $this->logger?->debug('Quick LLM classification failed', ['error' => $e->getMessage()]);
        }

        // Fallback
        return [
            'request_type' => 'conversation',
            'requires_tools' => false,
            'confidence' => 0.6,
            'complexity' => 'moderate',
            'reasoning' => 'Fallback classification',
        ];
    }

    /**
     * Get appropriate request handler based on classification
     */
    protected function getRequestHandler(array $classification): RequestHandler
    {
        $type = RequestType::tryFrom($classification['request_type'] ?? 'conversation')
            ?? RequestType::Conversation;

        return match ($type) {
            RequestType::Implementation => new ImplementationHandler(
                toolExecutor: $this->toolExecutor,
                taskManager: $this->taskManager,
                conversationBuffer: $this->conversationBuffer,
                logger: $this->logger,
                progressCallback: fn (string $operation, array $details) => $this->reportProgress($operation, $details),
                llmCallback: fn (array $messages, array $options = []) => $this->callLLMWithEnhancements($messages, $options)
            ),
            RequestType::Demonstration => new DemonstrationHandler(
                conversationBuffer: $this->conversationBuffer,
                llmCallback: fn (array $messages, array $options = []) => $this->callLLMWithEnhancements($messages, $options)
            ),
            RequestType::Explanation => new ExplanationHandler(
                conversationBuffer: $this->conversationBuffer,
                llmCallback: fn (array $messages, array $options = []) => $this->callLLMWithEnhancements($messages, $options)
            ),
            RequestType::Query => new QueryHandler(
                conversationBuffer: $this->conversationBuffer,
                llmCallback: fn (array $messages, array $options = []) => $this->callLLMWithEnhancements($messages, $options)
            ),
            default => new ConversationHandler(
                conversationBuffer: $this->conversationBuffer,
                llmCallback: fn (array $messages, array $options = []) => $this->callLLMWithEnhancements($messages, $options)
            )
        };
    }

    /**
     * Generate a fallback response when LLM calls fail
     */
    protected function generateFallbackResponse(array $messages, Exception $originalError): string
    {
        // Try to extract the last user message for context
        $lastUserMessage = null;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i]['role'] === 'user') {
                $lastUserMessage = $messages[$i]['content'];
                break;
            }
        }

        $fallbackResponse = "I apologize, but I'm currently experiencing technical difficulties connecting to the AI service. ";

        if ($lastUserMessage) {
            $fallbackResponse .= "I understand you're asking about: " . mb_substr($lastUserMessage, 0, 100);
            if (mb_strlen($lastUserMessage) > 100) {
                $fallbackResponse .= '...';
            }
            $fallbackResponse .= "\n\n";
        }

        $fallbackResponse .= 'Please try your request again in a few moments. If the issue persists, ';
        $fallbackResponse .= "you may want to check your internet connection or contact support.\n\n";
        $fallbackResponse .= 'Error details: ' . $originalError->getMessage();

        return $fallbackResponse;
    }

    /**
     * Post-execution reflection and learning
     */
    protected function reflectOnExecution(string $input, AgentResponse $response, array $classification): void
    {
        // Simple reflection for now - can be enhanced with pattern learning
        $this->logger?->info('Request completed', [
            'classification_type' => $classification['request_type'] ?? 'unknown',
            'consistency_score' => $classification['consistency_score'] ?? 0,
            'response_length' => mb_strlen($response->content),
            'success' => $response->success,
        ]);

        // Store successful patterns for future learning
        if ($response->success && ($classification['consistency_score'] ?? 0) > 0.8) {
            $this->recordSuccessPattern($input, $classification, $response);
        }
    }

    /**
     * Handle errors with potential recovery
     */
    /**
     * Enhanced processing with recovery mechanisms
     */
    protected function processWithChannelsAndRecovery(string $userInput): AgentResponse
    {
        try {
            return $this->processWithChannels($userInput);
        } catch (Exception $e) {
            $this->logger?->warning('Primary processing failed, attempting recovery', [
                'error' => $e->getMessage(),
            ]);

            // Try simplified processing as fallback
            return $this->simplifiedProcessing($userInput);
        }
    }

    /**
     * Simplified processing fallback
     */
    protected function simplifiedProcessing(string $userInput): AgentResponse
    {
        try {
            // Simplified classification
            $classification = $this->simpleClassification($userInput);

            // Use basic conversation handler
            $handler = new ConversationHandler(
                conversationBuffer: $this->conversationBuffer,
                llmCallback: fn (array $messages, array $options = []) => $this->callLLMWithEnhancements($messages, $options)
            );

            $response = $handler->handle($userInput, $classification, []);

            // Mark as fallback
            return AgentResponse::error(
                error: 'Used simplified processing due to primary system issues',
                partialContent: $response->content,
                metadata: array_merge($response->metadata, [
                    'fallback_mode' => 'simplified',
                    'classification' => $classification,
                ])
            );
        } catch (Exception $e) {
            // Final fallback - emergency response
            return AgentResponse::error(
                error: 'All processing methods failed: ' . $e->getMessage(),
                partialContent: $this->generateEmergencyResponse($userInput)
            );
        }
    }

    /**
     * Enhanced error handling with multiple recovery strategies
     */
    protected function handleErrorWithRecovery(Exception $error, string $input, float $processingTime): AgentResponse
    {
        // Determine error severity and appropriate response
        $severity = $this->categorizeErrorSeverity($error);

        $this->logger?->error('Error categorized', [
            'severity' => $severity,
            'error_type' => get_class($error),
            'processing_time' => $processingTime,
        ]);

        // Generate contextual error message
        $errorMessage = $this->generateContextualErrorMessage($error, $input, $severity);

        // Add error to conversation buffer for learning (only for non-critical errors)
        if ($severity !== 'critical') {
            $this->conversationBuffer->addMessage('error', $errorMessage);
        }

        return AgentResponse::error(
            error: $error->getMessage(),
            partialContent: $errorMessage,
            metadata: [
                'error_type' => get_class($error),
                'severity' => $severity,
                'processing_time' => $processingTime,
                'recovery_suggestions' => $this->getRecoverySuggestions($severity, $error),
                'input_length' => mb_strlen($input),
            ]
        );
    }

    /**
     * Categorize error severity for appropriate response
     */
    protected function categorizeErrorSeverity(Exception $error): string
    {
        $errorMessage = $error->getMessage();
        $errorClass = get_class($error);

        // Critical errors - system level issues
        if (str_contains($errorMessage, 'out of memory') ||
            str_contains($errorMessage, 'segmentation fault') ||
            str_contains($errorClass, 'Error')) {
            return 'critical';
        }

        // High errors - API or service failures
        if (str_contains($errorMessage, 'API') ||
            str_contains($errorMessage, 'network') ||
            str_contains($errorMessage, 'timeout') ||
            str_contains($errorMessage, 'authentication')) {
            return 'high';
        }

        // Medium errors - processing issues
        if (str_contains($errorMessage, 'JSON') ||
            str_contains($errorMessage, 'invalid') ||
            str_contains($errorMessage, 'format')) {
            return 'medium';
        }

        // Low errors - user input issues
        return 'low';
    }

    /**
     * Generate contextual error message based on error type and input
     */
    protected function generateContextualErrorMessage(Exception $error, string $input, string $severity): string
    {
        $baseMessage = match ($severity) {
            'critical' => "I'm experiencing a critical system issue and cannot process requests right now.",
            'high' => "I'm having trouble connecting to external services needed to help you.",
            'medium' => 'I encountered a processing issue with your request.',
            'low' => "There's a problem with how I interpreted your request.",
            default => 'I encountered an unexpected issue with your request.'
        };

        // Add context about what the user was trying to do
        if (mb_strlen($input) > 0) {
            $context = ' I can see you were asking about: ' . mb_substr($input, 0, 100);
            if (mb_strlen($input) > 100) {
                $context .= '...';
            }
        } else {
            $context = '';
        }

        // Add recovery suggestions
        $suggestions = match ($severity) {
            'critical' => ' Please restart the application and try again. If the issue persists, contact system administrator.',
            'high' => ' Please check your internet connection and try again in a few moments.',
            'medium' => ' Please try rephrasing your request or break it into smaller parts.',
            'low' => ' Please check your input and try again.',
            default => ' Please try again or contact support if the issue persists.'
        };

        return $baseMessage . $context . $suggestions;
    }

    /**
     * Get specific recovery suggestions based on error
     */
    protected function getRecoverySuggestions(string $severity, Exception $error): array
    {
        return match ($severity) {
            'critical' => [
                'restart_application',
                'check_system_resources',
                'contact_administrator',
            ],
            'high' => [
                'check_internet_connection',
                'verify_api_credentials',
                'try_again_later',
                'use_offline_mode_if_available',
            ],
            'medium' => [
                'rephrase_request',
                'break_into_smaller_parts',
                'check_input_format',
                'try_simpler_language',
            ],
            'low' => [
                'check_spelling',
                'provide_more_context',
                'use_specific_examples',
            ],
            default => ['try_again_later', 'contact_support']
        };
    }

    /**
     * Simple rule-based classification fallback
     */
    protected function simpleClassification(string $userInput): array
    {
        $input = mb_strtolower(trim($userInput));

        // Rule-based classification
        if (str_contains($input, 'show') || str_contains($input, 'example') || str_contains($input, 'demo')) {
            return [
                'request_type' => 'demonstration',
                'requires_tools' => false,
                'confidence' => 0.6,
                'reasoning' => 'Rule-based: contains demonstration keywords',
            ];
        }

        if (str_contains($input, 'explain') || str_contains($input, 'what is') || str_contains($input, 'how does')) {
            return [
                'request_type' => 'explanation',
                'requires_tools' => false,
                'confidence' => 0.6,
                'reasoning' => 'Rule-based: contains explanation keywords',
            ];
        }

        if (str_contains($input, 'create') || str_contains($input, 'implement') || str_contains($input, 'build')) {
            return [
                'request_type' => 'implementation',
                'requires_tools' => true,
                'confidence' => 0.6,
                'reasoning' => 'Rule-based: contains implementation keywords',
            ];
        }

        // Default to conversation
        return [
            'request_type' => 'conversation',
            'requires_tools' => false,
            'confidence' => 0.4,
            'reasoning' => 'Rule-based: default classification',
        ];
    }

    /**
     * Generate emergency response when all else fails
     */
    protected function generateEmergencyResponse(string $userInput): string
    {
        return "I apologize, but I'm experiencing technical difficulties and cannot fully process your request right now. " .
               "However, I can see you're asking about something related to: " . mb_substr($userInput, 0, 50) . "...\n\n" .
               'Please try again in a moment, or rephrase your request. If the problem continues, ' .
               'you may want to restart the application or check system resources.';
    }

    /**
     * Get classification JSON schema
     */
    protected function getClassificationSchema(): array
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

    /**
     * Fallback classification if self-consistent approach fails
     */
    protected function fallbackClassification(string $userInput): array
    {
        $this->logger?->warning('Using fallback classification due to consistency failure');

        return [
            'request_type' => 'conversation',
            'requires_tools' => false,
            'confidence' => 0.5,
            'reasoning' => 'Fallback classification due to consistency check failure',
            'complexity' => 'simple',
            'estimated_effort' => 'low',
        ];
    }

    /**
     * Record successful patterns for future learning
     */
    protected function recordSuccessPattern(string $input, array $classification, AgentResponse $response): void
    {
        // This could be enhanced to store patterns in database or file for persistence
        $this->logger?->debug('Recording success pattern', [
            'input_pattern' => mb_substr($input, 0, 100),
            'classification' => $classification['request_type'],
            'confidence' => $classification['confidence'],
            'response_success' => $response->success,
        ]);
    }

    /**
     * Report progress with enhanced detail
     */
    protected function reportProgress(string $operation, array $details = []): void
    {
        if ($this->progressCallback) {
            ($this->progressCallback)($operation, $details);
        }

        $this->emit(new ProcessingEvent($operation, $details));
    }
}
