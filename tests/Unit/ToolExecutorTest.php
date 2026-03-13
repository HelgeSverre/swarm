<?php

use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Core\ToolResponse;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ToolCompletedEvent;
use HelgeSverre\Swarm\Events\ToolStartedEvent;
use HelgeSverre\Swarm\Exceptions\ToolNotFoundException;

test('can register and dispatch tools', function () {
    $executor = new ToolExecutor;

    $executor->registerTool('test_tool', function ($params) {
        return ToolResponse::success(['result' => 'Hello ' . $params['name']]);
    });

    $response = $executor->dispatch('test_tool', ['name' => 'World']);

    expect($response)->toBeInstanceOf(ToolResponse::class);
    expect($response->getData())->toBe(['result' => 'Hello World']);
});

test('throws exception for unknown tool', function () {
    $executor = new ToolExecutor;

    $executor->dispatch('unknown_tool', []);
})->throws(ToolNotFoundException::class, "Tool 'unknown_tool' not found");

test('logs tool execution', function () {
    $executor = new ToolExecutor;

    $executor->registerTool('logged_tool', function ($params) {
        return ToolResponse::success(['done' => true]);
    });

    $executor->dispatch('logged_tool', ['test' => 'param']);

    $log = $executor->getExecutionLog();

    expect($log)->toHaveCount(2);
    expect($log[0])->toMatchArray([
        'tool' => 'logged_tool',
        'params' => ['test' => 'param'],
        'status' => 'started',
    ]);
    expect($log[1])->toMatchArray([
        'tool' => 'logged_tool',
        'params' => ['test' => 'param'],
        'status' => 'completed',
    ]);
});

test('logs failed tool execution', function () {
    $executor = new ToolExecutor;

    $executor->registerTool('failing_tool', function ($params) {
        throw new Exception('Tool failed');
    });

    try {
        $executor->dispatch('failing_tool', []);
    } catch (Exception $e) {
        // Expected
    }

    $log = $executor->getExecutionLog();

    expect($log)->toHaveCount(2);
    expect($log[0])->toMatchArray([
        'tool' => 'failing_tool',
        'status' => 'started',
    ]);
    expect($log[1])->toMatchArray([
        'tool' => 'failing_tool',
        'status' => 'failed',
        'error' => 'Tool failed',
    ]);
});

test('emits tool lifecycle events on the provided event bus', function () {
    $eventBus = new EventBus;
    $startedEvents = [];
    $completedEvents = [];

    $eventBus->on(ToolStartedEvent::class, function (ToolStartedEvent $event) use (&$startedEvents): void {
        $startedEvents[] = $event;
    });
    $eventBus->on(ToolCompletedEvent::class, function (ToolCompletedEvent $event) use (&$completedEvents): void {
        $completedEvents[] = $event;
    });

    $executor = ToolExecutor::createWithDefaultTools(eventBus: $eventBus);
    $executor->registerTool('evented_tool', function ($params) {
        return ToolResponse::success(['ok' => $params['value']]);
    });

    $executor->dispatch('evented_tool', ['value' => 42]);

    expect($startedEvents)->toHaveCount(1)
        ->and($startedEvents[0]->tool)->toBe('evented_tool')
        ->and($completedEvents)->toHaveCount(1)
        ->and($completedEvents[0]->tool)->toBe('evented_tool')
        ->and($completedEvents[0]->result->getData())->toBe(['ok' => 42]);
});
