<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Process;

use Exception;
use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\Traits\EventAware;
use HelgeSverre\Swarm\Traits\Loggable;

/**
 * Manages background process execution and IPC communication
 * Handles launching ProcessSpawner and processing updates
 */
class ProcessManager
{
    use EventAware, Loggable;

    private const DEFAULT_TIMEOUT = 600; // 10 minutes

    private const ANIMATION_INTERVAL = 0.1;

    private const PROCESS_SLEEP_MS = 20000; // 20ms

    private ?ProcessSpawner $processor = null;

    private bool $processComplete = false;

    private float $startTime = 0;

    /**
     * Launch a background process to handle the input
     */
    public function launch(string $input): \HelgeSverre\Swarm\CLI\ProcessResult
    {
        $this->processor = new ProcessSpawner($this->log());
        $this->processComplete = false;
        $this->startTime = microtime(true);

        try {
            // Launch the streaming processor
            $this->processor->launch($input);

            // Get timeout from environment or use default
            $maxWaitTime = (int) ($_ENV['SWARM_REQUEST_TIMEOUT'] ?? self::DEFAULT_TIMEOUT);

            // Process updates in real-time
            $lastUpdate = microtime(true);
            $finalUpdate = null;

            while (! $this->processComplete && microtime(true) - $this->startTime < $maxWaitTime) {
                $now = microtime(true);

                // Read any available updates
                $updates = $this->processor->readUpdates();

                foreach ($updates as $update) {
                    $result = $this->processUpdate($update);
                    if ($result->isComplete) {
                        $this->processComplete = true;
                        $finalUpdate = $update;
                        break;
                    }
                }

                // Small sleep to avoid busy waiting
                usleep(self::PROCESS_SLEEP_MS);
            }

            // Check timeout
            if (! $this->processComplete) {
                $this->terminate();
                $timeoutMinutes = round($maxWaitTime / 60, 1);
                throw new Exception(
                    "Request timed out after {$timeoutMinutes} minutes. " .
                    'You can increase the timeout by setting SWARM_REQUEST_TIMEOUT environment variable (in seconds).'
                );
            }

            return new \HelgeSverre\Swarm\CLI\ProcessResult(
                success: true,
                response: $finalUpdate['response'] ?? null,
                error: null
            );
        } catch (Exception $e) {
            $this->logError('Process execution failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return new \HelgeSverre\Swarm\CLI\ProcessResult(
                success: false,
                response: null,
                error: $e->getMessage()
            );
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Process a single update from the streaming processor
     */
    public function processUpdate(array $update): \HelgeSverre\Swarm\CLI\UpdateResult
    {
        $type = $update['type'] ?? 'status';

        return match ($type) {
            'progress' => $this->handleProgressUpdate($update),
            'state_sync' => $this->handleStateSyncUpdate($update),
            'task_status' => $this->handleTaskStatusUpdate($update),
            'status' => $this->handleStatusUpdate($update),
            'error' => $this->handleErrorUpdate($update),
            default => new \HelgeSverre\Swarm\CLI\UpdateResult(false, $type, $update)
        };
    }

    /**
     * Terminate the background process
     */
    public function terminate(): void
    {
        if ($this->processor) {
            $this->processor->terminate();
        }
    }

    /**
     * Clean up resources
     */
    public function cleanup(): void
    {
        if ($this->processor) {
            $this->processor->cleanup();
            $this->processor = null;
        }
    }

    /**
     * Check if a process is currently running
     */
    public function isRunning(): bool
    {
        return $this->processor && $this->processor->isRunning();
    }

    /**
     * Get elapsed time since process start
     */
    public function getElapsedTime(): float
    {
        return $this->startTime > 0 ? microtime(true) - $this->startTime : 0;
    }

    protected function handleProgressUpdate(array $update): \HelgeSverre\Swarm\CLI\UpdateResult
    {
        return new \HelgeSverre\Swarm\CLI\UpdateResult(false, 'progress', $update);
    }

    protected function handleStateSyncUpdate(array $update): \HelgeSverre\Swarm\CLI\UpdateResult
    {
        return new \HelgeSverre\Swarm\CLI\UpdateResult(false, 'state_sync', $update);
    }

    protected function handleTaskStatusUpdate(array $update): \HelgeSverre\Swarm\CLI\UpdateResult
    {
        return new \HelgeSverre\Swarm\CLI\UpdateResult(false, 'task_status', $update);
    }

    protected function handleStatusUpdate(array $update): \HelgeSverre\Swarm\CLI\UpdateResult
    {
        $status = $update['status'] ?? '';

        if ($status === 'completed') {
            return new \HelgeSverre\Swarm\CLI\UpdateResult(true, 'completed', $update);
        } elseif ($status === 'error') {
            throw new Exception($update['error'] ?? 'Unknown error occurred');
        }

        return new \HelgeSverre\Swarm\CLI\UpdateResult(false, 'status', $update);
    }

    protected function handleErrorUpdate(array $update): \HelgeSverre\Swarm\CLI\UpdateResult
    {
        // Log error but don't stop unless it's fatal
        $this->logError('Process error', ['error' => $update['message'] ?? 'Unknown error']);

        return new \HelgeSverre\Swarm\CLI\UpdateResult(false, 'error', $update);
    }
}

/**
 * Result of process execution
 */
class ProcessResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?array $response,
        public readonly ?string $error
    ) {}

    public function getAgentResponse(): ?AgentResponse
    {
        if (! $this->success || ! $this->response) {
            return null;
        }

        return AgentResponse::success(
            $this->response['message'] ?? ''
        );
    }
}

/**
 * Result of processing an update
 */
class UpdateResult
{
    public function __construct(
        public readonly bool $isComplete,
        public readonly string $type,
        public readonly array $data
    ) {}
}
