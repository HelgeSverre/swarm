<?php

use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Tools\Playwright;

test('playwright tool is registered correctly', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $registeredTools = $executor->getRegisteredTools();

    expect($registeredTools)->toContain('playwright');
});

test('playwright tool schema has proper structure', function () {
    $tool = new Playwright;
    $schema = $tool->toOpenAISchema();

    expect($schema)->toHaveKeys(['name', 'description', 'parameters'])
        ->and($schema['name'])->toBe('playwright')
        ->and($schema['parameters'])->toHaveKeys(['type', 'properties', 'required'])
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['required'])->toBe(['action']);

    // Check action parameter
    expect($schema['parameters']['properties']['action'])
        ->toHaveKeys(['type', 'description', 'enum'])
        ->and($schema['parameters']['properties']['action']['enum'])
        ->toContain('launch', 'navigate', 'screenshot', 'click', 'type', 'evaluate', 'wait_for', 'get_content', 'close');
});

test('playwright tool requires action parameter', function () {
    $tool = new Playwright;

    expect($tool->required())->toBe(['action']);
});

test('playwright tool handles invalid action', function () {
    $tool = new Playwright;

    $result = $tool->execute(['action' => 'invalid_action']);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toContain('Unknown action: invalid_action');
});

test('playwright tool requires url for navigate action', function () {
    $tool = new Playwright;

    $result = $tool->execute([
        'action' => 'navigate',
        'session_id' => 'test',
    ]);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toContain('URL is required');
});

test('playwright tool requires selector for click action', function () {
    $tool = new Playwright;

    $result = $tool->execute([
        'action' => 'click',
        'session_id' => 'test',
    ]);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toContain('Selector is required');
});

test('playwright tool requires selector and text for type action', function () {
    $tool = new Playwright;

    // Test missing selector
    $result = $tool->execute([
        'action' => 'type',
        'text' => 'test',
        'session_id' => 'test',
    ]);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toContain('Selector is required');

    // Test missing text
    $result = $tool->execute([
        'action' => 'type',
        'selector' => '#input',
        'session_id' => 'test',
    ]);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toContain('Text is required');
});

test('playwright tool requires script for evaluate action', function () {
    $tool = new Playwright;

    $result = $tool->execute([
        'action' => 'evaluate',
        'session_id' => 'test',
    ]);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toContain('Script is required');
});

test('playwright tool requires selector for wait_for action', function () {
    $tool = new Playwright;

    $result = $tool->execute([
        'action' => 'wait_for',
        'session_id' => 'test',
    ]);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toContain('Selector is required');
});

test('playwright tool actions require active session', function () {
    $tool = new Playwright;

    $actions = ['navigate', 'screenshot', 'click', 'type', 'evaluate', 'wait_for', 'get_content'];

    foreach ($actions as $action) {
        $params = ['action' => $action, 'session_id' => 'nonexistent'];

        // Add required parameters for each action
        if ($action === 'navigate') {
            $params['url'] = 'https://example.com';
        } elseif (in_array($action, ['click', 'type', 'wait_for'])) {
            $params['selector'] = '#test';
            if ($action === 'type') {
                $params['text'] = 'test';
            }
        } elseif ($action === 'evaluate') {
            $params['script'] = 'return true;';
        }

        $result = $tool->execute($params);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toContain('No browser session found');
    }
});

test('playwright tool close action handles missing session gracefully', function () {
    $tool = new Playwright;

    $result = $tool->execute([
        'action' => 'close',
        'session_id' => 'nonexistent',
    ]);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toContain('No browser session found');
});

test('playwright tool supports multiple browser types', function () {
    $tool = new Playwright;
    $schema = $tool->toOpenAISchema();

    expect($schema['parameters']['properties']['browser'])
        ->toHaveKeys(['type', 'description', 'enum'])
        ->and($schema['parameters']['properties']['browser']['enum'])
        ->toBe(['chromium', 'firefox', 'webkit']);
});

test('playwright tool has configurable timeout parameter', function () {
    $tool = new Playwright;
    $schema = $tool->toOpenAISchema();

    expect($schema['parameters']['properties']['timeout'])
        ->toHaveKeys(['type', 'description'])
        ->and($schema['parameters']['properties']['timeout']['type'])
        ->toBe('number');
});

test('playwright tool supports headless mode parameter', function () {
    $tool = new Playwright;
    $schema = $tool->toOpenAISchema();

    expect($schema['parameters']['properties']['headless'])
        ->toHaveKeys(['type', 'description'])
        ->and($schema['parameters']['properties']['headless']['type'])
        ->toBe('boolean');
});

test('playwright tool supports session management', function () {
    $tool = new Playwright;
    $schema = $tool->toOpenAISchema();

    expect($schema['parameters']['properties']['session_id'])
        ->toHaveKeys(['type', 'description'])
        ->and($schema['parameters']['properties']['session_id']['type'])
        ->toBe('string');
});
