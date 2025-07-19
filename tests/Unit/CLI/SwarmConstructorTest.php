<?php

use HelgeSverre\Swarm\CLI\Swarm;

test('createFromEnvironment creates Swarm with proper dependencies', function () {
    // Set required environment variables
    $_ENV['OPENAI_API_KEY'] = 'test-api-key';
    $_ENV['OPENAI_MODEL'] = 'gpt-4';
    $_ENV['OPENAI_TEMPERATURE'] = '0.5';
    $_ENV['LOG_ENABLED'] = false;

    // Create SwarmCLI from environment
    $cli = Swarm::createFromEnvironment();

    expect($cli)->toBeInstanceOf(Swarm::class);

    // Clean up
    unset($_ENV['OPENAI_API_KEY'], $_ENV['OPENAI_MODEL'], $_ENV['OPENAI_TEMPERATURE'], $_ENV['LOG_ENABLED']);
});

test('createFromEnvironment throws exception when API key is missing', function () {
    // Save current environment state
    $originalKey = $_ENV['OPENAI_API_KEY'] ?? null;
    $originalEnvKey = getenv('OPENAI_API_KEY');
    $projectRoot = defined('SWARM_ROOT') ? SWARM_ROOT : dirname(__DIR__, 3);
    $envPath = $projectRoot . '/.env';
    $envBackupPath = $projectRoot . '/.env.backup.test';

    // Temporarily rename .env file if it exists
    $envExists = file_exists($envPath);
    if ($envExists) {
        rename($envPath, $envBackupPath);
    }

    try {
        // Make sure API key is not set in environment
        unset($_ENV['OPENAI_API_KEY']);
        putenv('OPENAI_API_KEY=');

        // Should throw exception
        expect(fn () => Swarm::createFromEnvironment())
            ->toThrow(Exception::class, 'OpenAI API key not found');
    } finally {
        // Restore .env file
        if ($envExists) {
            rename($envBackupPath, $envPath);
        }

        // Restore environment values
        if ($originalKey !== null) {
            $_ENV['OPENAI_API_KEY'] = $originalKey;
        }
        if ($originalEnvKey !== false) {
            putenv("OPENAI_API_KEY={$originalEnvKey}");
        }
    }
});

test('createFromEnvironment uses default values when not specified', function () {
    // Set only required API key
    $_ENV['OPENAI_API_KEY'] = 'test-api-key';

    // Remove optional settings
    unset($_ENV['OPENAI_MODEL'], $_ENV['OPENAI_TEMPERATURE'], $_ENV['LOG_ENABLED']);

    // Create SwarmCLI from environment
    $cli = Swarm::createFromEnvironment();

    expect($cli)->toBeInstanceOf(Swarm::class);

    // Clean up
    unset($_ENV['OPENAI_API_KEY']);
});

test('createFromEnvironment creates logger when LOG_ENABLED is true', function () {
    // Set environment variables
    $_ENV['OPENAI_API_KEY'] = 'test-api-key';
    $_ENV['LOG_ENABLED'] = true;
    $_ENV['LOG_LEVEL'] = 'debug';
    $_ENV['LOG_PATH'] = sys_get_temp_dir() . '/test-logs';

    // Create SwarmCLI from environment
    $cli = Swarm::createFromEnvironment();

    expect($cli)->toBeInstanceOf(Swarm::class);

    // Clean up
    unset($_ENV['OPENAI_API_KEY'], $_ENV['LOG_ENABLED'], $_ENV['LOG_LEVEL'], $_ENV['LOG_PATH']);
    if (is_dir(sys_get_temp_dir() . '/test-logs')) {
        rmdir(sys_get_temp_dir() . '/test-logs');
    }
});
