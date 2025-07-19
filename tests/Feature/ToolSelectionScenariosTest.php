<?php

use HelgeSverre\Swarm\Core\ToolExecutor;

test('correct tools are selected for file operations', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    // Create test file
    $testFile = sys_get_temp_dir() . '/test_tool_selection.txt';
    file_put_contents($testFile, 'Original content');

    // Test read file scenario
    $result = $executor->dispatch('read_file', ['path' => $testFile]);
    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content'])->toBe('Original content');

    // Test write file scenario
    $result = $executor->dispatch('write_file', [
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
    $executor = ToolExecutor::createWithDefaultTools();

    // Create test directory with files
    $testDir = sys_get_temp_dir() . '/test_search_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test1.php', '<?php // TODO: implement feature');
    file_put_contents($testDir . '/test2.txt', 'Regular text file');
    file_put_contents($testDir . '/test3.php', '<?php // Another PHP file');

    // Test grep for finding files scenario
    $result = $executor->dispatch('grep', [
        'pattern' => '*.php',
        'directory' => $testDir,
        'files_only' => true,
    ]);
    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(2);

    // Test grep for searching content scenario
    $result = $executor->dispatch('grep', [
        'search' => 'TODO',
        'directory' => $testDir,
    ]);
    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1);

    // Clean up
    unlink($testDir . '/test1.php');
    unlink($testDir . '/test2.txt');
    unlink($testDir . '/test3.php');
    rmdir($testDir);
});

test('correct tools are selected for terminal operations', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    // Test bash command scenario
    $result = $executor->dispatch('bash', [
        'command' => 'echo "Hello from terminal"',
    ]);
    expect($result->isSuccess())->toBeTrue()
        ->and(mb_trim($result->getData()['stdout']))->toBe('Hello from terminal');
});

test('all expected tools are registered', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $registeredTools = $executor->getRegisteredTools();

    expect($registeredTools)
        ->toContain('read_file')
        ->toContain('write_file')
        ->toContain('bash')
        ->toContain('grep');

    // Verify schemas are properly formatted
    $schemas = $executor->getToolSchemas();
    $toolNames = array_column($schemas, 'name');

    expect($toolNames)
        ->toContain('read_file')
        ->toContain('write_file')
        ->toContain('bash')
        ->toContain('grep');
});

test('tools handle edge cases correctly', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    // Test non-existent file
    $result = $executor->dispatch('read_file', [
        'path' => '/non/existent/file.txt',
    ]);
    expect($result->isSuccess())->toBeFalse();

    // Test grep with non-existent directory
    $result = $executor->dispatch('grep', [
        'search' => 'test',
        'directory' => '/non/existent/directory',
    ]);
    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(0);
});

test('tool schemas contain required fields', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $schemas = $executor->getToolSchemas();

    foreach ($schemas as $schema) {
        expect($schema)
            ->toHaveKeys(['name', 'description', 'parameters'])
            ->and($schema['parameters'])
            ->toHaveKeys(['type', 'properties', 'required']);

        switch ($schema['name']) {
            case 'read_file':
                expect($schema['parameters']['properties'])
                    ->toHaveKey('path');
                break;
            case 'write_file':
                expect($schema['parameters']['properties'])
                    ->toHaveKeys(['path', 'content']);
                break;
            case 'bash':
                expect($schema['parameters']['properties'])
                    ->toHaveKey('command');
                break;
            case 'grep':
                expect($schema['parameters']['properties'])
                    ->toHaveKeys(['search', 'pattern', 'directory']);
                break;
        }
    }
});
