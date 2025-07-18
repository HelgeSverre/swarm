<?php

namespace HelgeSverre\Swarm;

class TaskManager
{
    protected $tasks = [];
    public $currentTask = null;

    public function addTasks(array $extractedTasks): void
    {
        foreach ($extractedTasks as $task) {
            $this->tasks[] = [
                'id' => uniqid(),
                'description' => $task['description'],
                'status' => 'pending',
                'plan' => null,
                'steps' => [],
                'created_at' => time()
            ];
        }
    }

    public function planTask(string $taskId, string $plan, array $steps): void
    {
        foreach ($this->tasks as &$task) {
            if ($task['id'] === $taskId) {
                $task['plan'] = $plan;
                $task['steps'] = $steps;
                $task['status'] = 'planned';
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
                return $task;
            }
        }
        return null;
    }

    public function completeCurrentTask(): void
    {
        if ($this->currentTask) {
            foreach ($this->tasks as &$task) {
                if ($task['id'] === $this->currentTask['id']) {
                    $task['status'] = 'completed';
                    break;
                }
            }
            $this->currentTask = null;
        }
    }

    public function getTasks(): array
    {
        return $this->tasks;
    }
}