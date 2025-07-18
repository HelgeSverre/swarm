<?php

use HelgeSverre\Swarm\Tools\Grep;

test('grep tool generates correct schema', function () {
    $tool = new Grep;
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('grep')
        ->and($schema['description'])->toBe('Search for content in files, optionally filtering by filename pattern')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKeys(['search', 'pattern', 'directory', 'case_sensitive', 'files_only', 'recursive'])
        ->and($schema['parameters']['properties']['pattern']['default'])->toBe('*')
        ->and($schema['parameters']['properties']['directory']['default'])->toBe('.')
        ->and($schema['parameters']['properties']['recursive']['type'])->toBe('boolean')
        ->and($schema['parameters']['properties']['recursive']['default'])->toBeTrue()
        ->and($schema['parameters']['required'])->toBe([]);
});

test('grep tool finds files by pattern when files_only is true', function () {
    $tool = new Grep;

    // Create test directory structure
    $testDir = sys_get_temp_dir() . '/test_grep_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test1.txt', 'content');
    file_put_contents($testDir . '/test2.php', 'content');
    mkdir($testDir . '/subdir');
    file_put_contents($testDir . '/subdir/test3.txt', 'content');

    // Find all txt files
    $result = $tool->execute([
        'pattern' => '*.txt',
        'directory' => $testDir,
        'recursive' => true,
        'files_only' => true,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(2)
        ->and($result->getData()['files'])->toHaveCount(2);

    // Clean up
    unlink($testDir . '/test1.txt');
    unlink($testDir . '/test2.php');
    unlink($testDir . '/subdir/test3.txt');
    rmdir($testDir . '/subdir');
    rmdir($testDir);
});

test('grep tool searches content in files', function () {
    $tool = new Grep;

    // Create test directory structure
    $testDir = sys_get_temp_dir() . '/test_grep_content_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test1.txt', "Hello World\nThis is a test");
    file_put_contents($testDir . '/test2.txt', "Another file\nWith Hello in it");
    file_put_contents($testDir . '/test3.php', "<?php\necho 'Hello';\n");

    // Search for "Hello" in all files
    $result = $tool->execute([
        'search' => 'Hello',
        'pattern' => '*',
        'directory' => $testDir,
        'recursive' => true,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(3)
        ->and($result->getData()['results'])->toHaveCount(3);

    // Verify each result has required fields
    foreach ($result->getData()['results'] as $match) {
        expect($match)->toHaveKeys(['file', 'line', 'content', 'match']);
    }

    // Clean up
    unlink($testDir . '/test1.txt');
    unlink($testDir . '/test2.txt');
    unlink($testDir . '/test3.php');
    rmdir($testDir);
});

test('grep tool handles case sensitivity', function () {
    $tool = new Grep;

    // Create test file
    $testDir = sys_get_temp_dir() . '/test_grep_case_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test.txt', "Hello\nhello\nHELLO");

    // Case sensitive search
    $result = $tool->execute([
        'search' => 'hello',
        'directory' => $testDir,
        'case_sensitive' => true,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1);

    // Case insensitive search
    $result = $tool->execute([
        'search' => 'hello',
        'directory' => $testDir,
        'case_sensitive' => false,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(3);

    // Clean up
    unlink($testDir . '/test.txt');
    rmdir($testDir);
});

test('grep tool works non-recursively', function () {
    $tool = new Grep;

    // Create test directory structure
    $testDir = sys_get_temp_dir() . '/test_grep_recursive_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test1.txt', 'content');
    mkdir($testDir . '/subdir');
    file_put_contents($testDir . '/subdir/test2.txt', 'content');

    // Find txt files non-recursively
    $result = $tool->execute([
        'pattern' => '*.txt',
        'directory' => $testDir,
        'recursive' => false,
        'files_only' => true,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1)
        ->and($result->getData()['files'])->toHaveCount(1);

    // Clean up
    unlink($testDir . '/test1.txt');
    unlink($testDir . '/subdir/test2.txt');
    rmdir($testDir . '/subdir');
    rmdir($testDir);
});
