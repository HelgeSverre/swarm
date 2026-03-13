<?php

use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Tools\Grep;
use HelgeSverre\Swarm\Tools\ReadFile;
use HelgeSverre\Swarm\Tools\Terminal;
use HelgeSverre\Swarm\Tools\WriteFile;

test('tool schemas are properly formatted for OpenAI function calling', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $schemas = $executor->getToolSchemas();

    foreach ($schemas as $schema) {
        // Each schema must have the function wrapper
        expect($schema)->toHaveKeys(['type', 'function'])
            ->and($schema['type'])->toBe('function')
            ->and($schema['function']['name'])->toBeString()->not->toBeEmpty()
            ->and($schema['function']['description'])->toBeString()->not->toBeEmpty()
            ->and($schema['function']['parameters'])->toBeArray()
            ->and($schema['function']['parameters']['type'])->toBe('object')
            ->and($schema['function']['parameters']['properties'])->toBeArray()
            ->and($schema['function']['parameters']['required'])->toBeArray();

        // Each property should have type and description
        foreach ($schema['function']['parameters']['properties'] as $propName => $propDef) {
            expect($propDef)->toHaveKey('type')
                ->and($propDef['type'])->toBeIn(['string', 'number', 'integer', 'boolean', 'array', 'object'])
                ->and($propDef)->toHaveKey('description')
                ->and($propDef['description'])->toBeString()->not->toBeEmpty();
        }
    }
});

test('parameter names in schemas match tool execute method expectations', function () {
    // Test ReadFile tool
    $readFile = new ReadFile;
    $schema = $readFile->toOpenAISchema();

    expect($schema['function']['parameters']['properties'])->toHaveKey('path');

    // Test that the tool can execute with these parameters
    $testFile = sys_get_temp_dir() . '/test_param_mapping.txt';
    file_put_contents($testFile, 'test content');

    $result = $readFile->execute(['path' => $testFile]);
    expect($result->isSuccess())->toBeTrue();

    unlink($testFile);
});

test('required parameters are correctly defined in schemas', function () {
    // Test WriteFile tool
    $writeFile = new WriteFile;
    $schema = $writeFile->toOpenAISchema();

    expect($schema['function']['parameters']['required'])->toBe(['path', 'content']);

    // Test Terminal tool
    $terminal = new Terminal;
    $schema = $terminal->toOpenAISchema();

    expect($schema['function']['parameters']['required'])->toBe(['command'])
        ->and($schema['function']['parameters']['properties']['timeout']['default'])->toBe(30);
});

test('tools can be dispatched with function call parameters', function () {
    $originalEnv = getenv('TERMINAL_ENABLED');
    putenv('TERMINAL_ENABLED=true');

    $executor = ToolExecutor::createWithDefaultTools();
    $executor->register(new Terminal);

    // Simulate function call parameters from OpenAI
    $functionCallParams = [
        'command' => 'echo "Hello from function call"',
    ];

    $result = $executor->dispatch('bash', $functionCallParams);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['stdout'])->toContain('Hello from function call');

    putenv($originalEnv !== false ? "TERMINAL_ENABLED={$originalEnv}" : 'TERMINAL_ENABLED');
});

test('default parameters work correctly when not provided in function call', function () {
    $executor = ToolExecutor::createWithDefaultTools();
    $executor->register(new WriteFile);

    $testFile = sys_get_temp_dir() . '/test_defaults.txt';

    // Call without backup parameter (should use default true)
    $result = $executor->dispatch('write_file', [
        'path' => $testFile,
        'content' => 'test content',
    ]);

    expect($result->isSuccess())->toBeTrue();

    // Clean up
    if (file_exists($testFile)) {
        unlink($testFile);
    }
});

test('search tool parameter mapping works with router dependency', function () {
    $executor = ToolExecutor::createWithDefaultTools();
    $executor->register(new Grep);

    $schemas = $executor->getToolSchemas();
    $searchFunction = null;
    foreach ($schemas as $schema) {
        if ($schema['function']['name'] === 'grep') {
            $searchFunction = $schema['function'];
            break;
        }
    }

    expect($searchFunction['parameters']['properties'])->toHaveKeys(['pattern', 'path', 'include'])
        ->and($searchFunction['parameters']['required'])->toBe(['pattern']);
});
