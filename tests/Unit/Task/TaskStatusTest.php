<?php

use HelgeSverre\Swarm\Task\TaskStatus;

test('task status enum has correct values', function () {
    expect(TaskStatus::Pending->value)->toBe('pending')
        ->and(TaskStatus::Planned->value)->toBe('planned')
        ->and(TaskStatus::Executing->value)->toBe('executing')
        ->and(TaskStatus::Completed->value)->toBe('completed');
});

test('task status values returns all status strings', function () {
    $values = TaskStatus::values();

    expect($values)->toBe(['pending', 'planned', 'executing', 'completed']);
});

test('task status can be created from string', function () {
    expect(TaskStatus::from('pending'))->toBe(TaskStatus::Pending)
        ->and(TaskStatus::from('planned'))->toBe(TaskStatus::Planned)
        ->and(TaskStatus::from('executing'))->toBe(TaskStatus::Executing)
        ->and(TaskStatus::from('completed'))->toBe(TaskStatus::Completed);
});

test('task status transition rules are enforced', function () {
    // Pending can only transition to Planned
    expect(TaskStatus::Pending->canTransitionTo(TaskStatus::Planned))->toBeTrue()
        ->and(TaskStatus::Pending->canTransitionTo(TaskStatus::Executing))->toBeFalse()
        ->and(TaskStatus::Pending->canTransitionTo(TaskStatus::Completed))->toBeFalse();

    // Planned can only transition to Executing
    expect(TaskStatus::Planned->canTransitionTo(TaskStatus::Executing))->toBeTrue()
        ->and(TaskStatus::Planned->canTransitionTo(TaskStatus::Completed))->toBeFalse()
        ->and(TaskStatus::Planned->canTransitionTo(TaskStatus::Pending))->toBeFalse();

    // Executing can only transition to Completed
    expect(TaskStatus::Executing->canTransitionTo(TaskStatus::Completed))->toBeTrue()
        ->and(TaskStatus::Executing->canTransitionTo(TaskStatus::Pending))->toBeFalse()
        ->and(TaskStatus::Executing->canTransitionTo(TaskStatus::Planned))->toBeFalse();

    // Completed cannot transition to anything
    expect(TaskStatus::Completed->canTransitionTo(TaskStatus::Pending))->toBeFalse()
        ->and(TaskStatus::Completed->canTransitionTo(TaskStatus::Planned))->toBeFalse()
        ->and(TaskStatus::Completed->canTransitionTo(TaskStatus::Executing))->toBeFalse();
});

test('task status next status returns correct progression', function () {
    expect(TaskStatus::Pending->nextStatus())->toBe(TaskStatus::Planned)
        ->and(TaskStatus::Planned->nextStatus())->toBe(TaskStatus::Executing)
        ->and(TaskStatus::Executing->nextStatus())->toBe(TaskStatus::Completed)
        ->and(TaskStatus::Completed->nextStatus())->toBeNull();
});

test('task status terminal check works correctly', function () {
    expect(TaskStatus::Pending->isTerminal())->toBeFalse()
        ->and(TaskStatus::Planned->isTerminal())->toBeFalse()
        ->and(TaskStatus::Executing->isTerminal())->toBeFalse()
        ->and(TaskStatus::Completed->isTerminal())->toBeTrue();
});

test('task status labels are human readable', function () {
    expect(TaskStatus::Pending->label())->toBe('Pending')
        ->and(TaskStatus::Planned->label())->toBe('Planned')
        ->and(TaskStatus::Executing->label())->toBe('Executing')
        ->and(TaskStatus::Completed->label())->toBe('Completed');
});

test('task status emojis are appropriate', function () {
    expect(TaskStatus::Pending->emoji())->toBe('⏳')
        ->and(TaskStatus::Planned->emoji())->toBe('📋')
        ->and(TaskStatus::Executing->emoji())->toBe('🔄')
        ->and(TaskStatus::Completed->emoji())->toBe('✅');
});
