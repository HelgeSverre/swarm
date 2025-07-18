<?php

use HelgeSverre\Swarm\Core\ToolRegistry;
use HelgeSverre\Swarm\Core\ToolRouter;
use HelgeSverre\Swarm\Tools\FindFiles;
use HelgeSverre\Swarm\Tools\ReadFile;
use HelgeSverre\Swarm\Tools\Search;
use HelgeSverre\Swarm\Tools\Terminal;
use HelgeSverre\Swarm\Tools\WriteFile;

test('tool schemas are properly formatted for OpenAI function calling', function () {
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    $schemas = $router->getToolSchemas();

    foreach ($schemas as $schema) {
        // Each schema must have required fields for OpenAI
        expect($schema)->toHaveKeys(['name', 'description', 'parameters'])
            ->and($schema['name'])->toBeString()->not->toBeEmpty()
            ->and($schema['description'])->toBeString()->not->toBeEmpty()
            ->and($schema['parameters'])->toBeArray()
            ->and($schema['parameters']['type'])->toBe('object')
            ->and($schema['parameters']['properties'])->toBeArray()
            ->and($schema['parameters']['required'])->toBeArray();

        // Each property should have type and description
        foreach ($schema['parameters']['properties'] as $propName => $propDef) {
            expect($propDef)->toHaveKey('type')
                ->and($propDef['type'])->toBeIn(['string', 'number', 'boolean', 'array', 'object'])
                ->and($propDef)->toHaveKey('description')
                ->and($propDef['description'])->toBeString()->not->toBeEmpty();
        }
    }
});

test('parameter names in schemas match tool execute method expectations', function () {
    // Test ReadFile tool
    $readFile = new ReadFile;
    $schema = $readFile->toOpenAISchema();

    expect($schema['parameters']['properties'])->toHaveKey('path');

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

    expect($schema['parameters']['required'])->toBe(['path', 'content'])
        ->and($schema['parameters']['properties']['backup']['default'])->toBe(true);

    // Test Terminal tool
    $terminal = new Terminal;
    $schema = $terminal->toOpenAISchema();

    expect($schema['parameters']['required'])->toBe(['command'])
        ->and($schema['parameters']['properties']['timeout']['default'])->toBe(30);
});

test('tools can be dispatched with function call parameters', function () {
    $router = new ToolRouter;
    $router->register(new Terminal);

    // Simulate function call parameters from OpenAI
    $functionCallParams = [
        'command' => 'echo "Hello from function call"',
    ];

    $result = $router->dispatch('bash', $functionCallParams);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['stdout'])->toContain('Hello from function call');
});

test('default parameters work correctly when not provided in function call', function () {
    $router = new ToolRouter;
    $router->register(new WriteFile);

    $testFile = sys_get_temp_dir() . '/test_defaults.txt';

    // Call without backup parameter (should use default true)
    $result = $router->dispatch('write_file', [
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
    $router = new ToolRouter;
    $router->register(new FindFiles);
    $router->register(new Search($router));

    $schemas = $router->getToolSchemas();
    $searchSchema = null;
    foreach ($schemas as $schema) {
        if ($schema['name'] === 'search_content') {
            $searchSchema = $schema;
            break;
        }
    }

    expect($searchSchema['parameters']['properties'])->toHaveKeys(['search', 'pattern', 'directory', 'case_sensitive'])
        ->and($searchSchema['parameters']['required'])->toBe(['search']);
});
