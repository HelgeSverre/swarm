<?php

use HelgeSverre\Swarm\Core\ToolExecutor;

test('function calling schemas are properly formatted', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $schemas = $executor->getToolSchemas();

    // Verify we have all expected tools
    $toolNames = array_column($schemas, 'name');
    expect($toolNames)->toContain('read_file')
        ->and($toolNames)->toContain('write_file')
        ->and($toolNames)->toContain('bash')
        ->and($toolNames)->toContain('grep');

    // Verify bash tool schema is correct
    $bashSchema = null;
    foreach ($schemas as $schema) {
        if ($schema['name'] === 'bash') {
            $bashSchema = $schema;
            break;
        }
    }

    expect($bashSchema)->not->toBeNull()
        ->and($bashSchema['description'])->toContain('Execute bash commands')
        ->and($bashSchema['parameters']['properties']['command'])->toHaveKey('type')
        ->and($bashSchema['parameters']['required'])->toBe(['command']);
});

test('tools execute correctly with function call parameters', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    // Test bash tool execution
    $result = $executor->dispatch('bash', [
        'command' => 'echo "Function calling works!"',
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['stdout'])->toContain('Function calling works!');

    // Test file operations
    $testFile = sys_get_temp_dir() . '/test_fc.txt';

    // Write file
    $result = $executor->dispatch('write_file', [
        'path' => $testFile,
        'content' => 'Testing function calls',
    ]);
    expect($result->isSuccess())->toBeTrue();

    // Read file
    $result = $executor->dispatch('read_file', [
        'path' => $testFile,
    ]);
    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content'])->toBe('Testing function calls');

    // Clean up
    unlink($testFile);
});

test('function parameters are validated and defaults applied', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $testFile = sys_get_temp_dir() . '/test_defaults.txt';

    $result = $executor->dispatch('write_file', [
        'path' => $testFile,
        'content' => 'Test content',
    ]);

    expect($result->isSuccess())->toBeTrue();

    // Clean up
    if (file_exists($testFile)) {
        unlink($testFile);
    }
});

test('search tools work with function parameters', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    // Create test files
    $testDir = sys_get_temp_dir() . '/test_search_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test.php', '<?php echo "test";');
    file_put_contents($testDir . '/test.txt', 'test content');

    // Test grep for finding files
    $result = $executor->dispatch('grep', [
        'pattern' => '*.php',
        'directory' => $testDir,
        'files_only' => true,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1);

    // Test grep for searching content
    $result = $executor->dispatch('grep', [
        'search' => 'echo',
        'directory' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1);

    // Clean up
    unlink($testDir . '/test.php');
    unlink($testDir . '/test.txt');
    rmdir($testDir);
});
