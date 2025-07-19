<?php

namespace HelgeSverre\Swarm\CLI;

use Dotenv\Dotenv;
use Exception;
use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Task\TaskManager;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use OpenAI;

/**
 * Async processor that streams progress updates via stdout
 * instead of writing to a status file
 */
class StreamingAsyncProcessor
{
    /**
     * Process a request asynchronously with streaming updates
     */
    public static function processRequest(string $input, int $timeout = 300): void
    {
        // Set execution time limit based on timeout
        set_time_limit($timeout);

        // Register signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () {
                self::sendUpdate([
                    'type' => 'status',
                    'status' => 'error',
                    'error' => 'Process terminated by timeout signal',
                ]);
                exit(1);
            });

            pcntl_signal(SIGALRM, function () {
                self::sendUpdate([
                    'type' => 'status',
                    'status' => 'error',
                    'error' => 'Process exceeded time limit',
                ]);
                exit(1);
            });

            // Enable async signal handling
            pcntl_async_signals(true);
        }

        try {
            // Send initial status
            self::sendUpdate([
                'type' => 'status',
                'status' => 'initializing',
                'message' => 'Starting request processing...',
            ]);

            // Load environment
            $envPath = dirname(__DIR__, 2) . '/.env';
            if (file_exists($envPath)) {
                $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
                $dotenv->load();
            }

            // Setup logging to file only (not stderr)
            $logger = null;
            if ($_ENV['LOG_ENABLED'] ?? false) {
                $logPath = $_ENV['LOG_PATH'] ?? 'logs';
                if (! is_absolute_path($logPath)) {
                    $logPath = dirname(__DIR__, 2) . '/' . $logPath;
                }

                if (! is_dir($logPath)) {
                    mkdir($logPath, 0755, true);
                }

                $logFile = $logPath . '/swarm-' . date('Y-m-d') . '.log';
                $logger = new Logger('swarm');
                $logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
            }

            // Initialize services
            self::sendUpdate([
                'type' => 'status',
                'status' => 'initializing',
                'message' => 'Setting up tools and services...',
            ]);

            $toolExecutor = ToolExecutor::createWithDefaultTools($logger);
            $taskManager = new TaskManager($logger);

            // Setup OpenAI client
            $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
            if (! $apiKey) {
                throw new Exception('OpenAI API key not found in environment');
            }

            $openAI = OpenAI::client($apiKey);

            // Create agent with progress callback
            $agent = new CodingAgent(
                toolExecutor: $toolExecutor,
                taskManager: $taskManager,
                llmClient: $openAI,
                logger: $logger,
                model: $_ENV['OPENAI_MODEL'] ?? 'gpt-4.1-mini',
                temperature: (float) ($_ENV['OPENAI_TEMPERATURE'] ?? 0.7)
            );

            // Load conversation history from state file if it exists
            $stateFile = getcwd() . '/.swarm.json';
            if (file_exists($stateFile)) {
                $stateContent = file_get_contents($stateFile);
                if (! empty(trim($stateContent))) {
                    $state = json_decode($stateContent, true);
                    if ($state && isset($state['conversation_history']) && is_array($state['conversation_history'])) {
                        $agent->setConversationHistory($state['conversation_history']);
                        $logger?->debug('Restored conversation history', [
                            'count' => count($state['conversation_history']),
                        ]);
                    }
                }
            }

            // Set progress callback to stream updates
            // Track last heartbeat time and last state sync
            $lastHeartbeat = time();
            $heartbeatInterval = (int) ($_ENV['SWARM_HEARTBEAT_INTERVAL'] ?? 30);
            $lastStateSync = 0;
            $stateSyncThrottle = 0.1; // 100ms throttle for state updates

            $agent->setProgressCallback(function (string $operation, array $details) use (&$lastHeartbeat, &$lastStateSync, $heartbeatInterval, $stateSyncThrottle, $agent, $toolExecutor) {
                $message = match ($operation) {
                    'classifying' => 'Analyzing request type...',
                    'extracting_tasks' => 'Identifying tasks to complete...',
                    'planning_task' => 'Planning: ' . ($details['task_description'] ?? ''),
                    'executing_task' => 'Executing: ' . ($details['task_description'] ?? ''),
                    'calling_openai' => 'Calling AI model...',
                    'generating_summary' => 'Generating summary...',
                    default => ucfirst(str_replace('_', ' ', $operation)) . '...',
                };

                self::sendUpdate([
                    'type' => 'progress',
                    'operation' => $operation,
                    'message' => $message,
                    'details' => $details,
                ]);

                // Send comprehensive state sync with throttling
                $now = microtime(true);
                if ($now - $lastStateSync > $stateSyncThrottle) {
                    $status = $agent->getStatus();
                    self::sendUpdate([
                        'type' => 'state_sync',
                        'data' => [
                            'tasks' => $status['tasks'],
                            'current_task' => $status['current_task'],
                            'conversation_history' => $agent->getConversationHistory(),
                            'tool_log' => array_slice($toolExecutor->getExecutionLog(), -10), // Last 10 tool executions
                            'operation' => $operation,
                            'operation_details' => $details,
                        ],
                    ]);
                    $lastStateSync = $now;
                }

                // Send heartbeat if enough time has passed
                $now = time();
                if ($now - $lastHeartbeat >= $heartbeatInterval) {
                    self::sendUpdate([
                        'type' => 'heartbeat',
                        'message' => 'Process is still running...',
                        'elapsed' => $now - $lastHeartbeat,
                    ]);
                    $lastHeartbeat = $now;
                }
            });

            $logger?->info('Processing user request (streaming)', ['input' => $input]);

            // Send processing status
            self::sendUpdate([
                'type' => 'status',
                'status' => 'processing',
                'message' => 'Processing your request...',
            ]);

            // Process the request
            $response = $agent->processRequest($input);

            // Send completion status with response
            self::sendUpdate([
                'type' => 'status',
                'status' => 'completed',
                'message' => 'Request completed successfully',
                'response' => [
                    'message' => $response->getMessage(),
                    'success' => $response->isSuccess(),
                ],
            ]);
        } catch (Exception $e) {
            $logger?->error('Unexpected error in streaming process', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            self::sendUpdate([
                'type' => 'status',
                'status' => 'error',
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a status update via stdout
     */
    protected static function sendUpdate(array $data): void
    {
        // Ensure we have a type
        $data['type'] = $data['type'] ?? 'status';
        $data['timestamp'] = microtime(true);

        // Send as JSON line
        echo json_encode($data) . "\n";

        // Flush output immediately
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}

// Helper function
if (! function_exists('is_absolute_path')) {
    function is_absolute_path(string $path): bool
    {
        return $path[0] === '/' || preg_match('/^[A-Z]:\\\\/i', $path);
    }
}
