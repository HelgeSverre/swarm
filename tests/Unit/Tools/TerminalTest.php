<?php

use HelgeSverre\Swarm\Tools\Terminal;

test('terminal tool generates correct schema', function () {
    $tool = new Terminal;
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('bash')
        ->and($schema['description'])->toBe('Execute bash commands in a terminal')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKeys(['command', 'timeout', 'directory'])
        ->and($schema['parameters']['properties']['command']['type'])->toBe('string')
        ->and($schema['parameters']['properties']['timeout']['type'])->toBe('number')
        ->and($schema['parameters']['properties']['timeout']['default'])->toBe(30)
        ->and($schema['parameters']['properties']['directory']['type'])->toBe('string')
        ->and($schema['parameters']['required'])->toBe(['command']);
});

test('terminal tool executes command successfully', function () {
    $tool = new Terminal;

    $result = $tool->execute(['command' => 'echo "Hello World"']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['stdout'])->toContain('Hello World')
        ->and($result->getData()['return_code'])->toBe(0)
        ->and($result->getData()['success'])->toBeTrue();
});

test('terminal tool handles command errors', function () {
    $tool = new Terminal;

    $result = $tool->execute(['command' => 'ls /nonexistent/directory']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['return_code'])->not->toBe(0)
        ->and($result->getData()['success'])->toBeFalse()
        ->and($result->getData()['stderr'])->not->toBeEmpty();
});

test('terminal tool changes working directory', function () {
    $tool = new Terminal;

    $tempDir = sys_get_temp_dir();
    $result = $tool->execute([
        'command' => 'pwd',
        'directory' => $tempDir,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and(mb_trim($result->getData()['stdout']))->toBe(realpath($tempDir));
});
