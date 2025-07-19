<?php

namespace HelgeSverre\Swarm\Task;

use Psr\Log\LoggerInterface;

class TaskManager
{
    public ?Task $currentTask = null;

    /**
     * @var Task[]
     */
    protected array $tasks = [];

    /**
     * @var array<array{id: string, description: string, status: string, created_at: int, completed_at: int, execution_time?: int}>
     */
    protected array $taskHistory = [];

    /**
     * Maximum number of tasks to keep in history
     */
    protected int $historyLimit = 1000;

    public function __construct(
        protected readonly ?LoggerInterface $logger = null
    ) {}

    public function addTasks(array $extractedTasks): void
    {
        $taskCount = count($extractedTasks);
        $this->logger?->info('Adding tasks to queue', ['task_count' => $taskCount]);

        foreach ($extractedTasks as $taskData) {
            $description = $taskData['description'] ?? null;
            if ($description) {
                $task = Task::create($description);
                $this->tasks[] = $task;

                $this->logger?->debug('Task added', [
                    'task_id' => $task->id,
                    'description' => $task->description,
                ]);
            }
        }

        $this->logger?->debug('Task queue status', [
            'total_tasks' => count($this->tasks),
            'pending' => $this->countByStatus('pending'),
        ]);
    }

    public function planTask(string $taskId, string $plan, array $steps): void
    {
        $this->logger?->info('Planning task', ['task_id' => $taskId]);

        foreach ($this->tasks as $index => $task) {
            if ($task->id === $taskId) {
                $this->tasks[$index] = $task->withPlan($plan, $steps);

                $this->logger?->debug('Task planned', [
                    'task_id' => $taskId,
                    'plan_length' => mb_strlen($plan),
                    'step_count' => count($steps),
                ]);

                break;
            }
        }
    }

    public function getNextTask(): ?Task
    {
        foreach ($this->tasks as $index => $task) {
            if ($task->status === TaskStatus::Planned) {
                $this->tasks[$index] = $task->startExecuting();
                $this->currentTask = $this->tasks[$index];

                $this->logger?->info('Task execution started', [
                    'task_id' => $task->id,
                    'description' => $task->description,
                ]);

                return $this->currentTask;
            }
        }

        $this->logger?->debug('No planned tasks available');

        return null;
    }

    public function completeCurrentTask(): void
    {
        if ($this->currentTask) {
            $taskId = $this->currentTask->id;
            $executionTime = time() - $this->currentTask->createdAt->getTimestamp();

            foreach ($this->tasks as $index => $task) {
                if ($task->id === $taskId) {
                    $this->tasks[$index] = $task->complete();

                    // Add to history
                    $this->addToHistory($this->tasks[$index]);

                    $this->logger?->info('Task completed', [
                        'task_id' => $taskId,
                        'description' => $task->description,
                        'execution_time_seconds' => $executionTime,
                    ]);

                    break;
                }
            }

            $this->currentTask = null;

            $this->logger?->debug('Task queue status after completion', [
                'total_tasks' => count($this->tasks),
                'completed' => $this->countByStatus('completed'),
                'pending' => $this->countByStatus('pending'),
                'planned' => $this->countByStatus('planned'),
            ]);
        }
    }

    /**
     * @return Task[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get task history
     *
     * @return array<array{id: string, description: string, status: string, created_at: int, completed_at: int, execution_time?: int}>
     */
    public function getTaskHistory(): array
    {
        return $this->taskHistory;
    }

    /**
     * Set task history (for loading from state)
     *
     * @param array<array{id: string, description: string, status: string, created_at: int, completed_at: int, execution_time?: int}> $history
     */
    public function setTaskHistory(array $history): void
    {
        $this->taskHistory = $history;
    }

    /**
     * Clear completed tasks from the active task list
     *
     * @return Task[] The completed tasks that were removed
     */
    public function clearCompletedTasks(): array
    {
        $completed = [];
        $active = [];

        foreach ($this->tasks as $task) {
            if ($task->isCompleted()) {
                $completed[] = $task;
                // Ensure task is in history
                $this->addToHistory($task);
            } else {
                $active[] = $task;
            }
        }

        $this->tasks = $active;

        $this->logger?->info('Cleared completed tasks', [
            'completed_count' => count($completed),
            'active_count' => count($active),
        ]);

        return $completed;
    }

    /**
     * Get all tasks as arrays (for backward compatibility)
     */
    public function getTasksAsArrays(): array
    {
        return array_map(fn (Task $task) => $task->toArray(), $this->tasks);
    }

    protected function countByStatus(string $status): int
    {
        $statusEnum = TaskStatus::from($status);

        return count(array_filter($this->tasks, fn (Task $task) => $task->status === $statusEnum));
    }

    /**
     * Add a completed task to history
     */
    protected function addToHistory(Task $task): void
    {
        if ($task->status !== TaskStatus::Completed) {
            return;
        }

        // Check if task is already in history
        foreach ($this->taskHistory as $historyItem) {
            if ($historyItem['id'] === $task->id) {
                return; // Task already in history
            }
        }

        $historyEntry = $task->toArray();

        // Calculate execution time if not already set
        if (isset($historyEntry['created_at'], $historyEntry['completed_at'])) {
            $historyEntry['execution_time'] = $historyEntry['completed_at'] - $historyEntry['created_at'];
        }

        $this->taskHistory[] = $historyEntry;

        // Maintain history limit
        while (count($this->taskHistory) > $this->historyLimit) {
            array_shift($this->taskHistory);
        }

        $this->logger?->debug('Task added to history', [
            'task_id' => $task->id,
            'history_count' => count($this->taskHistory),
        ]);
    }
}
