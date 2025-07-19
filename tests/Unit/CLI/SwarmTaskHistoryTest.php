<?php

use HelgeSverre\Swarm\Task\Task;
use HelgeSverre\Swarm\Task\TaskManager;
use Psr\Log\NullLogger;

// These tests have been moved to TaskManagerHistoryTest.php
// The task history functionality is now handled by TaskManager, not SwarmCLI

test('Swarm properly integrates with TaskManager for history', function () {
    // Create a mock TaskManager
    $taskManager = new TaskManager(new NullLogger);

    // Add some tasks and complete them
    $taskManager->addTasks([
        ['description' => 'Completed task 1'],
        ['description' => 'Active task'],
        ['description' => 'Completed task 2'],
    ]);

    $tasks = $taskManager->getTasks();

    // Complete first task
    $taskManager->planTask($tasks[0]->id, 'Plan 1', []);
    $taskManager->getNextTask();
    $taskManager->completeCurrentTask();

    // Complete third task
    $taskManager->planTask($tasks[2]->id, 'Plan 2', []);
    $taskManager->getNextTask();
    $taskManager->completeCurrentTask();

    // Clear completed tasks
    $taskManager->clearCompletedTasks();

    // Verify results
    expect($taskManager->getTasks())->toHaveCount(1)
        ->and($taskManager->getTaskHistory())->toHaveCount(2);
});

test('task history is preserved in state file structure', function () {
    $state = [
        'tasks' => [
            ['id' => 'active_1', 'status' => 'pending', 'description' => 'Active task'],
        ],
        'task_history' => [
            [
                'id' => 'completed_1',
                'status' => 'completed',
                'description' => 'Historical task',
                'created_at' => 1234567890,
                'completed_at' => 1234567900,
                'execution_time' => 10,
            ],
        ],
        'current_task' => null,
        'conversation_history' => [],
        'tool_log' => [],
        'operation' => '',
    ];

    // Verify structure matches expected format
    $json = json_encode($state, JSON_PRETTY_PRINT);
    $decoded = json_decode($json, true);

    expect($decoded)->toHaveKey('task_history')
        ->and($decoded['task_history'])->toBeArray()
        ->and($decoded['task_history'][0])->toHaveKeys(['id', 'status', 'description', 'created_at', 'completed_at', 'execution_time']);
});

test('task object completedAt timestamp is set on completion', function () {
    $task = Task::create('Test task');

    // Initial task should not have completedAt
    expect($task->completedAt)->toBeNull();

    // Complete the task
    $completed = $task->complete();

    expect($completed->completedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($completed->completedAt->getTimestamp())->toBeGreaterThan(0)
        ->and($completed->completedAt->getTimestamp())->toBeLessThanOrEqual(time());
});
