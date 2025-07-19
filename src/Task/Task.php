<?php

namespace HelgeSverre\Swarm\Task;

use DateTimeImmutable;

/**
 * Immutable Task value object
 */
readonly class Task
{
    public function __construct(
        public string $id,
        public string $description,
        public TaskStatus $status,
        public ?string $plan = null,
        public array $steps = [],
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $completedAt = null,
    ) {}

    /**
     * Create a new pending task
     */
    public static function create(string $description): self
    {
        return new self(
            id: uniqid('task_', true),
            description: $description,
            status: TaskStatus::Pending,
            plan: null,
            steps: [],
            createdAt: new DateTimeImmutable,
        );
    }

    /**
     * Create a task from array (for backwards compatibility)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            description: $data['description'],
            status: TaskStatus::from($data['status']),
            plan: $data['plan'] ?? null,
            steps: $data['steps'] ?? [],
            createdAt: isset($data['created_at'])
                ? (new DateTimeImmutable)->setTimestamp($data['created_at'])
                : new DateTimeImmutable,
            completedAt: isset($data['completed_at'])
                ? (new DateTimeImmutable)->setTimestamp($data['completed_at'])
                : null,
        );
    }

    /**
     * Convert to array (for backwards compatibility)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'status' => $this->status->value,
            'plan' => $this->plan,
            'steps' => $this->steps,
            'created_at' => $this->createdAt->getTimestamp(),
            'completed_at' => $this->completedAt?->getTimestamp(),
        ];
    }

    /**
     * Create a new task with planned status
     */
    public function withPlan(string $plan, array $steps): self
    {
        return new self(
            id: $this->id,
            description: $this->description,
            status: TaskStatus::Planned,
            plan: $plan,
            steps: $steps,
            createdAt: $this->createdAt,
            completedAt: $this->completedAt,
        );
    }

    /**
     * Create a new task with executing status
     */
    public function startExecuting(): self
    {
        return new self(
            id: $this->id,
            description: $this->description,
            status: TaskStatus::Executing,
            plan: $this->plan,
            steps: $this->steps,
            createdAt: $this->createdAt,
            completedAt: $this->completedAt,
        );
    }

    /**
     * Create a new task with completed status
     */
    public function complete(): self
    {
        return new self(
            id: $this->id,
            description: $this->description,
            status: TaskStatus::Completed,
            plan: $this->plan,
            steps: $this->steps,
            createdAt: $this->createdAt,
            completedAt: new DateTimeImmutable,
        );
    }

    /**
     * Check if the task can be executed
     */
    public function canExecute(): bool
    {
        return $this->status === TaskStatus::Planned;
    }

    /**
     * Check if the task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === TaskStatus::Completed;
    }

    /**
     * Check if the task is currently executing
     */
    public function isExecuting(): bool
    {
        return $this->status === TaskStatus::Executing;
    }

    /**
     * Check if the task is pending
     */
    public function isPending(): bool
    {
        return $this->status === TaskStatus::Pending;
    }

    /**
     * Check if the task is planned
     */
    public function isPlanned(): bool
    {
        return $this->status === TaskStatus::Planned;
    }
}
