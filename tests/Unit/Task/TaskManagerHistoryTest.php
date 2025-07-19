<?php

use HelgeSverre\Swarm\Task\Task;
use HelgeSverre\Swarm\Task\TaskManager;
use Psr\Log\NullLogger;

test('task history is updated when task is completed', function () {
    $manager = new TaskManager(new NullLogger);

    // Add a task
    $manager->addTasks([['description' => 'Test task']]);

    // Get and plan the task
    $tasks = $manager->getTasks();
    $task = $tasks[0];
    $manager->planTask($task->id, 'Test plan', ['Step 1']);

    // Execute and complete the task
    $nextTask = $manager->getNextTask();
    expect($nextTask)->not->toBeNull();

    $manager->completeCurrentTask();

    // Check history
    $history = $manager->getTaskHistory();
    expect($history)->toHaveCount(1)
        ->and($history[0]['description'])->toBe('Test task')
        ->and($history[0]['status'])->toBe('completed')
        ->and($history[0])->toHaveKey('completed_at')
        ->and($history[0])->toHaveKey('execution_time');
});

test('task history maintains limit of 1000 tasks', function () {
    $manager = new TaskManager(new NullLogger);

    // Add and complete many tasks
    for ($i = 0; $i < 1005; $i++) {
        $manager->addTasks([['description' => "Task {$i}"]]);
        $tasks = $manager->getTasks();
        $task = end($tasks);
        $manager->planTask($task->id, 'Plan', []);
        $manager->getNextTask();
        $manager->completeCurrentTask();
    }

    $history = $manager->getTaskHistory();
    expect($history)->toHaveCount(1000)
        ->and($history[0]['description'])->toBe('Task 5')
        ->and($history[999]['description'])->toBe('Task 1004');
});

test('clearCompletedTasks moves completed tasks to history', function () {
    $manager = new TaskManager(new NullLogger);

    // Add multiple tasks
    $manager->addTasks([
        ['description' => 'Task 1'],
        ['description' => 'Task 2'],
        ['description' => 'Task 3'],
    ]);

    $tasks = $manager->getTasks();

    // Complete first and third tasks
    $manager->planTask($tasks[0]->id, 'Plan 1', []);
    $manager->getNextTask();
    $manager->completeCurrentTask();

    $manager->planTask($tasks[2]->id, 'Plan 3', []);
    $manager->getNextTask();
    $manager->completeCurrentTask();

    // Clear completed tasks
    $manager->clearCompletedTasks();

    // Check active tasks
    $activeTasks = $manager->getTasks();
    expect($activeTasks)->toHaveCount(1)
        ->and($activeTasks[0]->description)->toBe('Task 2');

    // Check history
    $history = $manager->getTaskHistory();
    expect($history)->toHaveCount(2)
        ->and($history[0]['description'])->toBe('Task 1')
        ->and($history[1]['description'])->toBe('Task 3');
});

test('task history can be set and retrieved', function () {
    $manager = new TaskManager(new NullLogger);

    $history = [
        [
            'id' => 'task_1',
            'description' => 'Historical task 1',
            'status' => 'completed',
            'created_at' => time() - 3600,
            'completed_at' => time() - 3000,
            'execution_time' => 600,
        ],
        [
            'id' => 'task_2',
            'description' => 'Historical task 2',
            'status' => 'completed',
            'created_at' => time() - 1800,
            'completed_at' => time() - 1500,
            'execution_time' => 300,
        ],
    ];

    $manager->setTaskHistory($history);

    expect($manager->getTaskHistory())->toBe($history);
});

test('getTasksAsArrays returns tasks in array format', function () {
    $manager = new TaskManager(new NullLogger);

    $manager->addTasks([
        ['description' => 'Task 1'],
        ['description' => 'Task 2'],
    ]);

    $tasksAsArrays = $manager->getTasksAsArrays();

    expect($tasksAsArrays)->toBeArray()
        ->and($tasksAsArrays)->toHaveCount(2)
        ->and($tasksAsArrays[0])->toHaveKeys(['id', 'description', 'status', 'plan', 'steps', 'created_at', 'completed_at'])
        ->and($tasksAsArrays[0]['description'])->toBe('Task 1')
        ->and($tasksAsArrays[1]['description'])->toBe('Task 2');
});
