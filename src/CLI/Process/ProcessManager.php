<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Process;

use Exception;
use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\CLI\Process\Message\WorkerUpdate;
use HelgeSverre\Swarm\CLI\Process\Message\WorkerUpdateType;
use HelgeSverre\Swarm\Core\Application;
use HelgeSverre\Swarm\Traits\Loggable;

/**
 * Manages background process execution and IPC communication
 * Handles launching ProcessSpawner and processing updates
 */
class ProcessManager
{
    use Loggable;

    protected Application $app;

    protected array $activeProcesses = [];

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

        // Only log when we have updates to avoid log spam
        // $this->logDebug('Polling processes', ['process_count' => count($this->activeProcesses)]);

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
                $workerUpdate = WorkerUpdate::fromArray($update)->withProcessId($processId);
                $allUpdates[] = $workerUpdate;

                if ($workerUpdate->isCompletedStatus()) {
                    $this->activeProcesses[$processId]['complete'] = true;
                    $this->activeProcesses[$processId]['result'] = $workerUpdate;
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
            response: $process['result'] instanceof WorkerUpdate ? $process['result']->response() : null,
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
    public function processUpdate(array|WorkerUpdate $update): UpdateResult
    {
        $workerUpdate = $update instanceof WorkerUpdate ? $update : WorkerUpdate::fromArray($update);

        return match ($workerUpdate->type) {
            WorkerUpdateType::Progress => $this->handleProgressUpdate($workerUpdate),
            WorkerUpdateType::StateSync => $this->handleStateSyncUpdate($workerUpdate),
            WorkerUpdateType::TaskStatus => $this->handleTaskStatusUpdate($workerUpdate),
            WorkerUpdateType::Status => $this->handleStatusUpdate($workerUpdate),
            WorkerUpdateType::Error => $this->handleErrorUpdate($workerUpdate),
            default => new UpdateResult(false, $workerUpdate->type->value, $workerUpdate->toArray())
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

    protected function handleProgressUpdate(WorkerUpdate $update): UpdateResult
    {
        return new UpdateResult(false, WorkerUpdateType::Progress->value, $update->toArray());
    }

    protected function handleStateSyncUpdate(WorkerUpdate $update): UpdateResult
    {
        return new UpdateResult(false, WorkerUpdateType::StateSync->value, $update->toArray());
    }

    protected function handleTaskStatusUpdate(WorkerUpdate $update): UpdateResult
    {
        return new UpdateResult(false, WorkerUpdateType::TaskStatus->value, $update->toArray());
    }

    protected function handleStatusUpdate(WorkerUpdate $update): UpdateResult
    {
        $status = $update->status() ?? '';

        if ($status === 'completed') {
            return new UpdateResult(true, 'completed', $update->toArray());
        } elseif ($status === 'error') {
            $payload = $update->toArray();

            throw new Exception($payload['error'] ?? 'Unknown error occurred');
        }

        return new UpdateResult(false, WorkerUpdateType::Status->value, $update->toArray());
    }

    protected function handleErrorUpdate(WorkerUpdate $update): UpdateResult
    {
        $payload = $update->toArray();

        // Log error but don't stop unless it's fatal
        $this->logError('Process error', ['error' => $payload['message'] ?? 'Unknown error']);

        return new UpdateResult(false, WorkerUpdateType::Error->value, $payload);
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
