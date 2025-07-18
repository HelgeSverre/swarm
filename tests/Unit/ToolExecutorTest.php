<?php

use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Core\ToolResponse;
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
