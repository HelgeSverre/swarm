<?php

namespace HelgeSverre\Swarm\CLI;

use Dotenv\Dotenv;
use Exception;
use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Router\ToolRouter;
use HelgeSverre\Swarm\Task\TaskManager;
use HelgeSverre\Swarm\Tools\ToolRegistry;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use OpenAI;

class SwarmCLI
{
    protected $agent;

    protected $tui;
    
    protected $logger;

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

        $this->agent = new CodingAgent($toolRouter, $taskManager, $llmClient, $logger);
        $this->tui = new TUIRenderer;
    }

    public function run(): void
    {
        //        $this->tui->showWelcome();

        while (true) {
            $this->tui->refresh($this->agent->getStatus());

            $input = $this->tui->prompt('🤖 What would you like me to help you with?');

            if ($input === 'exit' || $input === 'quit') {
                break;
            }

            try {
                // Log user request
                $logger?->info('User request received', [
                    'input' => $input,
                    'timestamp' => date('Y-m-d H:i:s'),
                ]);

                $response = $this->agent->processRequest($input);
                $this->tui->displayResponse($response);
            } catch (Exception $e) {
                $logger?->error('Request processing failed', [
                    'error' => $e->getMessage(),
                    'input' => $input,
                ]);
                $this->tui->displayError($e->getMessage());
            }
        }
    }
}
