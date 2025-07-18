<?php

test('read file tool generates correct schema', function () {
    $tool = new HelgeSverre\Swarm\Tools\ReadFile;
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('read_file')
        ->and($schema['description'])->toBe('Read contents of a file from the filesystem')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKey('path')
        ->and($schema['parameters']['properties']['path']['type'])->toBe('string')
        ->and($schema['parameters']['properties']['path']['description'])->toBe('Path to the file to read')
        ->and($schema['parameters']['required'])->toBe(['path']);
});

test('write file tool generates correct schema', function () {
    $tool = new HelgeSverre\Swarm\Tools\WriteFile;
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('write_file')
        ->and($schema['description'])->toBe('Write content to a file')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKeys(['path', 'content', 'backup'])
        ->and($schema['parameters']['properties']['path']['type'])->toBe('string')
        ->and($schema['parameters']['properties']['content']['type'])->toBe('string')
        ->and($schema['parameters']['properties']['backup']['type'])->toBe('boolean')
        ->and($schema['parameters']['properties']['backup']['default'])->toBe(true)
        ->and($schema['parameters']['required'])->toBe(['path', 'content']);
});

test('tool router collects schemas from registered tools', function () {
    $router = new HelgeSverre\Swarm\Core\ToolRouter;

    $router->register(new HelgeSverre\Swarm\Tools\ReadFile);
    $router->register(new HelgeSverre\Swarm\Tools\WriteFile);

    $schemas = $router->getToolSchemas();

    expect($schemas)->toBeArray()
        ->and($schemas)->toHaveCount(2)
        ->and($schemas[0]['name'])->toBe('read_file')
        ->and($schemas[1]['name'])->toBe('write_file');
});

test('read file tool executes correctly', function () {
    $tool = new HelgeSverre\Swarm\Tools\ReadFile;

    // Create a test file
    $testFile = '/tmp/test_file.txt';
    file_put_contents($testFile, "Test content\nLine 2");

    $result = $tool->execute(['path' => $testFile]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content'])->toBe("Test content\nLine 2")
        ->and($result->getData()['lines'])->toBe(2);

    // Clean up
    unlink($testFile);
});

test('read file tool returns error for non-existent file', function () {
    $tool = new HelgeSverre\Swarm\Tools\ReadFile;

    $result = $tool->execute(['path' => '/tmp/non_existent_file.txt']);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->getError())->toContain('File not found');
});

test('write file tool executes correctly', function () {
    $tool = new HelgeSverre\Swarm\Tools\WriteFile;

    $testFile = '/tmp/test_write_file.txt';
    $content = 'New content';

    $result = $tool->execute([
        'path' => $testFile,
        'content' => $content,
        'backup' => false,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['bytes_written'])->toBe(mb_strlen($content))
        ->and(file_exists($testFile))->toBeTrue()
        ->and(file_get_contents($testFile))->toBe($content);

    // Clean up
    unlink($testFile);
});
