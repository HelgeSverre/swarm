<?php

use HelgeSverre\Swarm\Tools\Glob;

test('glob tool generates correct schema', function () {
    $tool = new Glob;
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('glob')
        ->and($schema['description'])->toContain('Fast file pattern matching tool')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKeys(['pattern', 'path'])
        ->and($schema['parameters']['required'])->toBe(['pattern']);
});

test('glob tool finds files by simple pattern', function () {
    $tool = new Glob;

    // Create test directory structure
    $testDir = sys_get_temp_dir() . '/test_glob_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test1.txt', 'content');
    file_put_contents($testDir . '/test2.php', 'content');
    file_put_contents($testDir . '/README.md', 'content');
    mkdir($testDir . '/subdir');
    file_put_contents($testDir . '/subdir/test3.txt', 'content');

    // Find all txt files with simple pattern
    $result = $tool->execute([
        'pattern' => '*.txt',
        'path' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(2)
        ->and($result->getData()['files'])->toHaveCount(2);

    // Files should be sorted by modification time
    $files = $result->getData()['files'];
    $filesBasenames = array_map('basename', $files);
    expect($filesBasenames)->toContain('test1.txt')
        ->and($filesBasenames)->toContain('test3.txt')
        ->and($filesBasenames)->not->toContain('test2.php')
        ->and($filesBasenames)->not->toContain('README.md');

    // Clean up
    unlink($testDir . '/test1.txt');
    unlink($testDir . '/test2.php');
    unlink($testDir . '/README.md');
    unlink($testDir . '/subdir/test3.txt');
    rmdir($testDir . '/subdir');
    rmdir($testDir);
});

test('glob tool finds files with recursive pattern', function () {
    $tool = new Glob;

    // Create test directory structure
    $testDir = sys_get_temp_dir() . '/test_glob_recursive_' . uniqid();
    mkdir($testDir);
    mkdir($testDir . '/src');
    mkdir($testDir . '/src/components');
    file_put_contents($testDir . '/app.js', 'content');
    file_put_contents($testDir . '/src/index.js', 'content');
    file_put_contents($testDir . '/src/components/Button.js', 'content');
    file_put_contents($testDir . '/src/style.css', 'content');

    // Find all JS files recursively
    $result = $tool->execute([
        'pattern' => '**/*.js',
        'path' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(3)
        ->and($result->getData()['files'])->toHaveCount(3);

    $files = $result->getData()['files'];
    $filesBasenames = array_map('basename', $files);
    expect($filesBasenames)->toContain('app.js')
        ->and($filesBasenames)->toContain('index.js')
        ->and($filesBasenames)->toContain('Button.js')
        ->and($filesBasenames)->not->toContain('style.css');

    // Clean up
    unlink($testDir . '/app.js');
    unlink($testDir . '/src/index.js');
    unlink($testDir . '/src/components/Button.js');
    unlink($testDir . '/src/style.css');
    rmdir($testDir . '/src/components');
    rmdir($testDir . '/src');
    rmdir($testDir);
});

test('glob tool supports complex patterns', function () {
    $tool = new Glob;

    // Create test directory structure
    $testDir = sys_get_temp_dir() . '/test_glob_complex_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/component.tsx', 'content');
    file_put_contents($testDir . '/utils.ts', 'content');
    file_put_contents($testDir . '/test.js', 'content');
    file_put_contents($testDir . '/style.css', 'content');

    // Find TypeScript and TSX files
    $result = $tool->execute([
        'pattern' => '*.{ts,tsx}',
        'path' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(2)
        ->and($result->getData()['files'])->toHaveCount(2);

    $files = $result->getData()['files'];
    $filesBasenames = array_map('basename', $files);
    expect($filesBasenames)->toContain('component.tsx')
        ->and($filesBasenames)->toContain('utils.ts')
        ->and($filesBasenames)->not->toContain('test.js')
        ->and($filesBasenames)->not->toContain('style.css');

    // Clean up
    unlink($testDir . '/component.tsx');
    unlink($testDir . '/utils.ts');
    unlink($testDir . '/test.js');
    unlink($testDir . '/style.css');
    rmdir($testDir);
});

test('glob tool excludes common directories', function () {
    $tool = new Glob;

    // Create test directory structure with excluded dirs
    $testDir = sys_get_temp_dir() . '/test_glob_exclude_' . uniqid();
    mkdir($testDir);
    mkdir($testDir . '/.git');
    mkdir($testDir . '/node_modules');
    mkdir($testDir . '/vendor');
    file_put_contents($testDir . '/app.js', 'content');
    file_put_contents($testDir . '/.git/config', 'content');
    file_put_contents($testDir . '/node_modules/lib.js', 'content');
    file_put_contents($testDir . '/vendor/package.php', 'content');

    // Find all files - should exclude common directories
    $result = $tool->execute([
        'pattern' => '*',
        'path' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(1)
        ->and($result->getData()['files'])->toHaveCount(1);

    $files = $result->getData()['files'];
    $filesBasenames = array_map('basename', $files);
    expect($filesBasenames)->toContain('app.js')
        ->and($filesBasenames)->not->toContain('config')
        ->and($filesBasenames)->not->toContain('lib.js')
        ->and($filesBasenames)->not->toContain('package.php');

    // Clean up
    unlink($testDir . '/app.js');
    unlink($testDir . '/.git/config');
    unlink($testDir . '/node_modules/lib.js');
    unlink($testDir . '/vendor/package.php');
    rmdir($testDir . '/.git');
    rmdir($testDir . '/node_modules');
    rmdir($testDir . '/vendor');
    rmdir($testDir);
});

test('glob tool validates dangerous patterns', function () {
    $tool = new Glob;

    // Test pattern with path traversal
    $result = $tool->execute([
        'pattern' => '../etc/passwd',
        'path' => sys_get_temp_dir(),
    ]);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Invalid glob pattern');
});

test('glob tool validates paths', function () {
    $tool = new Glob;

    // Test with non-existent path
    $result = $tool->execute([
        'pattern' => '*.txt',
        'path' => '/non/existent/path',
    ]);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Invalid or inaccessible path');
});

test('glob tool respects max results limit', function () {
    $tool = new Glob(maxResults: 2); // Very small limit for testing

    // Create many test files
    $testDir = sys_get_temp_dir() . '/test_glob_limit_' . uniqid();
    mkdir($testDir);
    for ($i = 1; $i <= 5; $i++) {
        file_put_contents($testDir . "/file{$i}.txt", 'content');
    }

    $result = $tool->execute([
        'pattern' => '*.txt',
        'path' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(2)
        ->and($result->getData()['truncated'])->toBeTrue();

    // Clean up
    for ($i = 1; $i <= 5; $i++) {
        unlink($testDir . "/file{$i}.txt");
    }
    rmdir($testDir);
});

test('glob tool sorts files by modification time', function () {
    $tool = new Glob;

    // Create test files with different timestamps
    $testDir = sys_get_temp_dir() . '/test_glob_sort_' . uniqid();
    mkdir($testDir);

    file_put_contents($testDir . '/old.txt', 'content');
    sleep(1); // Ensure different timestamps
    file_put_contents($testDir . '/new.txt', 'content');

    $result = $tool->execute([
        'pattern' => '*.txt',
        'path' => $testDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['count'])->toBe(2);

    // Newer file should come first (sorted by mtime desc)
    $files = $result->getData()['files'];
    expect($files[0])->toContain('new.txt')
        ->and($files[1])->toContain('old.txt');

    // Clean up
    unlink($testDir . '/old.txt');
    unlink($testDir . '/new.txt');
    rmdir($testDir);
});
