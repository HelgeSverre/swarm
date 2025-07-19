<?php

use HelgeSverre\Swarm\Task\Task;
use HelgeSverre\Swarm\Task\TaskStatus;

test('task can be created with description', function () {
    $task = Task::create('Create user model');

    expect($task->description)->toBe('Create user model')
        ->and($task->status)->toBe(TaskStatus::Pending)
        ->and($task->plan)->toBeNull()
        ->and($task->steps)->toBe([])
        ->and($task->id)->toBeString()
        ->and($task->createdAt)->toBeInstanceOf(DateTimeImmutable::class);
});

test('task can be created from array', function () {
    $data = [
        'id' => 'task_123',
        'description' => 'Test task',
        'status' => 'planned',
        'plan' => 'Test plan',
        'steps' => ['Step 1', 'Step 2'],
        'created_at' => time() - 3600,
    ];

    $task = Task::fromArray($data);

    expect($task->id)->toBe('task_123')
        ->and($task->description)->toBe('Test task')
        ->and($task->status)->toBe(TaskStatus::Planned)
        ->and($task->plan)->toBe('Test plan')
        ->and($task->steps)->toBe(['Step 1', 'Step 2'])
        ->and($task->createdAt->getTimestamp())->toBe($data['created_at']);
});

test('task can be converted to array', function () {
    $task = Task::create('Test task');
    $array = $task->toArray();

    expect($array)->toHaveKeys(['id', 'description', 'status', 'plan', 'steps', 'created_at', 'completed_at'])
        ->and($array['description'])->toBe('Test task')
        ->and($array['status'])->toBe('pending')
        ->and($array['plan'])->toBeNull()
        ->and($array['steps'])->toBe([])
        ->and($array['created_at'])->toBeInt()
        ->and($array['completed_at'])->toBeNull();
});

test('task immutability - withPlan creates new instance', function () {
    $original = Task::create('Test task');
    $planned = $original->withPlan('Test plan', ['Step 1', 'Step 2']);

    expect($original)->not->toBe($planned)
        ->and($original->status)->toBe(TaskStatus::Pending)
        ->and($original->plan)->toBeNull()
        ->and($planned->status)->toBe(TaskStatus::Planned)
        ->and($planned->plan)->toBe('Test plan')
        ->and($planned->steps)->toBe(['Step 1', 'Step 2'])
        ->and($planned->id)->toBe($original->id) // ID should remain the same
        ->and($planned->description)->toBe($original->description);
});

test('task immutability - startExecuting creates new instance', function () {
    $planned = Task::create('Test task')->withPlan('Plan', ['Step 1']);
    $executing = $planned->startExecuting();

    expect($planned)->not->toBe($executing)
        ->and($planned->status)->toBe(TaskStatus::Planned)
        ->and($executing->status)->toBe(TaskStatus::Executing)
        ->and($executing->plan)->toBe($planned->plan)
        ->and($executing->steps)->toBe($planned->steps);
});

test('task immutability - complete creates new instance', function () {
    $executing = Task::create('Test task')
        ->withPlan('Plan', ['Step 1'])
        ->startExecuting();
    $completed = $executing->complete();

    expect($executing)->not->toBe($completed)
        ->and($executing->status)->toBe(TaskStatus::Executing)
        ->and($completed->status)->toBe(TaskStatus::Completed);
});

test('task status check methods work correctly', function () {
    $pending = Task::create('Test');
    $planned = $pending->withPlan('Plan', []);
    $executing = $planned->startExecuting();
    $completed = $executing->complete();

    // Pending
    expect($pending->isPending())->toBeTrue()
        ->and($pending->isPlanned())->toBeFalse()
        ->and($pending->isExecuting())->toBeFalse()
        ->and($pending->isCompleted())->toBeFalse();

    // Planned
    expect($planned->isPending())->toBeFalse()
        ->and($planned->isPlanned())->toBeTrue()
        ->and($planned->isExecuting())->toBeFalse()
        ->and($planned->isCompleted())->toBeFalse()
        ->and($planned->canExecute())->toBeTrue();

    // Executing
    expect($executing->isPending())->toBeFalse()
        ->and($executing->isPlanned())->toBeFalse()
        ->and($executing->isExecuting())->toBeTrue()
        ->and($executing->isCompleted())->toBeFalse()
        ->and($executing->canExecute())->toBeFalse();

    // Completed
    expect($completed->isPending())->toBeFalse()
        ->and($completed->isPlanned())->toBeFalse()
        ->and($completed->isExecuting())->toBeFalse()
        ->and($completed->isCompleted())->toBeTrue()
        ->and($completed->canExecute())->toBeFalse();
});

test('task preserves all properties through transitions', function () {
    $task = Task::create('Build feature X');
    $planned = $task->withPlan('Implement in phases', ['Design', 'Code', 'Test']);
    $executing = $planned->startExecuting();
    $completed = $executing->complete();

    // All versions should have the same core properties
    foreach ([$task, $planned, $executing, $completed] as $version) {
        expect($version->id)->toBe($task->id)
            ->and($version->description)->toBe('Build feature X')
            ->and($version->createdAt)->toBe($task->createdAt);
    }

    // Plan and steps should be preserved after planning
    foreach ([$planned, $executing, $completed] as $version) {
        expect($version->plan)->toBe('Implement in phases')
            ->and($version->steps)->toBe(['Design', 'Code', 'Test']);
    }
});

test('completed task has completedAt timestamp', function () {
    $task = Task::create('Test task');

    // Initial task should not have completedAt
    expect($task->completedAt)->toBeNull();

    // Complete the task
    $completed = $task->complete();

    expect($completed->completedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($completed->completedAt->getTimestamp())->toBeGreaterThan(0)
        ->and($completed->completedAt->getTimestamp())->toBeLessThanOrEqual(time());

    // Verify it's in the array format
    $array = $completed->toArray();
    expect($array['completed_at'])->toBeInt()
        ->and($array['completed_at'])->toBe($completed->completedAt->getTimestamp());
});

test('completedAt is preserved through state transitions', function () {
    $completedTime = new DateTimeImmutable('2024-01-01 12:00:00');

    // Create a completed task with specific timestamp
    $task = new Task(
        id: 'test_123',
        description: 'Test',
        status: TaskStatus::Completed,
        plan: 'Plan',
        steps: ['Step 1'],
        createdAt: new DateTimeImmutable('2024-01-01 10:00:00'),
        completedAt: $completedTime
    );

    // Transition to planned (shouldn't happen in real life, but testing preservation)
    $planned = $task->withPlan('New plan', ['New step']);

    expect($planned->completedAt)->toBe($completedTime)
        ->and($planned->completedAt->format('Y-m-d H:i:s'))->toBe('2024-01-01 12:00:00');
});

test('task can be created from array with completedAt', function () {
    $createdTime = time() - 3600;
    $completedTime = time() - 1800;

    $data = [
        'id' => 'task_123',
        'description' => 'Completed task',
        'status' => 'completed',
        'plan' => 'Test plan',
        'steps' => ['Step 1'],
        'created_at' => $createdTime,
        'completed_at' => $completedTime,
    ];

    $task = Task::fromArray($data);

    expect($task->completedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($task->completedAt->getTimestamp())->toBe($completedTime)
        ->and($task->createdAt->getTimestamp())->toBe($createdTime);

    // Verify execution time can be calculated
    $executionTime = $task->completedAt->getTimestamp() - $task->createdAt->getTimestamp();
    expect($executionTime)->toBe(1800); // 30 minutes
});
