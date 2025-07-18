<?php

use HelgeSverre\Swarm\Core\ToolRegistry;
use HelgeSverre\Swarm\Core\ToolRouter;

test('tool registry registers all tools correctly', function () {
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    $registeredTools = $router->getRegisteredTools();

    expect($registeredTools)->toContain('read_file')
        ->and($registeredTools)->toContain('write_file')
        ->and($registeredTools)->toContain('bash')
        ->and($registeredTools)->toContain('find_files')
        ->and($registeredTools)->toContain('search_content');
});

test('tool schemas are generated dynamically for all tools', function () {
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    $schemas = $router->getToolSchemas();

    expect($schemas)->toBeArray()
        ->and($schemas)->toHaveCount(5);

    $toolNames = array_column($schemas, 'name');
    expect($toolNames)->toContain('read_file')
        ->and($toolNames)->toContain('write_file')
        ->and($toolNames)->toContain('bash')
        ->and($toolNames)->toContain('find_files')
        ->and($toolNames)->toContain('search_content');
});

test('tool schemas have proper format for OpenAI', function () {
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    $schemas = $router->getToolSchemas();

    // Verify all tools have proper schemas
    foreach ($schemas as $schema) {
        expect($schema)->toHaveKeys(['name', 'description', 'parameters'])
            ->and($schema['parameters'])->toHaveKeys(['type', 'properties', 'required'])
            ->and($schema['parameters']['type'])->toBe('object')
            ->and($schema['parameters']['properties'])->toBeArray()
            ->and($schema['parameters']['required'])->toBeArray();
    }
});
