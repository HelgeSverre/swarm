<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use Exception;
use HelgeSverre\Swarm\Traits\Loggable;
use RuntimeException;

/**
 * Handles built-in commands for the Swarm CLI
 */
class CommandHandler
{
    use Loggable;

    /**
     * Registered command handlers
     */
    private array $handlers = [];

    /**
     * Result of command execution
     */
    private ?CommandResult $lastResult = null;

    public function __construct()
    {
        $this->registerDefaultCommands();
    }

    /**
     * Register a custom command handler
     */
    public function registerCommand(string $name, callable $handler): void
    {
        $this->handlers[$name] = $handler;
        $this->logDebug('Command registered', ['command' => $name]);
    }

    /**
     * Handle a command input
     */
    public function handle(string $input): CommandResult
    {
        // Check if this is a registered command
        if (isset($this->handlers[$input])) {
            try {
                $this->lastResult = call_user_func($this->handlers[$input]);
                $this->logDebug('Command handled', [
                    'command' => $input,
                    'action' => $this->lastResult->action,
                ]);

                return $this->lastResult;
            } catch (Exception $e) {
                $this->logError('Command handler failed', [
                    'command' => $input,
                    'error' => $e->getMessage(),
                ]);

                return new CommandResult(
                    handled: true,
                    action: 'error',
                    data: ['error' => $e->getMessage()]
                );
            }
        }

        // Not a command
        return new CommandResult(
            handled: false,
            action: null,
            data: []
        );
    }

    /**
     * Get list of available commands
     */
    public function getAvailableCommands(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Check if a command exists
     */
    public function hasCommand(string $name): bool
    {
        return isset($this->handlers[$name]);
    }

    /**
     * Get the last command result
     */
    public function getLastResult(): ?CommandResult
    {
        return $this->lastResult;
    }

    /**
     * Register default built-in commands
     */
    protected function registerDefaultCommands(): void
    {
        $this->registerCommand('help', function () {
            return new CommandResult(
                handled: true,
                action: 'show_help',
                data: ['message' => 'Commands: exit, quit, clear, save, clear-state, help, test-error']
            );
        });

        $this->registerCommand('exit', function () {
            return new CommandResult(
                handled: true,
                action: 'exit',
                data: []
            );
        });

        $this->registerCommand('quit', function () {
            return new CommandResult(
                handled: true,
                action: 'exit',
                data: []
            );
        });

        $this->registerCommand('clear', function () {
            return new CommandResult(
                handled: true,
                action: 'clear_history',
                data: ['message' => 'History cleared']
            );
        });

        $this->registerCommand('save', function () {
            return new CommandResult(
                handled: true,
                action: 'save_state',
                data: []
            );
        });

        $this->registerCommand('clear-state', function () {
            return new CommandResult(
                handled: true,
                action: 'clear_state',
                data: []
            );
        });

        $this->registerCommand('test-error', function () {
            $this->logInfo('Test error command invoked');
            throw new RuntimeException('Test exception to verify error logging');
        });
    }
}

/**
 * Result of command execution
 */
class CommandResult
{
    public function __construct(
        public readonly bool $handled,
        public readonly ?string $action,
        public readonly array $data
    ) {}

    /**
     * Check if command requires exit
     */
    public function isExit(): bool
    {
        return $this->action === 'exit';
    }

    /**
     * Check if command had an error
     */
    public function hasError(): bool
    {
        return $this->action === 'error';
    }

    /**
     * Get error message if any
     */
    public function getError(): ?string
    {
        return $this->data['error'] ?? null;
    }

    /**
     * Get message if any
     */
    public function getMessage(): ?string
    {
        return $this->data['message'] ?? null;
    }
}
