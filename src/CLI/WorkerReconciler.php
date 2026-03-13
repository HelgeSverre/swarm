<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use HelgeSverre\Swarm\CLI\Process\Message\WorkerUpdateType;
use HelgeSverre\Swarm\CLI\Process\ProcessManager;
use HelgeSverre\Swarm\Contracts\UIInterface;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ProcessCompleteEvent;
use HelgeSverre\Swarm\Events\ProcessProgressEvent;
use HelgeSverre\Swarm\Traits\Loggable;

/**
 * Polls worker processes for updates and reconciles results
 * into the synced state and UI.
 */
class WorkerReconciler
{
    use Loggable;

    public function __construct(
        protected ProcessManager $processManager,
        protected StateSyncAdapter $stateSync,
        protected EventBus $eventBus,
        protected UIInterface $ui,
    ) {}

    /**
     * Poll all active processes and reconcile any updates
     */
    public function poll(): void
    {
        $updates = $this->processManager->pollUpdates();

        if (! empty($updates)) {
            $this->logDebug('Polling found updates', ['update_count' => count($updates)]);
        }

        foreach ($updates as $update) {
            $payload = $update->toArray();
            $processId = $update->processId ?? '';

            $this->logDebug('Processing update from worker', [
                'processId' => $processId,
                'type' => $update->type->value,
                'status' => $update->status(),
            ]);

            $this->eventBus->emit(new ProcessProgressEvent(
                processId: $processId,
                type: $update->type->value,
                data: $payload,
            ));

            if ($update->type === WorkerUpdateType::StateSync && isset($payload['data'])) {
                $this->applyStateSync($payload['data']);
            }

            if ($update->isCompletedStatus()) {
                $this->handleComplete($processId, $payload);
            }
        }
    }

    /**
     * Apply a state-sync payload from a worker to the synced state
     */
    protected function applyStateSync(array $data): void
    {
        if (isset($data['tasks'])) {
            $this->stateSync->set('tasks', $data['tasks']);
        }

        if (isset($data['current_task'])) {
            $currentTask = is_array($data['current_task'])
                ? ($data['current_task']['description'] ?? null)
                : $data['current_task'];
            $this->stateSync->set('current_task', $currentTask);
        }

        if (isset($data['operation'])) {
            $this->stateSync->set('operation', $data['operation']);
        }

        $this->stateSync->emitUpdate();
    }

    /**
     * Handle a completed worker process
     */
    protected function handleComplete(string $processId, array $update): void
    {
        $result = $this->processManager->getProcessResult($processId);

        if ($result && $result->success) {
            $response = $result->getAgentResponse();
            if ($response) {
                $this->stateSync->addConversationMessage('assistant', $response->getMessage());
                $this->eventBus->emit(new ProcessCompleteEvent($processId, $response));
            }
        }

        $this->ui->stopProcessing();
        $this->stateSync->save();
        $this->stateSync->emitUpdate();
    }
}
