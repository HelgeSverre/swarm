<?php

use HelgeSverre\Swarm\Agent\AgentProgressReporter;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ProcessingEvent;

test('agent progress reporter emits processing events on the provided event bus', function () {
    $eventBus = new EventBus;
    $reporter = new AgentProgressReporter($eventBus);
    $received = [];

    $eventBus->on(ProcessingEvent::class, function (ProcessingEvent $event) use (&$received): void {
        $received[] = $event;
    });

    $reporter->report('quick_assessment', ['message' => 'Checking request']);

    expect($received)->toHaveCount(1)
        ->and($received[0]->operation)->toBe('quick_assessment')
        ->and($received[0]->details['message'])->toBe('Checking request');
});

test('agent progress reporter forwards progress to a user callback until it is cleared', function () {
    $reporter = new AgentProgressReporter(new EventBus);
    $received = [];

    $reporter->setCallback(function (string $operation, array $details) use (&$received): void {
        $received[] = [$operation, $details];
    });

    $reporter->report('classifying', ['phase' => 'starting']);
    $reporter->setCallback(null);
    $reporter->report('classifying', ['phase' => 'complete']);

    expect($received)->toBe([
        ['classifying', ['phase' => 'starting']],
    ]);
});
