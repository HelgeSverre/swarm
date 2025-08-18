<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use Exception;
use HelgeSverre\Swarm\Traits\Loggable;
use RuntimeException;

/**
 * Manages persistent state for the Swarm application
 * Handles saving and loading of .swarm.json state file
 */
class StateManager
{
    use Loggable;

    private const STATE_FILE = '.swarm.json';

    /**
     * Default state structure
     */
    private array $defaultState = [
        'tasks' => [],
        'task_history' => [],
        'current_task' => null,
        'conversation_history' => [],
        'tool_log' => [],
        'operation' => '',
        'allowed_directories' => [],
    ];

    /**
     * Get the full path to the state file
     */
    public function getStateFilePath(): string
    {
        return getcwd() . '/' . self::STATE_FILE;
    }

    /**
     * Save state to .swarm.json with atomic write
     */
    public function save(array $state): void
    {
        try {
            $stateFile = $this->getStateFilePath();

            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                throw new RuntimeException('Failed to encode state: ' . json_last_error_msg());
            }

            // Atomic write: write to temp file first, then rename
            $tempFile = $stateFile . '.tmp.' . getmypid();
            if (file_put_contents($tempFile, $json) === false) {
                throw new RuntimeException('Failed to write state to temp file');
            }

            // Rename is atomic on most filesystems
            if (! rename($tempFile, $stateFile)) {
                @unlink($tempFile);
                throw new RuntimeException('Failed to rename temp file to state file');
            }

            $this->logInfo('State saved to ' . self::STATE_FILE);
        } catch (Exception $e) {
            $this->logError('Failed to save state', [
                'error' => $e->getMessage(),
                'file' => self::STATE_FILE,
            ]);
            // Don't rethrow - state save failure shouldn't crash the app
        }
    }

    /**
     * Load state from .swarm.json if it exists
     */
    public function load(): array
    {
        $stateFile = $this->getStateFilePath();

        if (! file_exists($stateFile)) {
            $this->logDebug('No state file found, using defaults');

            return $this->defaultState;
        }

        $json = file_get_contents($stateFile);

        // Handle empty file
        if (empty(mb_trim($json))) {
            $this->logWarning('State file is empty, starting with clean slate');

            return $this->defaultState;
        }

        // Try to decode JSON
        $state = json_decode($json, true);

        // Check for JSON errors
        if ($state === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->logError('Failed to parse state file, starting with clean slate', [
                'error' => json_last_error_msg(),
                'file' => $stateFile,
            ]);

            // Optionally rename the corrupt file for debugging
            $backupFile = $stateFile . '.corrupt.' . time();
            rename($stateFile, $backupFile);
            $this->logInfo('Corrupt state file backed up', ['backup' => $backupFile]);

            return $this->defaultState;
        }

        // Validate state structure
        if (! is_array($state)) {
            $this->logWarning('State file does not contain valid array, starting with clean slate');

            return $this->defaultState;
        }

        // Validate state has expected structure
        if (! $this->validate($state)) {
            $this->logWarning('State file has invalid structure, starting with clean slate');

            return $this->defaultState;
        }

        // Merge with defaults, ensuring all expected keys exist
        $state = array_merge($this->defaultState, $state);

        $this->logInfo('State loaded from .swarm.json', [
            'tasks' => count($state['tasks'] ?? []),
            'history' => count($state['conversation_history'] ?? []),
            'task_history' => count($state['task_history'] ?? []),
        ]);

        return $state;
    }

    /**
     * Validate state structure
     */
    public function validate(array $state): bool
    {
        $requiredKeys = ['tasks', 'task_history', 'current_task', 'conversation_history', 'tool_log', 'operation', 'allowed_directories'];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $state)) {
                $this->logDebug('Missing required state key', ['key' => $key]);

                return false;
            }
        }

        // Validate types
        if (! is_array($state['tasks']) || ! is_array($state['task_history']) ||
            ! is_array($state['conversation_history']) || ! is_array($state['tool_log']) ||
            ! is_array($state['allowed_directories'])) {
            $this->logDebug('Invalid state value types');

            return false;
        }

        return true;
    }

    /**
     * Reset state to defaults
     */
    public function reset(): array
    {
        return $this->defaultState;
    }

    /**
     * Clear the state file
     */
    public function clear(): bool
    {
        $stateFile = $this->getStateFilePath();

        if (! file_exists($stateFile)) {
            return true;
        }

        if (! @unlink($stateFile)) {
            $this->logError('Failed to delete state file', ['file' => $stateFile]);

            return false;
        }

        $this->logInfo('State file cleared');

        return true;
    }

    /**
     * Check if state file exists
     */
    public function exists(): bool
    {
        return file_exists($this->getStateFilePath());
    }
}
