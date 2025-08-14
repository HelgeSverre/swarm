<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

/**
 * Event emitted when system state changes
 */
class StateUpdateEvent extends Event
{
    public function __construct(
        public readonly array $tasks = [],
        public readonly ?string $currentTask = null,
        public readonly array $conversationHistory = [],
        public readonly array $toolLog = [],
        public readonly array $context = [],
        public readonly string $status = 'ready'
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'tasks' => $this->tasks,
            'currentTask' => $this->currentTask,
            'conversationHistoryCount' => count($this->conversationHistory),
            'toolLogCount' => count($this->toolLog),
            'context' => $this->context,
            'status' => $this->status,
        ]);
    }
}
