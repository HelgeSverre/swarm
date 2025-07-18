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
        // Use background processing mode by default
        $this->logger?->info('Starting with background processing mode');
        $this->runWithBackgroundProcessing();
    }

    protected function runWithBackgroundProcessing(): void
    {
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

                // Launch background processor
                $processor = new BackgroundProcessor($this->logger);
                $statusFile = $processor->launch($input);

                // Start processing animation
                $this->tui->startProcessing();

                // Wait for the status file to be created
                if (! $processor->waitForStatusFile(2.0)) {
                    throw new Exception('Background process failed to start');
                }

                // Update loop - show progress while background process runs or status exists
                $lastStatus = null;
                $lastUpdate = microtime(true);
                $maxWaitTime = 60; // Maximum 60 seconds
                $startTime = microtime(true);
                $processComplete = false;

                while (! $processComplete && microtime(true) - $startTime < $maxWaitTime) {
                    $now = microtime(true);

                    // Check for status updates
                    $status = $processor->getStatus();
                    if ($status) {
                        if ($status !== $lastStatus) {
                            $lastStatus = $status;

                            // Update UI with status
                            if (isset($status['message'])) {
                                $this->tui->updateProcessingMessage($status['message']);
                            }

                            // Log progress
                            $this->logger?->debug('Background process status', $status);

                            // Check if process is complete
                            if (isset($status['status']) && in_array($status['status'], ['completed', 'error'])) {
                                $processComplete = true;
                            }
                        }
                    }

                    // Update animation every 100ms
                    if ($now - $lastUpdate > 0.1) {
                        $this->tui->showProcessing();
                        $lastUpdate = $now;
                    }

                    // Small sleep to avoid busy waiting
                    usleep(50000); // 50ms
                }

                // Check timeout
                if (! $processComplete && microtime(true) - $startTime >= $maxWaitTime) {
                    throw new Exception('Process timeout');
                }

                // Get final status
                $finalStatus = $processor->getStatus() ?? $lastStatus;

                // Stop processing animation
                $this->tui->stopProcessing();

                // Handle result
                if ($finalStatus && $finalStatus['status'] === 'completed') {
                    $responseData = $finalStatus['response'] ?? [];
                    $response = new \HelgeSverre\Swarm\Agent\AgentResponse(
                        $responseData['message'] ?? '',
                        $responseData['success'] ?? true
                    );
                    $this->tui->displayResponse($response);
                } elseif ($finalStatus && $finalStatus['status'] === 'error') {
                    throw new Exception($finalStatus['error'] ?? 'Unknown error occurred');
                } else {
                    throw new Exception('Process terminated unexpectedly');
                }

                // Cleanup
                $processor->cleanup();
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
}
