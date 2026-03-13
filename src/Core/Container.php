<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Core;

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Application\Runtime\RuntimeContext;
use HelgeSverre\Swarm\Application\Runtime\RuntimeKernel;
use HelgeSverre\Swarm\CLI\CommandHandler;
use HelgeSverre\Swarm\CLI\Process\ProcessManager;
use HelgeSverre\Swarm\CLI\StateManager;
use HelgeSverre\Swarm\CLI\Terminal\FullTerminalUI;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI\Contracts\ClientContract;

/**
 * Service container for dependency management
 * Provides lazy initialization and centralized access to services
 */
class Container
{
    protected ?RuntimeContext $runtimeContext = null;

    protected ?CodingAgent $codingAgent = null;

    protected ?FullTerminalUI $ui = null;

    protected ?ToolExecutor $toolExecutor = null;

    protected ?TaskManager $taskManager = null;

    protected ?ClientContract $openAIClient = null;

    protected ?EventBus $eventBus = null;

    protected ?PathChecker $pathChecker = null;

    protected ?StateManager $stateManager = null;

    protected ?CommandHandler $commandHandler = null;

    protected ?ProcessManager $processManager = null;

    public function __construct(
        protected Application $app
    ) {}

    /**
     * Get or create the EventBus instance
     */
    public function getEventBus(): EventBus
    {
        if ($this->eventBus === null) {
            $this->eventBus = $this->getRuntimeContext()->eventBus;
        }

        return $this->eventBus;
    }

    /**
     * Get or create the OpenAI client
     */
    public function getOpenAIClient(): ClientContract
    {
        if ($this->openAIClient === null) {
            $this->openAIClient = $this->getRuntimeContext()->openAIClient;
        }

        return $this->openAIClient;
    }

    /**
     * Get or create the PathChecker
     */
    public function getPathChecker(): PathChecker
    {
        if ($this->pathChecker === null) {
            $this->pathChecker = $this->getRuntimeContext()->pathChecker;
        }

        return $this->pathChecker;
    }

    /**
     * Get or create the ToolExecutor
     */
    public function getToolExecutor(): ToolExecutor
    {
        if ($this->toolExecutor === null) {
            $this->toolExecutor = $this->getRuntimeContext()->toolExecutor;
        }

        return $this->toolExecutor;
    }

    /**
     * Get or create the TaskManager
     */
    public function getTaskManager(): TaskManager
    {
        if ($this->taskManager === null) {
            $this->taskManager = $this->getRuntimeContext()->taskManager;
        }

        return $this->taskManager;
    }

    /**
     * Get or create the CodingAgent
     */
    public function getCodingAgent(): CodingAgent
    {
        if ($this->codingAgent === null) {
            $this->codingAgent = $this->getRuntimeContext()->codingAgent;
        }

        return $this->codingAgent;
    }

    public function getStateManager(): StateManager
    {
        if ($this->stateManager === null) {
            $this->stateManager = $this->getRuntimeContext()->stateManager;
        }

        return $this->stateManager;
    }

    public function getCommandHandler(): CommandHandler
    {
        if ($this->commandHandler === null) {
            $this->commandHandler = $this->getRuntimeContext()->commandHandler
                ?? new CommandHandler($this->getPathChecker(), $this->getStateManager());
        }

        return $this->commandHandler;
    }

    public function getProcessManager(): ProcessManager
    {
        if ($this->processManager === null) {
            $this->processManager = $this->getRuntimeContext()->processManager
                ?? new ProcessManager($this->app);
        }

        return $this->processManager;
    }

    /**
     * Get or create the Terminal UI
     */
    public function getUI(): FullTerminalUI
    {
        if ($this->ui === null) {
            $this->ui = $this->getRuntimeContext()->ui instanceof FullTerminalUI
                ? $this->getRuntimeContext()->ui
                : new FullTerminalUI($this->getEventBus());
        }

        return $this->ui;
    }

    /**
     * Reset all services (useful for testing)
     */
    public function reset(): void
    {
        $this->runtimeContext = null;
        $this->codingAgent = null;
        $this->ui = null;
        $this->toolExecutor = null;
        $this->taskManager = null;
        $this->openAIClient = null;
        $this->eventBus = null;
        $this->pathChecker = null;
        $this->stateManager = null;
        $this->commandHandler = null;
        $this->processManager = null;
    }

    /**
     * Check if a service has been initialized
     */
    public function hasService(string $service): bool
    {
        return match ($service) {
            'agent' => $this->codingAgent !== null,
            'ui' => $this->ui !== null,
            'tools' => $this->toolExecutor !== null,
            'tasks' => $this->taskManager !== null,
            'openai' => $this->openAIClient !== null,
            'events' => $this->eventBus !== null,
            'state' => $this->stateManager !== null,
            'commands' => $this->commandHandler !== null,
            'processes' => $this->processManager !== null,
            default => false
        };
    }

    protected function getRuntimeContext(): RuntimeContext
    {
        if ($this->runtimeContext === null) {
            $this->runtimeContext = RuntimeKernel::bootCli($this->app);
        }

        return $this->runtimeContext;
    }
}
