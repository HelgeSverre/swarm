<?php

use HelgeSverre\Swarm\Tools\Grep;

test('grep tool generates correct schema', function () {
    $tool = new Grep;
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('grep')
        ->and($schema['description'])->toContain('Fast content search tool')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKeys(['pattern', 'path', 'include'])
        ->and($schema['parameters']['required'])->toBe(['pattern']);
});

test('grep tool searches content and returns matching files', function () {
    $tool = new Grep;

    // Create test directory structure
    $testDir = sys_get_temp_dir() . '/test_grep_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test1.txt', 'Hello World\nThis is a test');
    file_put_contents($testDir . '/test2.php', '<?php echo "Hello";');
    file_put_contents($testDir . '/test3.txt', 'No match here');
    mkdir($testDir . '/subdir');
    file_put_contents($testDir . '/subdir/test4.txt', 'Another Hello file');

    // Search for "Hello" pattern
    $result = $tool->execute([
        'pattern' => 'Hello',
        'path' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(3)
        ->and($result->getData()['files'])->toHaveCount(3)
        ->and($result->getData()['method'])->toBeIn(['php', 'ripgrep']);

    // Files should be sorted by modification time
    $files = $result->getData()['files'];
    $filesBasenames = array_map('basename', $files);
    expect($filesBasenames)->toContain('test1.txt')
        ->and($filesBasenames)->toContain('test2.php')
        ->and($filesBasenames)->toContain('test4.txt')
        ->and($filesBasenames)->not->toContain('test3.txt');

    // Clean up
    unlink($testDir . '/test1.txt');
    unlink($testDir . '/test2.php');
    unlink($testDir . '/test3.txt');
    unlink($testDir . '/subdir/test4.txt');
    rmdir($testDir . '/subdir');
    rmdir($testDir);
});

test('grep tool filters files with include parameter', function () {
    $tool = new Grep;

    // Create test directory structure
    $testDir = sys_get_temp_dir() . '/test_grep_include_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test1.txt', 'Hello World');
    file_put_contents($testDir . '/test2.php', '<?php echo "Hello";');
    file_put_contents($testDir . '/test3.js', 'console.log("Hello");');

    // Search only in .php files
    $result = $tool->execute([
        'pattern' => 'Hello',
        'path' => $testDir,
        'include' => '*.php',
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1)
        ->and($result->getData()['files'])->toHaveCount(1)
        ->and($result->getData()['files'][0])->toContain('test2.php');

    // Clean up
    unlink($testDir . '/test1.txt');
    unlink($testDir . '/test2.php');
    unlink($testDir . '/test3.js');
    rmdir($testDir);
});

test('grep tool supports regex patterns', function () {
    $tool = new Grep;

    // Create test file
    $testDir = sys_get_temp_dir() . '/test_grep_regex_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test.txt', "function myFunc()\nvar myVar = 1\nconst myConst = 2");

    // Search for function declarations with regex
    $result = $tool->execute([
        'pattern' => 'function\\s+\\w+',
        'path' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1)
        ->and($result->getData()['files'])->toHaveCount(1);

    // Clean up
    unlink($testDir . '/test.txt');
    rmdir($testDir);
});

test('grep tool validates dangerous regex patterns', function () {
    $tool = new Grep;

    // Test dangerous ReDoS pattern
    $result = $tool->execute([
        'pattern' => '(.*)+',
        'path' => sys_get_temp_dir(),
    ]);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Invalid or potentially dangerous regex pattern');
});

test('grep tool validates paths', function () {
    $tool = new Grep;

    // Test with non-existent path
    $result = $tool->execute([
        'pattern' => 'test',
        'path' => '/non/existent/path',
    ]);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Invalid or inaccessible path');
});

test('grep tool respects max file size limit', function () {
    $tool = new Grep(maxFileSize: 100, preferNativeRipgrep: false); // Very small limit for testing, force PHP mode

    // Create test file that's too large
    $testDir = sys_get_temp_dir() . '/test_grep_size_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/large.txt', str_repeat('Hello World ', 50)); // > 100 bytes
    file_put_contents($testDir . '/small.txt', 'Hello'); // < 100 bytes

    $result = $tool->execute([
        'pattern' => 'Hello',
        'path' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1)
        ->and($result->getData()['files'][0])->toContain('small.txt');

    // Clean up
    unlink($testDir . '/large.txt');
    unlink($testDir . '/small.txt');
    rmdir($testDir);
});
