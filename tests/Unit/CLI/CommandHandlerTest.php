<?php

declare(strict_types=1);

use HelgeSverre\Swarm\CLI\Command\CommandAction;
use HelgeSverre\Swarm\CLI\CommandHandler;
use HelgeSverre\Swarm\CLI\StateManager;
use HelgeSverre\Swarm\Core\PathChecker;

beforeEach(function () {
    $this->projectRoot = defined('SWARM_ROOT') ? SWARM_ROOT : dirname(__DIR__, 3);
    $this->tempDir = sys_get_temp_dir() . '/swarm_cmd_' . uniqid();
    mkdir($this->tempDir);

    $stateFile = getcwd() . '/.swarm.json';
    if (file_exists($stateFile)) {
        unlink($stateFile);
    }
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        rmdir($this->tempDir);
    }

    $stateFile = getcwd() . '/.swarm.json';
    if (file_exists($stateFile)) {
        unlink($stateFile);
    }
});

test('add-dir accepts an inline path argument', function () {
    $handler = new CommandHandler(
        new PathChecker($this->projectRoot),
        new StateManager
    );

    $result = $handler->handle('add-dir ' . $this->tempDir);

    expect($result->handled)->toBeTrue()
        ->and($result->action)->toBe(CommandAction::ShowHelp)
        ->and($result->getMessage())->toContain('Directory added to allow-list');
});

test('remove-dir without an argument returns usage help', function () {
    $handler = new CommandHandler(
        new PathChecker($this->projectRoot),
        new StateManager
    );

    $result = $handler->handle('remove-dir');

    expect($result->handled)->toBeTrue()
        ->and($result->action)->toBe(CommandAction::ShowHelp)
        ->and($result->getMessage())->toBe('Usage: remove-dir <directory-path>');
});

test('unknown commands are ignored', function () {
    $handler = new CommandHandler;

    $result = $handler->handle('not-a-real-command foo');

    expect($result->handled)->toBeFalse()
        ->and($result->action)->toBeNull();
});

test('natural language that starts with a command word is not intercepted', function () {
    $handler = new CommandHandler;

    $result = $handler->handle('help me understand this code');

    expect($result->handled)->toBeFalse()
        ->and($result->action)->toBeNull();
});
