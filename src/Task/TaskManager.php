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

    public function getNextTask(): ?array
    {
        foreach ($this->tasks as $index => $task) {
            if ($task->status === TaskStatus::Planned) {
                $this->tasks[$index] = $task->startExecuting();
                $this->currentTask = $this->tasks[$index];

                $this->logger?->info('Task execution started', [
                    'task_id' => $task->id,
                    'description' => $task->description,
                ]);

                return $this->currentTask->toArray();
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

    public function getTasks(): array
    {
        return array_map(fn (Task $task) => $task->toArray(), $this->tasks);
    }

    protected function countByStatus(string $status): int
    {
        $statusEnum = TaskStatus::from($status);

        return count(array_filter($this->tasks, fn (Task $task) => $task->status === $statusEnum));
    }
}
