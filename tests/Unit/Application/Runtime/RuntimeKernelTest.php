<?php

declare(strict_types=1);

use HelgeSverre\Swarm\Application\Runtime\RuntimeKernel;
use HelgeSverre\Swarm\Application\Runtime\RuntimeMode;
use HelgeSverre\Swarm\Core\Application;

beforeEach(function () {
    $this->projectRoot = defined('SWARM_ROOT') ? SWARM_ROOT : dirname(__DIR__, 4);
    $this->stateFile = getcwd() . '/.swarm.json';
    $this->stateBackup = $this->stateFile . '.runtime-kernel-test-backup';

    $_ENV['OPENAI_API_KEY'] = 'test-api-key';
    $_ENV['LOG_ENABLED'] = false;

    if (file_exists($this->stateBackup)) {
        unlink($this->stateBackup);
    }

    if (file_exists($this->stateFile)) {
        rename($this->stateFile, $this->stateBackup);
    }
});

afterEach(function () {
    if (isset($this->app) && $this->app->exceptionHandler()) {
        $this->app->exceptionHandler()->unregister();
    }

    if (file_exists($this->stateFile)) {
        unlink($this->stateFile);
    }

    if (file_exists($this->stateBackup)) {
        rename($this->stateBackup, $this->stateFile);
    }

    unset($_ENV['OPENAI_API_KEY'], $_ENV['LOG_ENABLED']);
});

test('bootCli creates shared runtime services for interactive mode', function () {
    $this->app = new Application($this->projectRoot);

    try {
        $runtime = RuntimeKernel::bootCli($this->app);

        expect($runtime->mode)->toBe(RuntimeMode::Cli)
            ->and($runtime->stateManager)->not->toBeNull()
            ->and($runtime->pathChecker)->not->toBeNull()
            ->and($runtime->toolExecutor)->not->toBeNull()
            ->and($runtime->taskManager)->not->toBeNull()
            ->and($runtime->codingAgent)->not->toBeNull()
            ->and($runtime->commandHandler)->not->toBeNull()
            ->and($runtime->processManager)->not->toBeNull()
            ->and($runtime->ui)->toBeNull();
    } finally {
        $this->app->exceptionHandler()?->unregister();
    }
});

test('bootWorker reuses the same core graph without cli-only adapters', function () {
    $this->app = new Application($this->projectRoot);

    try {
        $runtime = RuntimeKernel::bootWorker($this->app);

        expect($runtime->mode)->toBe(RuntimeMode::Worker)
            ->and($runtime->stateManager)->not->toBeNull()
            ->and($runtime->pathChecker)->not->toBeNull()
            ->and($runtime->toolExecutor)->not->toBeNull()
            ->and($runtime->taskManager)->not->toBeNull()
            ->and($runtime->codingAgent)->not->toBeNull()
            ->and($runtime->commandHandler)->toBeNull()
            ->and($runtime->processManager)->toBeNull()
            ->and($runtime->ui)->toBeNull();
    } finally {
        $this->app->exceptionHandler()?->unregister();
    }
});
