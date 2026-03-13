<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use Closure;
use Exception;
use OpenAI\Contracts\ClientContract;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class LlmGateway
{
    private bool $useCustomTools = true;

    public function __construct(
        private readonly ClientContract $llmClient,
        private readonly ConversationBuffer $conversationBuffer,
        private readonly ErrorRecoveryPolicy $errorRecoveryPolicy,
        private readonly ?AgentProgressReporter $progressReporter = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?Closure $delayCallback = null,
        private readonly string $model = 'gpt-4o-mini',
        private readonly string $reasoningEffort = 'medium',
        private readonly string $verbosity = 'medium',
    ) {}

    public function call(array $messages, array $options = []): string
    {
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
        $backoffDelay = 1.0;

        while ($retryCount <= $maxRetries) {
            try {
                $this->reportProgress('calling_openai', [
                    'model' => $requestOptions['model'],
                    'attempt' => $retryCount + 1,
                    'max_retries' => $maxRetries + 1,
                ]);

                $response = $this->llmClient->chat()->create($requestOptions);
                $choice = $response->choices[0] ?? null;
                if ($choice === null) {
                    throw new RuntimeException('No choices in LLM response');
                }

                $message = $choice->message ?? null;
                if ($message === null) {
                    throw new RuntimeException('No message in LLM response choice');
                }

                $content = $message->content ?? '';
                if ($content === '') {
                    throw new RuntimeException('Empty content from LLM response');
                }

                $this->conversationBuffer->addMessage('assistant', $content);

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

                    return $this->errorRecoveryPolicy->generateFallbackResponse($messages, $e);
                }

                $delay = $backoffDelay + random_int(0, 1000000) / 1000000;
                $this->logger?->info("Retrying LLM call after {$delay}s delay");
                $this->delay($delay);
                $backoffDelay *= 2;
            }
        }

        return $this->errorRecoveryPolicy->generateFallbackResponse(
            $messages,
            new RuntimeException('Max retries exceeded'),
        );
    }

    public function setUseCustomTools(bool $use): void
    {
        $this->useCustomTools = $use;
    }

    private function reportProgress(string $operation, array $details = []): void
    {
        if ($this->progressReporter !== null) {
            $this->progressReporter->report($operation, $details);
        }
    }

    private function delay(float $seconds): void
    {
        if ($this->delayCallback !== null) {
            ($this->delayCallback)($seconds);

            return;
        }

        sleep((int) $seconds);
    }
}
