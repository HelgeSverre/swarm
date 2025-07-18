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
    public static function processRequest(string $input, string $statusFile): void
    {
        try {
            // In child process - need to recreate necessary objects
            $projectRoot = dirname(__DIR__, 2);

            if (file_exists($projectRoot . '/.env')) {
                $dotenv = Dotenv::createImmutable($projectRoot);
                $dotenv->load();
            }

            $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
            $model = $_ENV['OPENAI_MODEL'] ?? 'gpt-4.1-mini';
            $temperature = (float) ($_ENV['OPENAI_TEMPERATURE'] ?? 0.7);

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

                $logPath = $_ENV['LOG_PATH'] ?? 'storage/logs';
                if (! is_dir($logPath)) {
                    mkdir($logPath, 0755, true);
                }

                $logger->pushHandler(
                    new RotatingFileHandler("{$logPath}/swarm.log", 7, $logLevel)
                );
            }

            $toolRouter = new ToolRouter($logger);
            ToolRegistry::registerAll($toolRouter);

            $taskManager = new TaskManager($logger);
            $llmClient = OpenAI::client($apiKey);

            $agent = new CodingAgent($toolRouter, $taskManager, $llmClient, $logger, $model, $temperature);

            // Log user request
            $logger?->info('User request received (async)', [
                'input' => $input,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            // Write initial status
            file_put_contents($statusFile, json_encode([
                'status' => 'processing',
                'message' => 'Starting request processing...',
                'timestamp' => time(),
            ]));

            // Process request
            $response = $agent->processRequest($input);

            // Write final status
            file_put_contents($statusFile, json_encode([
                'status' => 'completed',
                'response' => $response->toArray(),
                'timestamp' => time(),
            ]));
        } catch (Exception $e) {
            // Write error status
            file_put_contents($statusFile, json_encode([
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => time(),
            ]));

            throw $e;
        }
    }
}
