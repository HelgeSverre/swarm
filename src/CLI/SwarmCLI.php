<?php

namespace HelgeSverre\Swarm\CLI;

use Dotenv\Dotenv;
use Exception;
use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\Toolchain;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Task\TaskManager;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use OpenAI;

class SwarmCLI
{
    protected readonly CodingAgent $agent;

    protected readonly TUIRenderer $tui;

    protected readonly ?Logger $logger;

    protected array $syncedState = [
        'tasks' => [],
        'current_task' => null,
        'conversation_history' => [],
        'tool_log' => [],
        'operation' => '',
    ];

    public function __construct()
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

        // Simple logger setup - Laravel style
        $logger = null;
        if ($_ENV['LOG_ENABLED'] ?? false) {
            $logger = new Logger('swarm');

            // Get log level from environment
            $logLevel = match (mb_strtolower($_ENV['LOG_LEVEL'] ?? 'info')) {
                'debug' => Logger::DEBUG,
                'info' => Logger::INFO,
                'notice' => Logger::NOTICE,
                'warning', 'warn' => Logger::WARNING,
                'error' => Logger::ERROR,
                'critical' => Logger::CRITICAL,
                'alert' => Logger::ALERT,
                'emergency' => Logger::EMERGENCY,
                default => Logger::INFO,
            };

            // Log to file
            $logPath = $_ENV['LOG_PATH'] ?? 'logs';
            if (! is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }

            $logger->pushHandler(
                new RotatingFileHandler("{$logPath}/swarm.log", 7, $logLevel)
            );

            // Never log to console as it interferes with TUI rendering
            // All logs go to file only
        }

        $toolExecutor = new ToolExecutor($logger);
        Toolchain::registerAll($toolExecutor);

        $taskManager = new TaskManager($logger);
        $llmClient = OpenAI::client($apiKey);

        $this->logger = $logger;
        $this->agent = new CodingAgent($toolExecutor, $taskManager, $llmClient, $logger, $model, $temperature);
        $this->tui = new TUIRenderer;

        // Register shutdown handler for saving state
        register_shutdown_function([$this, 'saveStateOnShutdown']);

        // Register signal handlers for graceful shutdown (SIGINT = Ctrl+C, SIGTERM = kill)
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_async_signals(true);
        }
    }

    public function run(): void
    {
        // Load any saved state from previous session
        $this->loadState();

        // Use background processing mode by default
        $this->logger?->info('Starting with background processing mode');
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
        $this->logger?->info('Received signal, saving state', ['signal' => $signal]);
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

            if ($input === 'exit' || $input === 'quit') {
                $this->saveState();
                break;
            }

            if ($input === 'clear') {
                // Clear command history
                InputHandler::clearHistory();

                continue;
            }

            if ($input === 'help') {
                $this->showHelp();

                continue;
            }

            if ($input === 'save') {
                $this->saveState();
                $this->tui->showNotification('State saved to .swarm.json', 'success');

                continue;
            }

            if ($input === 'clear-state') {
                $stateFile = getcwd() . '/.swarm.json';
                if (file_exists($stateFile)) {
                    unlink($stateFile);
                    $this->syncedState = [
                        'tasks' => [],
                        'current_task' => null,
                        'conversation_history' => [],
                        'tool_log' => [],
                        'operation' => '',
                    ];
                    $this->tui->showNotification('State cleared', 'success');
                } else {
                    $this->tui->showNotification('No saved state found', 'info');
                }

                continue;
            }

            try {
                // Log user request
                $this->logger?->info('User request received', [
                    'input' => $input,
                    'timestamp' => date('Y-m-d H:i:s'),
                ]);

                // Use streaming processor for real-time updates
                $this->runWithStreamingProcessor($input);
            } catch (Exception $e) {
                // Stop processing animation on error too
                $this->tui->stopProcessing();

                $this->logger?->error('Request processing failed', [
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

    protected function showHelp(): void
    {
        $this->tui->showNotification('Commands: exit, quit, clear, save, clear-state, help', 'info');
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
            $maxWaitTime = (int) ($_ENV['SWARM_REQUEST_TIMEOUT'] ?? 600);
            $startTime = microtime(true);
            $processComplete = false;

            // Process updates in real-time
            while (! $processComplete && microtime(true) - $startTime < $maxWaitTime) {
                $now = microtime(true);

                // Read any available updates
                $updates = $processor->readUpdates();

                foreach ($updates as $update) {
                    $type = $update['type'] ?? 'status';

                    if ($type === 'progress') {
                        // Update UI with progress message
                        $this->tui->updateProcessingMessage($update['message'] ?? '');
                    } elseif ($type === 'state_sync') {
                        // Handle comprehensive state sync
                        $this->syncedState = array_merge($this->syncedState, $update['data'] ?? []);
                        // Refresh TUI with synced state
                        $this->tui->refresh($this->syncedState);
                    } elseif ($type === 'task_status') {
                        // Legacy task status update (kept for compatibility)
                        $taskStatus = $update['status'] ?? [];
                        $this->syncedState['tasks'] = $taskStatus['tasks'] ?? [];
                        $this->syncedState['current_task'] = $taskStatus['current_task'] ?? null;
                        $this->tui->refresh($this->syncedState);
                    } elseif ($type === 'status') {
                        $status = $update['status'] ?? '';

                        if ($status === 'completed') {
                            $processComplete = true;
                            // Stop processing animation
                            $this->tui->stopProcessing();

                            // Display response
                            $responseData = $update['response'] ?? [];
                            $response = \HelgeSverre\Swarm\Agent\AgentResponse::success(
                                $responseData['message'] ?? ''
                            );
                            $this->tui->displayResponse($response);
                            // Clear tasks after completion
                            $this->syncedState['tasks'] = [];
                            $this->syncedState['current_task'] = null;
                            $this->syncedState['operation'] = '';
                            // Manually trigger refresh to show response with cleared tasks
                            $this->tui->refresh($this->syncedState);
                            // Save state after successful completion
                            $this->saveState();
                        } elseif ($status === 'error') {
                            $processComplete = true;
                            throw new Exception($update['error'] ?? 'Unknown error occurred');
                        }
                    } elseif ($type === 'error') {
                        // Log error but don't stop unless it's fatal
                        $this->logger?->error('Process error', ['error' => $update['message']]);
                    }
                }

                // Update animation periodically
                if ($now - $lastUpdate > 0.1) {
                    $this->tui->showProcessing();
                    $lastUpdate = $now;
                }

                // Small sleep to avoid busy waiting
                usleep(20000); // 20ms for more responsive updates
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

            $this->logger?->error('Streaming process failed', [
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
     * Save current state to .swarm.json
     */
    protected function saveState(): void
    {
        $stateFile = getcwd() . '/.swarm.json';
        $json = json_encode($this->syncedState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json !== false) {
            file_put_contents($stateFile, $json);
            $this->logger?->info('State saved to .swarm.json');
        }
    }

    /**
     * Load state from .swarm.json if it exists
     */
    protected function loadState(): void
    {
        $stateFile = getcwd() . '/.swarm.json';

        if (file_exists($stateFile)) {
            $json = file_get_contents($stateFile);

            // Handle empty file
            if (empty(trim($json))) {
                $this->logger?->warning('State file is empty, starting with clean slate');

                return;
            }

            // Try to decode JSON
            $state = json_decode($json, true);

            // Check for JSON errors
            if ($state === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger?->error('Failed to parse state file, starting with clean slate', [
                    'error' => json_last_error_msg(),
                    'file' => $stateFile,
                ]);

                // Optionally rename the corrupt file for debugging
                $backupFile = $stateFile . '.corrupt.' . time();
                rename($stateFile, $backupFile);
                $this->logger?->info('Corrupt state file backed up', ['backup' => $backupFile]);

                $this->tui->showNotification('State file was corrupt, starting fresh', 'warning');

                return;
            }

            // Validate state structure
            if (! is_array($state)) {
                $this->logger?->warning('State file does not contain valid array, starting with clean slate');

                return;
            }

            // Merge with defaults, ensuring all expected keys exist
            $this->syncedState = array_merge($this->syncedState, $state);

            $this->logger?->info('State loaded from .swarm.json', [
                'tasks' => count($state['tasks'] ?? []),
                'history' => count($state['conversation_history'] ?? []),
            ]);
            $this->tui->showNotification('Restored previous session', 'success');
        }
    }
}
