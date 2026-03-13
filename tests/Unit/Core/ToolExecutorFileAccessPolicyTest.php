<?php

declare(strict_types=1);

use HelgeSverre\Swarm\Core\PathChecker;
use HelgeSverre\Swarm\Core\ToolExecutor;

beforeEach(function () {
    $this->projectRoot = defined('SWARM_ROOT') ? SWARM_ROOT : dirname(__DIR__, 3);
    $this->tempDir = sys_get_temp_dir() . '/swarm_tool_policy_' . uniqid();
    mkdir($this->tempDir);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        $files = glob($this->tempDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        rmdir($this->tempDir);
    }
});

test('default read and write tools honor the injected file access policy', function () {
    $executor = ToolExecutor::createWithDefaultTools(
        fileAccessPolicy: new PathChecker($this->projectRoot)
    );

    $blockedFile = $this->tempDir . '/secret.txt';
    file_put_contents($blockedFile, 'secret');

    $readResponse = $executor->dispatch('read_file', ['path' => $blockedFile]);
    $writeResponse = $executor->dispatch('write_file', [
        'path' => $this->tempDir . '/new-file.txt',
        'content' => 'blocked',
    ]);

    expect($readResponse->isError())->toBeTrue()
        ->and($readResponse->getError())->toContain('Path access denied')
        ->and($writeResponse->isError())->toBeTrue()
        ->and($writeResponse->getError())->toContain('Path access denied');
});

test('default grep and glob tools honor the injected file access policy', function () {
    $executor = ToolExecutor::createWithDefaultTools(
        fileAccessPolicy: new PathChecker($this->projectRoot)
    );

    file_put_contents($this->tempDir . '/match.txt', 'needle');

    $globResponse = $executor->dispatch('glob', [
        'path' => $this->tempDir,
        'pattern' => '*.txt',
    ]);
    $grepResponse = $executor->dispatch('grep', [
        'path' => $this->tempDir,
        'pattern' => 'needle',
    ]);

    expect($globResponse->isError())->toBeTrue()
        ->and($globResponse->getError())->toContain('Path access denied')
        ->and($grepResponse->isError())->toBeTrue()
        ->and($grepResponse->getError())->toContain('Path access denied');
});
