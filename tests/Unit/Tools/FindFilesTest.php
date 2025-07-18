<?php

use HelgeSverre\Swarm\Tools\FindFiles;

test('find files tool generates correct schema', function () {
    $tool = new FindFiles;
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('find_files')
        ->and($schema['description'])->toBe('Find files matching a pattern')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKeys(['pattern', 'directory', 'recursive'])
        ->and($schema['parameters']['properties']['pattern']['default'])->toBe('*')
        ->and($schema['parameters']['properties']['directory']['default'])->toBe('.')
        ->and($schema['parameters']['properties']['recursive']['type'])->toBe('boolean')
        ->and($schema['parameters']['properties']['recursive']['default'])->toBeTrue()
        ->and($schema['parameters']['required'])->toBe([]);
});

test('find files tool finds matching files', function () {
    $tool = new FindFiles;

    // Create test directory structure
    $testDir = sys_get_temp_dir() . '/test_find_files_' . uniqid();
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

test('find files tool works non-recursively', function () {
    $tool = new FindFiles;

    // Create test directory structure
    $testDir = sys_get_temp_dir() . '/test_find_files_' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/test1.txt', 'content');
    mkdir($testDir . '/subdir');
    file_put_contents($testDir . '/subdir/test2.txt', 'content');

    // Find txt files non-recursively
    $result = $tool->execute([
        'pattern' => '*.txt',
        'directory' => $testDir,
        'recursive' => false,
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
