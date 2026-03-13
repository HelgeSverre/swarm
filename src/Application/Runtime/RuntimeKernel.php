<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Application\Runtime;

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\CLI\CommandHandler;
use HelgeSverre\Swarm\CLI\Process\ProcessManager;
use HelgeSverre\Swarm\CLI\StateManager;
use HelgeSverre\Swarm\Core\Application;
use HelgeSverre\Swarm\Core\LoggerRegistry;
use HelgeSverre\Swarm\Core\PathChecker;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI;

final class RuntimeKernel
{
    public static function bootCli(Application $app): RuntimeContext
    {
        return self::boot($app, RuntimeMode::Cli);
    }

    public static function bootWorker(Application $app): RuntimeContext
    {
        return self::boot($app, RuntimeMode::Worker);
    }

    private static function boot(Application $app, RuntimeMode $mode): RuntimeContext
    {
        self::initializeCompatibilityGlobals($app);

        $stateManager = new StateManager;
        $state = $stateManager->load();
        $allowedPaths = $state['allowed_directories'] ?? [];

        $pathChecker = new PathChecker($app->projectPath(), $allowedPaths);
        $eventBus = new EventBus;
        $toolExecutor = ToolExecutor::createWithDefaultTools(
            logger: $app->logger(),
            fileAccessPolicy: $pathChecker,
            eventBus: $eventBus,
        );
        $taskManager = new TaskManager($app->logger());

        $openAIClient = OpenAI::client($app->config('openai.api_key'));
        $codingAgent = new CodingAgent(
            toolExecutor: $toolExecutor,
            taskManager: $taskManager,
            llmClient: $openAIClient,
            logger: $app->logger(),
            model: $app->config('openai.model', 'gpt-4o-mini'),
            reasoningEffort: $app->config('openai.reasoning_effort', 'medium'),
            verbosity: $app->config('openai.verbosity', 'medium'),
            eventBus: $eventBus,
        );

        $commandHandler = null;
        $processManager = null;
        if ($mode === RuntimeMode::Cli) {
            $commandHandler = new CommandHandler($pathChecker, $stateManager);
            $processManager = new ProcessManager($app);
        }

        return new RuntimeContext(
            mode: $mode,
            app: $app,
            eventBus: $eventBus,
            logger: $app->logger(),
            stateManager: $stateManager,
            pathChecker: $pathChecker,
            toolExecutor: $toolExecutor,
            taskManager: $taskManager,
            openAIClient: $openAIClient,
            codingAgent: $codingAgent,
            commandHandler: $commandHandler,
            processManager: $processManager,
        );
    }

    private static function initializeCompatibilityGlobals(Application $app): void
    {
        LoggerRegistry::setLogger($app->logger());
    }
}
