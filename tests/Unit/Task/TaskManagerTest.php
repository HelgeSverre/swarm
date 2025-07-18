<?php

use HelgeSverre\Swarm\Task\TaskManager;
use Psr\Log\NullLogger;

test('task manager can add single task', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Create user migration'],
    ]);

    $tasks = $taskManager->getTasks();

    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]['description'])->toBe('Create user migration')
        ->and($tasks[0]['status'])->toBe('pending')
        ->and($tasks[0]['plan'])->toBeNull()
        ->and($tasks[0]['steps'])->toBe([])
        ->and($tasks[0]['id'])->toBeString()
        ->and($tasks[0]['created_at'])->toBeInt();
});

test('task manager can add multiple tasks', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Create user migration'],
        ['description' => 'Add authentication'],
        ['description' => 'Setup database seeder'],
    ]);

    $tasks = $taskManager->getTasks();

    expect($tasks)->toHaveCount(3)
        ->and($tasks[0]['description'])->toBe('Create user migration')
        ->and($tasks[1]['description'])->toBe('Add authentication')
        ->and($tasks[2]['description'])->toBe('Setup database seeder');
});

test('task manager handles empty task description', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['other_field' => 'value'], // No description field
    ]);

    $tasks = $taskManager->getTasks();

    // Tasks without description are now filtered out
    expect($tasks)->toBeEmpty();
});

test('task manager can plan a task', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Create user migration'],
    ]);

    $taskId = $taskManager->getTasks()[0]['id'];
    $plan = 'First create migration file, then add schema';
    $steps = ['Create migration', 'Add schema', 'Run migration'];

    $taskManager->planTask($taskId, $plan, $steps);

    $tasks = $taskManager->getTasks();

    expect($tasks[0]['plan'])->toBe($plan)
        ->and($tasks[0]['steps'])->toBe($steps)
        ->and($tasks[0]['status'])->toBe('planned');
});

test('planning non-existent task does not throw error', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->planTask('non-existent-id', 'Some plan', []);

    expect($taskManager->getTasks())->toBeEmpty();
});

test('task manager gets next planned task', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Task 1'],
        ['description' => 'Task 2'],
        ['description' => 'Task 3'],
    ]);

    $taskIds = array_column($taskManager->getTasks(), 'id');

    // Plan only the second task
    $taskManager->planTask($taskIds[1], 'Plan for task 2', []);

    $nextTask = $taskManager->getNextTask();

    expect($nextTask)->not->toBeNull()
        ->and($nextTask['description'])->toBe('Task 2')
        ->and($nextTask['status'])->toBe('executing')
        ->and($taskManager->currentTask->toArray())->toBe($nextTask);
});

test('getNextTask returns null when no planned tasks', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Task 1'], // pending
        ['description' => 'Task 2'],  // pending
    ]);

    $nextTask = $taskManager->getNextTask();

    expect($nextTask)->toBeNull()
        ->and($taskManager->currentTask)->toBeNull();
});

test('task manager completes current task', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Create user migration'],
    ]);

    $taskId = $taskManager->getTasks()[0]['id'];
    $taskManager->planTask($taskId, 'Plan', []);

    $taskManager->getNextTask(); // Sets as executing
    $taskManager->completeCurrentTask();

    $tasks = $taskManager->getTasks();

    expect($tasks[0]['status'])->toBe('completed')
        ->and($taskManager->currentTask)->toBeNull();
});

test('completing task when no current task does not throw error', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->completeCurrentTask();

    expect($taskManager->currentTask)->toBeNull();
});

test('task state transitions work correctly', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Test task'],
    ]);

    $taskId = $taskManager->getTasks()[0]['id'];

    // Initial state
    expect($taskManager->getTasks()[0]['status'])->toBe('pending');

    // Plan the task
    $taskManager->planTask($taskId, 'Test plan', ['Step 1', 'Step 2']);
    expect($taskManager->getTasks()[0]['status'])->toBe('planned');

    // Start executing
    $taskManager->getNextTask();
    expect($taskManager->getTasks()[0]['status'])->toBe('executing');

    // Complete the task
    $taskManager->completeCurrentTask();
    expect($taskManager->getTasks()[0]['status'])->toBe('completed');
});

test('multiple tasks are processed in order', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Task 1'],
        ['description' => 'Task 2'],
        ['description' => 'Task 3'],
    ]);

    $taskIds = array_column($taskManager->getTasks(), 'id');

    // Plan all tasks
    $taskManager->planTask($taskIds[0], 'Plan 1', []);
    $taskManager->planTask($taskIds[1], 'Plan 2', []);
    $taskManager->planTask($taskIds[2], 'Plan 3', []);

    // Process first task
    $task1 = $taskManager->getNextTask();
    expect($task1['description'])->toBe('Task 1');
    $taskManager->completeCurrentTask();

    // Process second task
    $task2 = $taskManager->getNextTask();
    expect($task2['description'])->toBe('Task 2');
    $taskManager->completeCurrentTask();

    // Process third task
    $task3 = $taskManager->getNextTask();
    expect($task3['description'])->toBe('Task 3');
    $taskManager->completeCurrentTask();

    // No more tasks
    expect($taskManager->getNextTask())->toBeNull();
});

test('countByStatus returns correct counts', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Task 1'],
        ['description' => 'Task 2'],
        ['description' => 'Task 3'],
        ['description' => 'Task 4'],
    ]);

    $taskIds = array_column($taskManager->getTasks(), 'id');

    // Plan two tasks
    $taskManager->planTask($taskIds[0], 'Plan 1', []);
    $taskManager->planTask($taskIds[1], 'Plan 2', []);

    // Execute and complete one
    $taskManager->getNextTask();
    $taskManager->completeCurrentTask();

    // Use reflection to test protected method
    $reflection = new ReflectionClass($taskManager);
    $method = $reflection->getMethod('countByStatus');
    $method->setAccessible(true);

    expect($method->invoke($taskManager, 'pending'))->toBe(2)
        ->and($method->invoke($taskManager, 'planned'))->toBe(1)
        ->and($method->invoke($taskManager, 'executing'))->toBe(0)
        ->and($method->invoke($taskManager, 'completed'))->toBe(1);
});

test('task ids are unique', function () {
    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Task 1'],
        ['description' => 'Task 2'],
        ['description' => 'Task 3'],
    ]);

    $tasks = $taskManager->getTasks();
    $ids = array_column($tasks, 'id');

    expect($ids)->toHaveCount(3)
        ->and(array_unique($ids))->toHaveCount(3); // All IDs are unique
});

test('task created_at timestamps are set', function () {
    $startTime = time();

    $taskManager = new TaskManager(new NullLogger);

    $taskManager->addTasks([
        ['description' => 'Test task'],
    ]);

    $task = $taskManager->getTasks()[0];

    expect($task['created_at'])->toBeGreaterThanOrEqual($startTime)
        ->and($task['created_at'])->toBeLessThanOrEqual(time());
});
