<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Process;

use Exception;
use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\Core\Application;
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

    protected Application $app;

    private array $activeProcesses = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function startProcess(string $input): string
    {
        $processId = uniqid('proc_');
        $processor = new ProcessSpawner($this->app, $this->log());

        $processor->launch($input);

        $this->activeProcesses[$processId] = [
            'processor' => $processor,
            'startTime' => microtime(true),
            'complete' => false,
            'input' => $input,
            'updates' => [],
        ];

        return $processId;
    }

    public function pollUpdates(): array
    {
        $allUpdates = [];

        if (empty($this->activeProcesses)) {
            return $allUpdates;
        }

        $this->logDebug('Polling processes', ['process_count' => count($this->activeProcesses)]);

        foreach ($this->activeProcesses as $processId => $processData) {
            if ($processData['complete']) {
                continue;
            }

            $updates = $processData['processor']->readUpdates();

            if (! empty($updates)) {
                $this->logDebug('Found updates from process', [
                    'processId' => $processId,
                    'update_count' => count($updates),
                ]);
            }

            foreach ($updates as $update) {
                $update['processId'] = $processId;
                $allUpdates[] = $update;

                if (($update['type'] ?? '') === 'status' && ($update['status'] ?? '') === 'completed') {
                    $this->activeProcesses[$processId]['complete'] = true;
                    $this->activeProcesses[$processId]['result'] = $update;
                }
            }
        }

        return $allUpdates;
    }

    public function getProcessResult(string $processId): ?ProcessResult
    {
        $process = $this->activeProcesses[$processId] ?? null;
        if (! $process || ! $process['complete']) {
            return null;
        }

        return new ProcessResult(
            success: true,
            response: $process['result']['response'] ?? null,
            error: null
        );
    }

    public function cleanupCompletedProcesses(): void
    {
        foreach ($this->activeProcesses as $processId => $process) {
            if ($process['complete']) {
                $process['processor']->cleanup();
                unset($this->activeProcesses[$processId]);
            }
        }
    }

    /**
     * Process a single update from the streaming processor
     */
    public function processUpdate(array $update): UpdateResult
    {
        $type = $update['type'] ?? 'status';

        return match ($type) {
            'progress' => $this->handleProgressUpdate($update),
            'state_sync' => $this->handleStateSyncUpdate($update),
            'task_status' => $this->handleTaskStatusUpdate($update),
            'status' => $this->handleStatusUpdate($update),
            'error' => $this->handleErrorUpdate($update),
            default => new UpdateResult(false, $type, $update)
        };
    }

    public function terminate(string $processId): void
    {
        $process = $this->activeProcesses[$processId] ?? null;
        if ($process) {
            $process['processor']->terminate();
        }
    }

    public function terminateAll(): void
    {
        foreach ($this->activeProcesses as $processData) {
            $processData['processor']->terminate();
        }
        $this->activeProcesses = [];
    }

    public function getActiveProcessCount(): int
    {
        return count(array_filter($this->activeProcesses, fn ($p) => ! $p['complete']));
    }

    public function hasActiveProcesses(): bool
    {
        return $this->getActiveProcessCount() > 0;
    }

    protected function handleProgressUpdate(array $update): UpdateResult
    {
        return new UpdateResult(false, 'progress', $update);
    }

    protected function handleStateSyncUpdate(array $update): UpdateResult
    {
        return new UpdateResult(false, 'state_sync', $update);
    }

    protected function handleTaskStatusUpdate(array $update): UpdateResult
    {
        return new UpdateResult(false, 'task_status', $update);
    }

    protected function handleStatusUpdate(array $update): UpdateResult
    {
        $status = $update['status'] ?? '';

        if ($status === 'completed') {
            return new UpdateResult(true, 'completed', $update);
        } elseif ($status === 'error') {
            throw new Exception($update['error'] ?? 'Unknown error occurred');
        }

        return new UpdateResult(false, 'status', $update);
    }

    protected function handleErrorUpdate(array $update): UpdateResult
    {
        // Log error but don't stop unless it's fatal
        $this->logError('Process error', ['error' => $update['message'] ?? 'Unknown error']);

        return new UpdateResult(false, 'error', $update);
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
