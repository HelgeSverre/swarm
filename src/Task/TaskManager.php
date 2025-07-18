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

        foreach ($extractedTasks as $task) {
            $taskId = uniqid();
            $this->tasks[] = [
                'id' => $taskId,
                'description' => $task['description'] ?? null,
                'status' => 'pending',
                'plan' => null,
                'steps' => [],
                'created_at' => time(),
            ];

            $this->logger?->debug('Task added', [
                'task_id' => $taskId,
                'description' => $task['description'] ?? null,
            ]);
        }

        $this->logger?->debug('Task queue status', [
            'total_tasks' => count($this->tasks),
            'pending' => $this->countByStatus('pending'),
        ]);
    }

    public function planTask(string $taskId, string $plan, array $steps): void
    {
        $this->logger?->info('Planning task', ['task_id' => $taskId]);

        foreach ($this->tasks as &$task) {
            if ($task['id'] === $taskId) {
                $task['plan'] = $plan;
                $task['steps'] = $steps;
                $task['status'] = 'planned';

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
        foreach ($this->tasks as &$task) {
            if ($task['status'] === 'planned') {
                $task['status'] = 'executing';
                $this->currentTask = $task;

                $this->logger?->info('Task execution started', [
                    'task_id' => $task['id'],
                    'description' => $task['description'],
                ]);

                return $task;
            }
        }

        $this->logger?->debug('No planned tasks available');

        return null;
    }

    public function completeCurrentTask(): void
    {
        if ($this->currentTask) {
            $taskId = $this->currentTask['id'];
            $executionTime = time() - ($this->currentTask['created_at'] ?? time());

            foreach ($this->tasks as &$task) {
                if ($task['id'] === $taskId) {
                    $task['status'] = 'completed';

                    $this->logger?->info('Task completed', [
                        'task_id' => $taskId,
                        'description' => $task['description'],
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
        return $this->tasks;
    }

    protected function countByStatus(string $status): int
    {
        return count(array_filter($this->tasks, fn ($task) => $task['status'] === $status));
    }
}
