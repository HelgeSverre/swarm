<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use Exception;
use HelgeSverre\Swarm\CLI\Command\CommandAction;
use HelgeSverre\Swarm\Core\PathChecker;
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
    protected array $handlers = [];

    /**
     * Result of command execution
     */
    protected ?CommandResult $lastResult = null;

    protected ?PathChecker $pathChecker = null;

    protected ?StateManager $stateManager = null;

    public function __construct(?PathChecker $pathChecker = null, ?StateManager $stateManager = null)
    {
        $this->pathChecker = $pathChecker;
        $this->stateManager = $stateManager;
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
        $input = trim($input);
        if ($input === '') {
            return CommandResult::ignored();
        }

        [$command, $arguments] = $this->parseCommandInput($input);

        if ($arguments !== null && $arguments !== '' && ! $this->supportsArguments($command)) {
            return CommandResult::ignored();
        }

        if (isset($this->handlers[$command])) {
            try {
                $handler = $this->handlers[$command];
                $this->lastResult = $handler($arguments);
                $this->logDebug('Command handled', [
                    'command' => $command,
                    'action' => $this->lastResult->action?->value,
                ]);

                return $this->lastResult;
            } catch (Exception $e) {
                $this->logError('Command handler failed', [
                    'command' => $input,
                    'error' => $e->getMessage(),
                ]);

                return CommandResult::error($e->getMessage());
            }
        }

        return CommandResult::ignored();
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
     * Handle directory addition with path validation
     */
    public function handleAddDirectory(string $path): CommandResult
    {
        if (! $this->pathChecker || ! $this->stateManager) {
            return CommandResult::error('Path management not available');
        }

        // Expand user path if needed
        if (str_starts_with($path, '~')) {
            $path = str_replace('~', $_SERVER['HOME'] ?? getenv('HOME'), $path);
        }

        if (! is_dir($path)) {
            return CommandResult::error("Directory does not exist: {$path}");
        }

        if ($this->pathChecker->addAllowedPath($path)) {
            // Save updated state
            $state = $this->stateManager->load();
            $state['allowed_directories'] = $this->pathChecker->getAllowedPaths();
            $this->stateManager->save($state);

            return CommandResult::success(
                CommandAction::ShowHelp,
                ['message' => "Directory added to allow-list: {$path}"]
            );
        }

        return CommandResult::error("Failed to add directory: {$path}");
    }

    /**
     * Handle directory removal
     */
    public function handleRemoveDirectory(string $path): CommandResult
    {
        if (! $this->pathChecker || ! $this->stateManager) {
            return CommandResult::error('Path management not available');
        }

        // Expand user path if needed
        if (str_starts_with($path, '~')) {
            $path = str_replace('~', $_SERVER['HOME'] ?? getenv('HOME'), $path);
        }

        if ($this->pathChecker->removeAllowedPath($path)) {
            // Save updated state
            $state = $this->stateManager->load();
            $state['allowed_directories'] = $this->pathChecker->getAllowedPaths();
            $this->stateManager->save($state);

            return CommandResult::success(
                CommandAction::ShowHelp,
                ['message' => "Directory removed from allow-list: {$path}"]
            );
        }

        return CommandResult::error("Failed to remove directory (not in allow-list): {$path}");
    }

    /**
     * Register default built-in commands
     */
    protected function registerDefaultCommands(): void
    {
        $this->registerCommand('help', function (?string $arguments = null) {
            return CommandResult::success(
                CommandAction::ShowHelp,
                ['message' => 'Commands: exit, quit, clear, save, clear-state, help, test-error, add-dir <path>, list-dirs, remove-dir <path>']
            );
        });

        $this->registerCommand('exit', function (?string $arguments = null) {
            return CommandResult::success(CommandAction::Exit);
        });

        $this->registerCommand('quit', function (?string $arguments = null) {
            return CommandResult::success(CommandAction::Exit);
        });

        $this->registerCommand('clear', function (?string $arguments = null) {
            return CommandResult::success(
                CommandAction::ClearHistory,
                ['message' => 'History cleared']
            );
        });

        $this->registerCommand('save', function (?string $arguments = null) {
            return CommandResult::success(CommandAction::SaveState);
        });

        $this->registerCommand('clear-state', function (?string $arguments = null) {
            return CommandResult::success(CommandAction::ClearState);
        });

        $this->registerCommand('test-error', function (?string $arguments = null) {
            $this->logInfo('Test error command invoked');
            throw new RuntimeException('Test exception to verify error logging');
        });

        $this->registerCommand('add-dir', function (?string $path = null) {
            if (! $this->pathChecker || ! $this->stateManager) {
                return CommandResult::error('Path management not available');
            }

            if ($path === null || $path === '') {
                return CommandResult::success(
                    CommandAction::ShowHelp,
                    ['message' => 'Usage: add-dir <directory-path>']
                );
            }

            return $this->handleAddDirectory($path);
        });

        $this->registerCommand('list-dirs', function (?string $arguments = null) {
            if (! $this->pathChecker) {
                return CommandResult::error('Path management not available');
            }

            $projectPath = $this->pathChecker->getProjectPath();
            $allowedPaths = $this->pathChecker->getAllowedPaths();

            $message = "Project directory: {$projectPath}\n";
            if (empty($allowedPaths)) {
                $message .= 'No additional allowed directories.';
            } else {
                $message .= "Allowed directories:\n" . implode("\n", array_map(fn ($p) => "  - {$p}", $allowedPaths));
            }

            return CommandResult::success(CommandAction::ShowHelp, ['message' => $message]);
        });

        $this->registerCommand('remove-dir', function (?string $path = null) {
            if (! $this->pathChecker || ! $this->stateManager) {
                return CommandResult::error('Path management not available');
            }

            if ($path === null || $path === '') {
                return CommandResult::success(
                    CommandAction::ShowHelp,
                    ['message' => 'Usage: remove-dir <directory-path>']
                );
            }

            return $this->handleRemoveDirectory($path);
        });
    }

    protected function parseCommandInput(string $input): array
    {
        $parts = preg_split('/\s+/', $input, limit: 2);
        $command = $parts[0] ?? $input;
        $arguments = isset($parts[1]) ? trim($parts[1]) : null;

        return [$command, $arguments];
    }

    protected function supportsArguments(string $command): bool
    {
        return in_array($command, ['add-dir', 'remove-dir'], true);
    }
}
