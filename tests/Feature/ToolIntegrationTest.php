<?php

use HelgeSverre\Swarm\Core\ToolExecutor;

test('toolchain registers all tools correctly', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $registeredTools = $executor->getRegisteredTools();

    expect($registeredTools)->toContain('read_file')
        ->and($registeredTools)->toContain('write_file')
        ->and($registeredTools)->toContain('bash')
        ->and($registeredTools)->toContain('grep');
});

test('tool schemas are generated dynamically for all tools', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $schemas = $executor->getToolSchemas();

    expect($schemas)->toBeArray()
        ->and($schemas)->toHaveCount(5);

    $toolNames = array_column($schemas, 'name');
    expect($toolNames)->toContain('read_file')
        ->and($toolNames)->toContain('write_file')
        ->and($toolNames)->toContain('bash')
        ->and($toolNames)->toContain('grep')
        ->and($toolNames)->toContain('web_fetch');
});

test('tool schemas have proper structure for OpenAI', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $schemas = $executor->getToolSchemas();

    foreach ($schemas as $schema) {
        expect($schema)->toHaveKeys(['name', 'description', 'parameters'])
            ->and($schema['parameters'])->toHaveKeys(['type', 'properties', 'required'])
            ->and($schema['parameters']['type'])->toBe('object');

        // Check each property has required fields
        foreach ($schema['parameters']['properties'] as $prop) {
            expect($prop)->toHaveKey('type')
                ->and($prop)->toHaveKey('description');
        }
    }
});

test('tools can be executed through executor', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    // Create a test file
    $testFile = sys_get_temp_dir() . '/test_integration.txt';
    file_put_contents($testFile, 'Test content');

    // Test read file
    $result = $executor->dispatch('read_file', ['path' => $testFile]);
    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content'])->toBe('Test content');

    // Clean up
    unlink($testFile);
});

test('tool executor logs execution history', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    // Execute a command
    $executor->dispatch('bash', ['command' => 'echo test']);

    $log = $executor->getExecutionLog();
    expect($log)->toHaveCount(2) // start and complete entries
        ->and($log[0]['tool'])->toBe('bash')
        ->and($log[0]['status'])->toBe('started')
        ->and($log[1]['status'])->toBe('completed');
});
