<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

use HelgeSverre\Swarm\Task\Task;

/**
 * Event emitted when a task status changes
 */
class TaskUpdateEvent extends Event
{
    public function __construct(
        public readonly Task $task,
        public readonly string $previousStatus,
        public readonly string $newStatus
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'taskId' => $this->task->id,
            'taskDescription' => $this->task->description,
            'previousStatus' => $this->previousStatus,
            'newStatus' => $this->newStatus,
        ]);
    }
}
