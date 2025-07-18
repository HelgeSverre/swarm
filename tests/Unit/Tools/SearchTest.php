<?php

use HelgeSverre\Swarm\Core\ToolRouter;
use HelgeSverre\Swarm\Tools\FindFiles;
use HelgeSverre\Swarm\Tools\Search;

test('search tool generates correct schema', function () {
    $router = new ToolRouter;
    $tool = new Search($router);
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('search_content')
        ->and($schema['description'])->toBe('Search for content in files')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKeys(['search', 'pattern', 'directory', 'case_sensitive'])
        ->and($schema['parameters']['properties']['search']['type'])->toBe('string')
        ->and($schema['parameters']['properties']['pattern']['default'])->toBe('*')
        ->and($schema['parameters']['properties']['case_sensitive']['type'])->toBe('boolean')
        ->and($schema['parameters']['properties']['case_sensitive']['default'])->toBeFalse()
        ->and($schema['parameters']['required'])->toBe(['search']);
});

test('search tool finds content in files', function () {
    $router = new ToolRouter;
    $router->register(new FindFiles);
    $tool = new Search($router);

    // Create test files
    $testDir = sys_get_temp_dir() . '/test_search_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test1.txt', "Hello World\nThis is a test\nAnother line");
    file_put_contents($testDir . '/test2.txt', "Different content\nNo match here");

    // Search for content
    $result = $tool->execute([
        'search' => 'test',
        'pattern' => '*.txt',
        'directory' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1)
        ->and($result->getData()['results'])->toHaveCount(1)
        ->and($result->getData()['results'][0]['line'])->toBe(2)
        ->and($result->getData()['results'][0]['content'])->toBe('This is a test');

    // Clean up
    unlink($testDir . '/test1.txt');
    unlink($testDir . '/test2.txt');
    rmdir($testDir);
});

test('search tool handles case sensitivity', function () {
    $router = new ToolRouter;
    $router->register(new FindFiles);
    $tool = new Search($router);

    // Create test file
    $testDir = sys_get_temp_dir() . '/test_search_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test.txt', "HELLO\nhello\nHeLLo");

    // Case insensitive search
    $result = $tool->execute([
        'search' => 'hello',
        'directory' => $testDir,
        'case_sensitive' => false,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(3);

    // Case sensitive search
    $result = $tool->execute([
        'search' => 'hello',
        'directory' => $testDir,
        'case_sensitive' => true,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1);

    // Clean up
    unlink($testDir . '/test.txt');
    rmdir($testDir);
});
