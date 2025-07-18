<?php

use HelgeSverre\Swarm\Exceptions\ToolNotFoundException;
use HelgeSverre\Swarm\Router\ToolResponse;
use HelgeSverre\Swarm\Router\ToolRouter;

test('can register and dispatch tools', function () {
    $router = new ToolRouter;

    $router->registerTool('test_tool', function ($params) {
        return ToolResponse::success(['result' => 'Hello ' . $params['name']]);
    });

    $response = $router->dispatch('test_tool', ['name' => 'World']);

    expect($response)->toBeInstanceOf(ToolResponse::class);
    expect($response->getData())->toBe(['result' => 'Hello World']);
});

test('throws exception for unknown tool', function () {
    $router = new ToolRouter;

    $router->dispatch('unknown_tool', []);
})->throws(ToolNotFoundException::class, "Tool 'unknown_tool' not found");

test('logs tool execution', function () {
    $router = new ToolRouter;

    $router->registerTool('logged_tool', function ($params) {
        return ToolResponse::success(['done' => true]);
    });

    $router->dispatch('logged_tool', ['test' => 'param']);

    $log = $router->getExecutionLog();

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
    $router = new ToolRouter;

    $router->registerTool('failing_tool', function ($params) {
        throw new Exception('Tool failed');
    });

    try {
        $router->dispatch('failing_tool', []);
    } catch (Exception $e) {
        // Expected
    }

    $log = $router->getExecutionLog();

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
