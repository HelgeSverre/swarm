<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use Dotenv\Dotenv;
use Exception;
use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\Toolchain;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Enums\Core\LogLevel;
use HelgeSverre\Swarm\Task\TaskManager;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use OpenAI;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class Swarm
{
    // Configuration constants
    private const STATE_FILE = '.swarm.json';

    private const DEFAULT_TIMEOUT = 600; // 10 minutes

    private const ANIMATION_INTERVAL = 0.1;

    private const PROCESS_SLEEP_MS = 20000;

    protected readonly CodingAgent $agent;

    protected readonly UI $tui;

    protected readonly LoggerInterface $logger;

    /**
     * Synced state that is shared between TUI and background processing
     * This will be saved to .swarm.json on shutdown
     */
    protected array $syncedState = [
        'tasks' => [],
        'task_history' => [],
        'current_task' => null,
        'conversation_history' => [],
        'tool_log' => [],
        'operation' => '',
    ];

    /**
     * Create a new SwarmCLI instance with injected dependencies
     */
    public function __construct(
        CodingAgent $agent,
        ?UI $tui = null,
        ?LoggerInterface $logger = null
    ) {
        $this->agent = $agent;
        $this->tui = $tui ?? new UI;
        $this->logger = $logger ?? new NullLogger;

        // Register shutdown handler for saving state
        register_shutdown_function([$this, 'saveStateOnShutdown']);

        // Register signal handlers for graceful shutdown (SIGINT = Ctrl+C, SIGTERM = kill)
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_async_signals(true);
        }
    }

    /**
     * Create a Swarm instance from environment configuration
     */
    public static function createFromEnvironment(): self
    {
        // Load environment variables from project root
        $projectRoot = defined('SWARM_ROOT') ? SWARM_ROOT : dirname(__DIR__, 2);

        if (file_exists($projectRoot . '/.env')) {
            $dotenv = Dotenv::createImmutable($projectRoot);
            $dotenv->load();
        }

        // Get API key from environment
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
        if (! $apiKey) {
            throw new Exception('OpenAI API key not found. Please set OPENAI_API_KEY environment variable or create a .env file.');
        }

        // Get model from environment
        $model = $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini';
        $temperature = (float) ($_ENV['OPENAI_TEMPERATURE'] ?? 0.7);

        // Setup logger
        $logger = new NullLogger;
        if ($_ENV['LOG_ENABLED'] ?? false) {
            $logger = new Logger('swarm');

            // Get log level from environment
            $logLevel = LogLevel::fromString($_ENV['LOG_LEVEL'] ?? 'info')->toMonologLevel();

            // Log to file
            $logPath = $_ENV['LOG_PATH'] ?? 'logs';
            if (! is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }

            $logger->pushHandler(
                new RotatingFileHandler("{$logPath}/swarm.log", 7, $logLevel)
            );
        }

        // Create dependencies
        $toolExecutor = new ToolExecutor($logger);
        Toolchain::registerAll($toolExecutor);

        $taskManager = new TaskManager($logger);
        $llmClient = OpenAI::client($apiKey);

        $agent = new CodingAgent($toolExecutor, $taskManager, $llmClient, $logger, $model, $temperature);
        $tui = new UI;

        return new self($agent, $tui, $logger);
    }

    public function run(): void
    {
        // Load any saved state from previous session
        $this->loadState();

        // Use background processing mode by default
        $this->logger->info('Starting with background processing mode');
        $this->runWithBackgroundProcessing();
    }

    /**
     * Handle shutdown to save state
     */
    public function saveStateOnShutdown(): void
    {
        // Only save if we have some state to save
        if (! empty($this->syncedState['conversation_history']) ||
            ! empty($this->syncedState['tasks']) ||
            ! empty($this->syncedState['tool_log'])) {
            $this->saveState();
        }
    }

    /**
     * Handle signals for graceful shutdown
     */
    public function handleSignal(int $signal): void
    {
        $this->logger->info('Received signal, saving state', ['signal' => $signal]);
        $this->saveState();

        // Cleanup TUI
        $this->tui->cleanup();

        exit(0);
    }

    protected function runWithBackgroundProcessing(): void
    {
        while (true) {
            $this->tui->refresh($this->syncedState);

            $input = $this->tui->prompt('>');

            // Handle built-in commands
            if ($this->handleCommand($input)) {
                if ($input === 'exit' || $input === 'quit') {
                    break; // Exit the main loop
                }

                continue;
            }

            try {
                // Log user request
                $this->logger->info('User request received', [
                    'input' => $input,
                    'timestamp' => date('Y-m-d H:i:s'),
                ]);

                // Use streaming processor for real-time updates
                $this->runWithStreamingProcessor($input);
            } catch (Exception $e) {
                // Stop processing animation on error too
                $this->tui->stopProcessing();

                $this->logger->error('Request processing failed', [
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'input' => $input,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Display error but continue running
                $this->tui->displayError($e->getMessage());

                // Check if this was a timeout and if retry is enabled
                if (str_contains($e->getMessage(), 'timed out') &&
                    ($_ENV['SWARM_TIMEOUT_RETRY_ENABLED'] ?? true)) {
                    $this->tui->showNotification(
                        'The request timed out. You can retry with a longer timeout or simplify your request.',
                        'warning'
                    );
                }

                // Continue the main loop instead of crashing
                continue;
            }
        }
    }

    /**
     * Handle built-in commands
     *
     * @return bool True if command was handled, false otherwise
     */
    protected function handleCommand(string $input): bool
    {
        return match ($input) {
            'exit', 'quit' => $this->handleExit(),
            'clear' => $this->handleClear(),
            'help' => $this->handleHelp(),
            'save' => $this->handleSave(),
            'clear-state' => $this->handleClearState(),
            default => false
        };
    }

    protected function handleExit(): bool
    {
        $this->saveState();

        // Return special value to signal exit
        return true;
    }

    protected function handleClear(): bool
    {
        InputHandler::clearHistory();

        return true;
    }

    protected function handleHelp(): bool
    {
        $this->tui->showNotification('Commands: exit, quit, clear, save, clear-state, help', 'info');

        return true;
    }

    protected function handleSave(): bool
    {
        $this->saveState();
        $this->tui->showNotification('State saved to ' . self::STATE_FILE, 'success');

        return true;
    }

    protected function handleClearState(): bool
    {
        try {
            $stateFile = getcwd() . '/' . self::STATE_FILE;
            if (file_exists($stateFile)) {
                if (! @unlink($stateFile)) {
                    throw new RuntimeException('Failed to delete state file');
                }
                $this->resetState();
                $this->tui->showNotification('State cleared', 'success');
            } else {
                $this->tui->showNotification('No saved state found', 'info');
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to clear state', ['error' => $e->getMessage()]);
            $this->tui->showNotification('Failed to clear state: ' . $e->getMessage(), 'error');
        }

        return true;
    }

    protected function resetState(): void
    {
        $this->syncedState = [
            'tasks' => [],
            'task_history' => [],
            'current_task' => null,
            'conversation_history' => [],
            'tool_log' => [],
            'operation' => '',
        ];
    }

    /**
     * Run with streaming background processor (pipe-based IPC)
     */
    protected function runWithStreamingProcessor(string $input): void
    {
        $processor = new StreamingBackgroundProcessor($this->logger);

        try {
            // Launch the streaming processor
            $processor->launch($input);

            // Start processing animation
            $this->tui->startProcessing();

            $lastUpdate = microtime(true);
            // Get timeout from environment or use default of 10 minutes
            $maxWaitTime = (int) ($_ENV['SWARM_REQUEST_TIMEOUT'] ?? self::DEFAULT_TIMEOUT);
            $startTime = microtime(true);
            $processComplete = false;

            // Process updates in real-time
            while (! $processComplete && microtime(true) - $startTime < $maxWaitTime) {
                $now = microtime(true);

                // Read any available updates
                $updates = $processor->readUpdates();

                foreach ($updates as $update) {
                    if ($this->processUpdate($update, $processComplete)) {
                        break;
                    }
                }

                // Update animation periodically
                if ($now - $lastUpdate > self::ANIMATION_INTERVAL) {
                    $this->tui->showProcessing();
                    $lastUpdate = $now;
                }

                // Small sleep to avoid busy waiting
                usleep(self::PROCESS_SLEEP_MS); // 20ms for more responsive updates
            }

            // Check timeout
            if (! $processComplete) {
                // Terminate the process gracefully
                $processor->terminate();

                $timeoutMinutes = round($maxWaitTime / 60, 1);
                throw new Exception(
                    "Request timed out after {$timeoutMinutes} minutes. " .
                    'You can increase the timeout by setting SWARM_REQUEST_TIMEOUT environment variable (in seconds).'
                );
            }
        } catch (Exception $e) {
            // Stop processing animation on error
            $this->tui->stopProcessing();

            $this->logger->error('Streaming process failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'input' => $input,
            ]);

            $this->tui->displayError($e->getMessage());
        } finally {
            // Always cleanup
            $processor->cleanup();
        }
    }

    /**
     * Save current state to .swarm.json with atomic write
     */
    protected function saveState(): void
    {
        try {
            $stateFile = getcwd() . '/' . self::STATE_FILE;

            // Update task history from TaskManager before saving
            $taskManager = $this->agent->getTaskManager();
            $this->syncedState['task_history'] = $taskManager->getTaskHistory();

            $json = json_encode($this->syncedState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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

            $this->logger->info('State saved to ' . self::STATE_FILE);
        } catch (Exception $e) {
            $this->logger->error('Failed to save state', [
                'error' => $e->getMessage(),
                'file' => self::STATE_FILE,
            ]);
            // Don't rethrow - state save failure shouldn't crash the app
        }
    }

    /**
     * Load state from .swarm.json if it exists
     */
    protected function loadState(): void
    {
        $stateFile = getcwd() . '/' . self::STATE_FILE;

        if (file_exists($stateFile)) {
            $json = file_get_contents($stateFile);

            // Handle empty file
            if (empty(trim($json))) {
                $this->logger->warning('State file is empty, starting with clean slate');

                return;
            }

            // Try to decode JSON
            $state = json_decode($json, true);

            // Check for JSON errors
            if ($state === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to parse state file, starting with clean slate', [
                    'error' => json_last_error_msg(),
                    'file' => $stateFile,
                ]);

                // Optionally rename the corrupt file for debugging
                $backupFile = $stateFile . '.corrupt.' . time();
                rename($stateFile, $backupFile);
                $this->logger->info('Corrupt state file backed up', ['backup' => $backupFile]);

                $this->tui->showNotification('State file was corrupt, starting fresh', 'warning');

                return;
            }

            // Validate state structure
            if (! is_array($state)) {
                $this->logger->warning('State file does not contain valid array, starting with clean slate');

                return;
            }

            // Validate state has expected structure
            if (! $this->validateState($state)) {
                $this->logger->warning('State file has invalid structure, starting with clean slate');

                return;
            }

            // Merge with defaults, ensuring all expected keys exist
            $this->syncedState = array_merge($this->syncedState, $state);

            // Restore task history to TaskManager if available
            if (isset($state['task_history']) && is_array($state['task_history'])) {
                $taskManager = $this->agent->getTaskManager();
                $taskManager->setTaskHistory($state['task_history']);
            }

            $this->logger->info('State loaded from .swarm.json', [
                'tasks' => count($state['tasks'] ?? []),
                'history' => count($state['conversation_history'] ?? []),
                'task_history' => count($state['task_history'] ?? []),
            ]);
            $this->tui->showNotification('Restored previous session', 'success');
        }
    }

    /**
     * Validate state structure
     */
    protected function validateState(array $state): bool
    {
        $requiredKeys = ['tasks', 'task_history', 'current_task', 'conversation_history', 'tool_log', 'operation'];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $state)) {
                $this->logger->debug('Missing required state key', ['key' => $key]);

                return false;
            }
        }

        // Validate types
        if (! is_array($state['tasks']) || ! is_array($state['task_history']) ||
            ! is_array($state['conversation_history']) || ! is_array($state['tool_log'])) {
            $this->logger->debug('Invalid state value types');

            return false;
        }

        return true;
    }

    /**
     * Process a single update from the streaming processor
     *
     * @param array $update The update to process
     * @param bool &$processComplete Reference to flag indicating if processing is complete
     *
     * @return bool True if processing should stop, false otherwise
     */
    protected function processUpdate(array $update, bool &$processComplete): bool
    {
        $type = $update['type'] ?? 'status';

        return match ($type) {
            'progress' => $this->handleProgressUpdate($update),
            'state_sync' => $this->handleStateSyncUpdate($update),
            'task_status' => $this->handleTaskStatusUpdate($update),
            'status' => $this->handleStatusUpdate($update, $processComplete),
            'error' => $this->handleErrorUpdate($update),
            default => false
        };
    }

    protected function handleProgressUpdate(array $update): bool
    {
        $this->tui->updateProcessingMessage($update['message'] ?? '');

        return false;
    }

    protected function handleStateSyncUpdate(array $update): bool
    {
        $this->syncedState = array_merge($this->syncedState, $update['data'] ?? []);
        $this->tui->refresh($this->syncedState);

        return false;
    }

    protected function handleTaskStatusUpdate(array $update): bool
    {
        // Legacy task status update (kept for compatibility)
        $taskStatus = $update['status'] ?? [];
        $this->syncedState['tasks'] = $taskStatus['tasks'] ?? [];
        $this->syncedState['current_task'] = $taskStatus['current_task'] ?? null;
        $this->tui->refresh($this->syncedState);

        return false;
    }

    protected function handleStatusUpdate(array $update, bool &$processComplete): bool
    {
        $status = $update['status'] ?? '';

        if ($status === 'completed') {
            $processComplete = true;
            $this->handleCompletedStatus($update);

            return true;
        } elseif ($status === 'error') {
            $processComplete = true;
            throw new Exception($update['error'] ?? 'Unknown error occurred');
        }

        return false;
    }

    protected function handleCompletedStatus(array $update): void
    {
        // Stop processing animation
        $this->tui->stopProcessing();

        // Display response
        $responseData = $update['response'] ?? [];
        $response = \HelgeSverre\Swarm\Agent\AgentResponse::success(
            $responseData['message'] ?? ''
        );
        $this->tui->displayResponse($response);

        // Clear completed tasks and update history
        $taskManager = $this->agent->getTaskManager();
        $taskManager->clearCompletedTasks();

        // Update synced state with current tasks
        $this->syncedState['tasks'] = $taskManager->getTasksAsArrays();
        $this->syncedState['task_history'] = $taskManager->getTaskHistory();
        $this->syncedState['current_task'] = null;
        $this->syncedState['operation'] = '';

        // Manually trigger refresh to show response with cleared tasks
        $this->tui->refresh($this->syncedState);

        // Save state after successful completion
        $this->saveState();
    }

    protected function handleErrorUpdate(array $update): bool
    {
        // Log error but don't stop unless it's fatal
        $this->logger->error('Process error', ['error' => $update['message'] ?? 'Unknown error']);

        return false;
    }
}
