<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use Closure;
use Exception;
use Psr\Log\LoggerInterface;

final class ErrorRecoveryPolicy
{
    public function __construct(
        private readonly ConversationBuffer $conversationBuffer,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @param Closure(string): AgentResponse $primaryProcessor
     * @param Closure(string): array{response: AgentResponse, classification?: array} $simplifiedProcessor
     */
    public function runWithRecovery(
        string $userInput,
        Closure $primaryProcessor,
        Closure $simplifiedProcessor,
    ): AgentResponse {
        try {
            return $primaryProcessor($userInput);
        } catch (Exception $primary) {
            $this->logger?->warning('Primary processing failed, attempting simplified fallback', [
                'error' => $primary->getMessage(),
            ]);
        }

        try {
            $fallback = $simplifiedProcessor($userInput);
            $response = $fallback['response'];
            $metadata = array_merge($response->metadata, ['fallback_mode' => 'simplified']);

            if (isset($fallback['classification'])) {
                $metadata['classification'] = $fallback['classification'];
            }

            return AgentResponse::error(
                error: 'Used simplified processing due to primary system issues',
                partialContent: $response->content,
                metadata: $metadata,
            );
        } catch (Exception $fallback) {
            return AgentResponse::error(
                error: 'All processing methods failed: ' . $fallback->getMessage(),
                partialContent: $this->generateFallbackResponse($userInput),
            );
        }
    }

    public function handleFatalProcessingError(Exception $error, string $input, float $processingTime): AgentResponse
    {
        $errorMessage = $error->getMessage();
        $errorClass = get_class($error);
        $severity = $this->determineSeverity($errorMessage, $errorClass);

        $this->logger?->error('Error categorized', [
            'severity' => $severity,
            'error_type' => $errorClass,
            'processing_time' => $processingTime,
        ]);

        $userMessage = $this->buildUserMessage($severity, $input);

        if ($severity !== 'critical') {
            $this->conversationBuffer->addMessage('error', $userMessage);
        }

        return AgentResponse::error(
            error: $errorMessage,
            partialContent: $userMessage,
            metadata: [
                'error_type' => $errorClass,
                'severity' => $severity,
                'processing_time' => $processingTime,
                'recovery_suggestions' => $this->recoverySuggestions($severity),
                'input_length' => mb_strlen($input),
            ],
        );
    }

    public function generateFallbackResponse(array|string $context, ?Exception $originalError = null): string
    {
        $userSnippet = '';
        if (is_array($context)) {
            for ($i = count($context) - 1; $i >= 0; $i--) {
                if (($context[$i]['role'] ?? null) === 'user') {
                    $userSnippet = (string) ($context[$i]['content'] ?? '');
                    break;
                }
            }
        } else {
            $userSnippet = $context;
        }

        $response = "I apologize, but I'm experiencing technical difficulties and cannot fully process your request right now.";

        if ($userSnippet !== '') {
            $truncated = mb_substr($userSnippet, 0, 100);
            $response .= ' I can see you were asking about: ' . $truncated;
            if (mb_strlen($userSnippet) > 100) {
                $response .= '...';
            }
        }

        $response .= "\n\nPlease try again in a few moments, or rephrase your request.";
        $response .= ' If the issue persists, you may want to restart the application or check system resources.';

        if ($originalError !== null) {
            $response .= "\n\nError details: " . $originalError->getMessage();
        }

        return $response;
    }

    private function determineSeverity(string $errorMessage, string $errorClass): string
    {
        return match (true) {
            str_contains($errorMessage, 'out of memory'),
            str_contains($errorMessage, 'segmentation fault'),
            str_contains($errorClass, 'Error') => 'critical',

            str_contains($errorMessage, 'API'),
            str_contains($errorMessage, 'network'),
            str_contains($errorMessage, 'timeout'),
            str_contains($errorMessage, 'authentication') => 'high',

            str_contains($errorMessage, 'JSON'),
            str_contains($errorMessage, 'invalid'),
            str_contains($errorMessage, 'format') => 'medium',

            default => 'low',
        };
    }

    private function buildUserMessage(string $severity, string $input): string
    {
        $message = match ($severity) {
            'critical' => "I'm experiencing a critical system issue and cannot process requests right now.",
            'high' => "I'm having trouble connecting to external services needed to help you.",
            'medium' => 'I encountered a processing issue with your request.',
            default => "There's a problem with how I interpreted your request.",
        };

        if ($input !== '') {
            $message .= ' I can see you were asking about: ' . mb_substr($input, 0, 100);
            if (mb_strlen($input) > 100) {
                $message .= '...';
            }
        }

        $message .= match ($severity) {
            'critical' => ' Please restart the application and try again. If the issue persists, contact system administrator.',
            'high' => ' Please check your internet connection and try again in a few moments.',
            'medium' => ' Please try rephrasing your request or break it into smaller parts.',
            default => ' Please check your input and try again.',
        };

        return $message;
    }

    private function recoverySuggestions(string $severity): array
    {
        return match ($severity) {
            'critical' => ['restart_application', 'check_system_resources', 'contact_administrator'],
            'high' => ['check_internet_connection', 'verify_api_credentials', 'try_again_later', 'use_offline_mode_if_available'],
            'medium' => ['rephrase_request', 'break_into_smaller_parts', 'check_input_format', 'try_simpler_language'],
            default => ['check_spelling', 'provide_more_context', 'use_specific_examples'],
        };
    }
}
