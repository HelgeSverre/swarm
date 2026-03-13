<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Application\Runtime;

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\CLI\CommandHandler;
use HelgeSverre\Swarm\CLI\Process\ProcessManager;
use HelgeSverre\Swarm\CLI\StateManager;
use HelgeSverre\Swarm\Contracts\UIInterface;
use HelgeSverre\Swarm\Core\Application;
use HelgeSverre\Swarm\Core\PathChecker;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI\Contracts\ClientContract;
use Psr\Log\LoggerInterface;

final class RuntimeContext
{
    public function __construct(
        public readonly RuntimeMode $mode,
        public readonly Application $app,
        public readonly EventBus $eventBus,
        public readonly ?LoggerInterface $logger,
        public readonly StateManager $stateManager,
        public readonly PathChecker $pathChecker,
        public readonly ToolExecutor $toolExecutor,
        public readonly TaskManager $taskManager,
        public readonly ClientContract $openAIClient,
        public readonly CodingAgent $codingAgent,
        public readonly ?CommandHandler $commandHandler = null,
        public readonly ?ProcessManager $processManager = null,
        public readonly ?UIInterface $ui = null,
    ) {}
}
