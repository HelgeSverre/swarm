<?php

use HelgeSverre\Swarm\Core\ToolRegistry;
use HelgeSverre\Swarm\Core\ToolRouter;

test('correct tools are selected for file operations', function () {
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    // Create test file
    $testFile = sys_get_temp_dir() . '/test_tool_selection.txt';
    file_put_contents($testFile, 'Original content');

    // Test read file scenario
    $result = $router->dispatch('read_file', ['path' => $testFile]);
    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content'])->toBe('Original content');

    // Test write file scenario
    $result = $router->dispatch('write_file', [
        'path' => $testFile,
        'content' => 'Updated content',
        'backup' => false,
    ]);
    expect($result->isSuccess())->toBeTrue();

    // Verify file was updated
    expect(file_get_contents($testFile))->toBe('Updated content');

    // Clean up
    unlink($testFile);
});

test('correct tools are selected for search operations', function () {
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    // Create test directory with files
    $testDir = sys_get_temp_dir() . '/test_search_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test1.php', '<?php // TODO: implement feature');
    file_put_contents($testDir . '/test2.txt', 'Regular text file');
    file_put_contents($testDir . '/test3.php', '<?php // Another PHP file');

    // Test find files scenario
    $result = $router->dispatch('find_files', [
        'pattern' => '*.php',
        'directory' => $testDir,
    ]);
    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(2);

    // Test search content scenario
    $result = $router->dispatch('search_content', [
        'search' => 'TODO',
        'directory' => $testDir,
    ]);
    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1)
        ->and($result->getData()['results'][0]['file'])->toContain('test1.php');

    // Clean up
    unlink($testDir . '/test1.php');
    unlink($testDir . '/test2.txt');
    unlink($testDir . '/test3.php');
    rmdir($testDir);
});

test('terminal tool is selected for system commands', function () {
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    // Test bash command scenario
    $result = $router->dispatch('bash', [
        'command' => 'echo "Hello from terminal"',
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['stdout'])->toContain('Hello from terminal')
        ->and($result->getData()['return_code'])->toBe(0);

    // Test command with specific directory
    $tempDir = sys_get_temp_dir();
    $result = $router->dispatch('bash', [
        'command' => 'pwd',
        'directory' => $tempDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and(trim($result->getData()['stdout']))->toBe(realpath($tempDir));
});

test('tool schemas match expected OpenAI function format', function () {
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    $schemas = $router->getToolSchemas();

    // Verify we have all expected tools
    $toolNames = array_column($schemas, 'name');
    expect($toolNames)->toContain('read_file')
        ->and($toolNames)->toContain('write_file')
        ->and($toolNames)->toContain('bash')
        ->and($toolNames)->toContain('find_files')
        ->and($toolNames)->toContain('search_content');

    // Verify each schema follows OpenAI function format
    foreach ($schemas as $schema) {
        // Check top-level structure
        expect($schema)->toHaveKeys(['name', 'description', 'parameters']);

        // Check parameters structure
        expect($schema['parameters'])->toHaveKeys(['type', 'properties', 'required'])
            ->and($schema['parameters']['type'])->toBe('object');

        // Verify specific tool parameters
        switch ($schema['name']) {
            case 'read_file':
                expect($schema['parameters']['properties'])->toHaveKey('path')
                    ->and($schema['parameters']['required'])->toBe(['path']);
                break;
            case 'write_file':
                expect($schema['parameters']['properties'])->toHaveKeys(['path', 'content', 'backup'])
                    ->and($schema['parameters']['required'])->toBe(['path', 'content']);
                break;
            case 'bash':
                expect($schema['parameters']['properties'])->toHaveKeys(['command', 'timeout', 'directory'])
                    ->and($schema['parameters']['required'])->toBe(['command']);
                break;
            case 'find_files':
                expect($schema['parameters']['properties'])->toHaveKeys(['pattern', 'directory', 'recursive'])
                    ->and($schema['parameters']['required'])->toBe([]);
                break;
            case 'search_content':
                expect($schema['parameters']['properties'])->toHaveKeys(['search', 'pattern', 'directory', 'case_sensitive'])
                    ->and($schema['parameters']['required'])->toBe(['search']);
                break;
        }
    }
});
