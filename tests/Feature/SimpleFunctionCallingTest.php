<?php

use HelgeSverre\Swarm\Core\ToolRegistry;
use HelgeSverre\Swarm\Core\ToolRouter;

test('function calling schemas are properly formatted', function () {
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
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    // Test bash tool execution
    $result = $router->dispatch('bash', [
        'command' => 'echo "Function calling works!"',
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['stdout'])->toContain('Function calling works!');

    // Test file operations
    $testFile = sys_get_temp_dir() . '/test_fc.txt';

    // Write file
    $result = $router->dispatch('write_file', [
        'path' => $testFile,
        'content' => 'Testing function calls',
    ]);
    expect($result->isSuccess())->toBeTrue();

    // Read file
    $result = $router->dispatch('read_file', [
        'path' => $testFile,
    ]);
    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content'])->toBe('Testing function calls');

    // Clean up
    unlink($testFile);
});

test('function parameters are validated and defaults applied', function () {
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    // Test write_file with default backup parameter
    $testFile = sys_get_temp_dir() . '/test_defaults.txt';

    $result = $router->dispatch('write_file', [
        'path' => $testFile,
        'content' => 'Test content',
        // backup parameter should default to true
    ]);

    expect($result->isSuccess())->toBeTrue();

    // Clean up
    if (file_exists($testFile)) {
        unlink($testFile);
    }
    if (file_exists($testFile . '.bak')) {
        unlink($testFile . '.bak');
    }
});

test('search tools work with function parameters', function () {
    $router = new ToolRouter;
    ToolRegistry::registerAll($router);

    // Create test files
    $testDir = sys_get_temp_dir() . '/test_search_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test.php', '<?php echo "test";');
    file_put_contents($testDir . '/test.txt', 'test content');

    // Test find_files
    $result = $router->dispatch('find_files', [
        'pattern' => '*.php',
        'directory' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1);

    // Test search_content
    $result = $router->dispatch('search_content', [
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
