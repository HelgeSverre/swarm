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
use Spatie\Async\Pool;

class SwarmCLI
{
    protected readonly CodingAgent $agent;

    protected readonly TUIRenderer $tui;

    protected readonly ?Logger $logger;

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
        $model = $_ENV['OPENAI_MODEL'] ?? 'gpt-4.1-mini';
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
            $logPath = $_ENV['LOG_PATH'] ?? 'storage/logs';
            if (! is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }

            $logger->pushHandler(
                new RotatingFileHandler("{$logPath}/swarm.log", 7, $logLevel)
            );

            // Never log to console as it interferes with TUI rendering
            // All logs go to file only
        }

        $toolRouter = new ToolRouter($logger);
        ToolRegistry::registerAll($toolRouter);

        $taskManager = new TaskManager($logger);
        $llmClient = OpenAI::client($apiKey);

        $this->logger = $logger;
        $this->agent = new CodingAgent($toolRouter, $taskManager, $llmClient, $logger, $model, $temperature);
        $this->tui = new TUIRenderer;
    }

    public function run(): void
    {
        // Check if async is supported
        if (! Pool::isSupported()) {
            $this->logger?->warning('Async processing not supported, falling back to synchronous mode');
            $this->runSynchronous();

            return;
        }

        // For now, use synchronous mode as async needs more work
        $this->logger?->info('Using synchronous mode for stability');
        $this->runSynchronous();

        /* Async implementation - disabled for now due to serialization issues
        while (true) {
            $this->tui->refresh($this->agent->getStatus());

            $input = $this->tui->prompt('>');

            if ($input === 'exit' || $input === 'quit') {
                break;
            }

            $this->runAsync($input);
        }
        */
    }

    protected function runSynchronous(): void
    {
        // Original synchronous implementation as fallback
        while (true) {
            $this->tui->refresh($this->agent->getStatus());

            $input = $this->tui->prompt('>');

            if ($input === 'exit' || $input === 'quit') {
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

            try {
                // Start processing animation
                $this->tui->startProcessing();

                // Log user request
                $this->logger?->info('User request received', [
                    'input' => $input,
                    'timestamp' => date('Y-m-d H:i:s'),
                ]);

                $response = $this->agent->processRequest($input);

                // Stop processing animation
                $this->tui->stopProcessing();

                $this->tui->displayResponse($response);
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
                $this->tui->displayError($e->getMessage());
            }
        }
    }

    protected function showHelp(): void
    {
        $this->tui->showNotification('Commands: exit, quit, clear, help', 'info');
    }

    protected function runAsync(string $input): void
    {
        // Start processing animation
        $this->tui->startProcessing();

        // Create async pool
        $pool = Pool::create()
            ->autoload(dirname(__DIR__, 2) . '/vendor/autoload.php')
            ->concurrency(1) // Single process for agent
            ->timeout(300); // 5 minute timeout

        // Create status file for IPC
        $statusFile = sys_get_temp_dir() . '/swarm_status_' . uniqid() . '.json';

        $pool->add(function () use ($input, $statusFile) {
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

            // Process request
            $response = $agent->processRequest($input);

            // Write status updates during processing
            file_put_contents($statusFile, json_encode([
                'status' => 'completed',
                'response' => $response->toArray(),
            ]));

            return $response;
        })
            ->then(function ($response) use ($statusFile) {
                // Success handler
                $this->tui->stopProcessing();
                $this->tui->displayResponse($response);

                // Clean up status file
                if (file_exists($statusFile)) {
                    unlink($statusFile);
                }
            })
            ->catch(function (Exception $e) use ($statusFile) {
                // Error handler
                $this->tui->stopProcessing();

                $this->logger?->error('Request processing failed (async)', [
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->tui->displayError($e->getMessage());

                // Clean up status file
                if (file_exists($statusFile)) {
                    unlink($statusFile);
                }
            });

        // Animation update loop while waiting
        $lastUpdate = microtime(true);
        while (! $pool->isTerminated()) {
            $now = microtime(true);
            if ($now - $lastUpdate > 0.1) { // Update every 100ms
                $this->tui->showProcessing();
                $lastUpdate = $now;

                // Check for status updates from child process
                if (file_exists($statusFile)) {
                    $status = json_decode(file_get_contents($statusFile), true);
                    // Could update UI with status here
                }
            }

            usleep(50000); // 50ms sleep

            // Process pool events
            $pool->wait();
        }
    }
}
