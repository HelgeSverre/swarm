<?php

namespace HelgeSverre\Swarm\CLI;

use Dotenv\Dotenv;
use Exception;
use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\ToolRegistry;
use HelgeSverre\Swarm\Core\ToolRouter;
use HelgeSverre\Swarm\Task\TaskManager;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use OpenAI;

/**
 * Async processor that can be serialized and run in a child process
 */
class AsyncProcessor
{
    protected static string $statusFile;

    /**
     * Process a request in the background
     */
    public static function processRequest(string $input, string $statusFile): void
    {
        self::$statusFile = $statusFile;

        try {
            // Write initial status
            self::updateStatus([
                'status' => 'initializing',
                'operation' => 'setup',
                'message' => 'Initializing agent...',
                'progress' => 0.0,
            ]);

            // In child process - need to recreate necessary objects
            $projectRoot = dirname(__DIR__, 2);

            if (file_exists($projectRoot . '/.env')) {
                $dotenv = Dotenv::createImmutable($projectRoot);
                $dotenv->load();
            }

            $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
            $model = $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini';
            $temperature = (float) ($_ENV['OPENAI_TEMPERATURE'] ?? 0.7);

            // Setup logger
            $logger = null;
            if ($_ENV['LOG_ENABLED'] ?? false) {
                $logger = new Logger('swarm');
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

                $logPath = $_ENV['LOG_PATH'] ?? 'logs';
                if (! is_dir($logPath)) {
                    mkdir($logPath, 0755, true);
                }

                $logger->pushHandler(
                    new RotatingFileHandler("{$logPath}/swarm.log", 7, $logLevel)
                );
            }

            self::updateStatus([
                'status' => 'initializing',
                'operation' => 'setup',
                'message' => 'Setting up tools and agent...',
                'progress' => 0.1,
            ]);

            // Create tool router with progress callback
            $toolRouter = new ToolRouter($logger);

            // Add progress callback to tool router
            $toolRouter->setProgressCallback(function ($tool, $params, $status) {
                self::updateStatus([
                    'status' => 'processing',
                    'operation' => 'tool_execution',
                    'message' => "Executing tool: {$tool}",
                    'tool' => $tool,
                    'tool_status' => $status,
                    'progress' => 0.5,
                ]);
            });

            ToolRegistry::registerAll($toolRouter);

            $taskManager = new TaskManager($logger);
            $llmClient = OpenAI::client($apiKey);

            // Create agent with progress callback
            $agent = new CodingAgent($toolRouter, $taskManager, $llmClient, $logger, $model, $temperature);

            // Set progress callback on agent
            $agent->setProgressCallback(function ($operation, $details) {
                $progress = match ($operation) {
                    'classifying' => 0.2,
                    'extracting_tasks' => 0.3,
                    'planning_task' => 0.4,
                    'executing_task' => 0.6,
                    'calling_openai' => 0.7,
                    'generating_summary' => 0.9,
                    default => 0.5,
                };

                self::updateStatus([
                    'status' => 'processing',
                    'operation' => $operation,
                    'message' => $details['message'] ?? "Processing: {$operation}",
                    'details' => $details,
                    'progress' => $progress,
                ]);
            });

            // Log user request
            $logger?->info('User request received (async)', [
                'input' => $input,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            self::updateStatus([
                'status' => 'processing',
                'operation' => 'request_processing',
                'message' => 'Processing your request...',
                'progress' => 0.2,
            ]);

            // Process request
            $response = $agent->processRequest($input);

            // Write final status
            self::updateStatus([
                'status' => 'completed',
                'operation' => 'done',
                'message' => 'Request completed successfully',
                'response' => $response->toArray(),
                'progress' => 1.0,
            ]);
        } catch (Exception $e) {
            // Write error status
            self::updateStatus([
                'status' => 'error',
                'operation' => 'error',
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'progress' => 0.0,
            ]);

            throw $e;
        }
    }

    /**
     * Write a status update to the status file
     */
    protected static function updateStatus(array $status): void
    {
        $status['timestamp'] = microtime(true);
        $content = json_encode($status, JSON_PRETTY_PRINT);

        // Write atomically to avoid partial reads
        $tempFile = self::$statusFile . '.tmp';
        file_put_contents($tempFile, $content);
        rename($tempFile, self::$statusFile);
    }
}
