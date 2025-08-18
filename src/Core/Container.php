<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Core;

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\CLI\Terminal\FullTerminalUI;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI;
use RuntimeException;

/**
 * Service container for dependency management
 * Provides lazy initialization and centralized access to services
 */
class Container
{
    protected ?CodingAgent $codingAgent = null;

    protected ?FullTerminalUI $ui = null;

    protected ?ToolExecutor $toolExecutor = null;

    protected ?TaskManager $taskManager = null;

    protected ?OpenAI\Contracts\ClientContract $openAIClient = null;

    protected ?EventBus $eventBus = null;

    protected ?PathChecker $pathChecker = null;

    public function __construct(
        protected Application $app
    ) {}

    /**
     * Get or create the EventBus instance
     */
    public function getEventBus(): EventBus
    {
        if ($this->eventBus === null) {
            // Use the singleton instance
            $this->eventBus = EventBus::getInstance();
        }

        return $this->eventBus;
    }

    /**
     * Get or create the OpenAI client
     */
    public function getOpenAIClient(): OpenAI\Contracts\ClientContract
    {
        if ($this->openAIClient === null) {
            $apiKey = $this->app->config('openai.api_key');
            if (! $apiKey) {
                throw new RuntimeException('OpenAI API key not configured');
            }
            $this->openAIClient = OpenAI::client($apiKey);
        }

        return $this->openAIClient;
    }

    /**
     * Get or create the PathChecker
     */
    public function getPathChecker(): PathChecker
    {
        if ($this->pathChecker === null) {
            // Load allowed directories from state
            $stateManager = new \HelgeSverre\Swarm\CLI\StateManager;
            $state = $stateManager->load();
            $allowedPaths = $state['allowed_directories'] ?? [];

            $this->pathChecker = new PathChecker(
                $this->app->projectPath(),
                $allowedPaths
            );
        }

        return $this->pathChecker;
    }

    /**
     * Get or create the ToolExecutor
     */
    public function getToolExecutor(): ToolExecutor
    {
        if ($this->toolExecutor === null) {
            // ToolExecutor now uses traits for EventBus and Logger
            $this->toolExecutor = ToolExecutor::createWithDefaultTools(
                $this->app->logger(),
                $this->getPathChecker()
            );
        }

        return $this->toolExecutor;
    }

    /**
     * Get or create the TaskManager
     */
    public function getTaskManager(): TaskManager
    {
        if ($this->taskManager === null) {
            $this->taskManager = new TaskManager($this->app->logger());
        }

        return $this->taskManager;
    }

    /**
     * Get or create the CodingAgent
     */
    public function getCodingAgent(): CodingAgent
    {
        if ($this->codingAgent === null) {
            $this->codingAgent = new CodingAgent(
                toolExecutor: $this->getToolExecutor(),
                taskManager: $this->getTaskManager(),
                llmClient: $this->getOpenAIClient(),
                logger: $this->app->logger(),
                model: $this->app->config('openai.model', 'gpt-4o-mini'),
                temperature: $this->app->config('openai.temperature', 0.7)
            );
        }

        return $this->codingAgent;
    }

    /**
     * Get or create the Terminal UI
     */
    public function getUI(): FullTerminalUI
    {
        if ($this->ui === null) {
            $this->ui = new FullTerminalUI($this->getEventBus());
        }

        return $this->ui;
    }

    /**
     * Reset all services (useful for testing)
     */
    public function reset(): void
    {
        $this->codingAgent = null;
        $this->ui = null;
        $this->toolExecutor = null;
        $this->taskManager = null;
        $this->openAIClient = null;
        $this->eventBus = null;
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
            default => false
        };
    }
}
