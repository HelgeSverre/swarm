<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use Exception;
use HelgeSverre\Swarm\CLI\Process\ProcessManager;
use HelgeSverre\Swarm\Core\Application;
use HelgeSverre\Swarm\Core\Container;
use HelgeSverre\Swarm\Core\LoggerRegistry;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\StateUpdateEvent;
use HelgeSverre\Swarm\Events\UserInputEvent;
use HelgeSverre\Swarm\Traits\EventAware;
use HelgeSverre\Swarm\Traits\Loggable;

/**
 * Refactored Swarm orchestrator
 * Coordinates between components but delegates all work to specialized managers
 */
class Swarm
{
    use EventAware, Loggable;

    private Container $container;

    private StateManager $stateManager;

    private CommandHandler $commandHandler;

    private ProcessManager $processManager;

    private bool $running = false;

    private array $syncedState = [];

    public function __construct(
        private Application $app,
        ?Container $container = null,
        ?StateManager $stateManager = null,
        ?CommandHandler $commandHandler = null,
        ?ProcessManager $processManager = null
    ) {
        $this->container = $container ?? new Container($app);
        $this->stateManager = $stateManager ?? new StateManager;
        $this->commandHandler = $commandHandler ?? new CommandHandler;
        $this->processManager = $processManager ?? new ProcessManager;

        $this->setupEventListeners();
        $this->registerShutdownHandlers();
    }

    /**
     * Factory method to create from environment
     */
    public static function createFromEnvironment(Application $app): self
    {
        // Setup LoggerRegistry from Application
        LoggerRegistry::setLogger($app->logger());

        // Set the EventBus instance to ensure all components use the same one
        EventBus::setInstance(EventBus::getInstance());

        return new self($app);
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        // Load saved state
        $this->syncedState = $this->stateManager->load();

        // Restore conversation history to agent if available
        if (! empty($this->syncedState['conversation_history'])) {
            $agent = $this->container->getCodingAgent();
            $agent->setConversationHistory($this->syncedState['conversation_history']);
        }

        // Restore task history to TaskManager if available
        if (! empty($this->syncedState['task_history'])) {
            $taskManager = $this->container->getTaskManager();
            $taskManager->setTaskHistory($this->syncedState['task_history']);
        }

        // Emit initial state
        $this->emitStateUpdate();

        // Start the UI event loop
        $this->running = true;
        $this->logInfo('Starting event-driven UI');
        $this->container->getUI()->run();
    }

    /**
     * Handle shutdown to save state
     */
    public function saveStateOnShutdown(): void
    {
        // Only save if we have some state to save
        if (! empty($this->syncedState['conversation_history']) ||
            ! empty($this->syncedState['tasks']) ||
            ! empty($this->syncedState['tool_log'])) {
            $this->saveState();
        }
    }

    /**
     * Handle signals for graceful shutdown
     */
    public function handleSignal(int $signal): void
    {
        $this->logInfo('Received signal, saving state', ['signal' => $signal]);
        $this->saveState();

        // Cleanup UI
        $this->container->getUI()->cleanup();

        exit(0);
    }

    /**
     * Setup event listeners
     */
    protected function setupEventListeners(): void
    {
        // Handle user input events
        $this->subscribe(UserInputEvent::class, function (UserInputEvent $event) {
            $this->handleUserInput($event->input);
        });
    }

    /**
     * Register shutdown handlers
     */
    protected function registerShutdownHandlers(): void
    {
        // Register shutdown handler for saving state
        register_shutdown_function([$this, 'saveStateOnShutdown']);

        // Register signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_async_signals(true);
        }
    }

    /**
     * Handle user input
     */
    protected function handleUserInput(string $input): void
    {
        // Try built-in commands first
        $result = $this->commandHandler->handle($input);

        if ($result->handled) {
            $this->processCommand($result);

            return;
        }

        // Process with AI agent
        $this->processRequestAsync($input);
    }

    /**
     * Process a command result
     */
    protected function processCommand(CommandResult $result): void
    {
        switch ($result->action) {
            case 'exit':
                $this->shutdown();
                break;
            case 'save_state':
                $this->saveState();
                $this->container->getUI()->showNotification('State saved to .swarm.json', 'success');
                break;
            case 'clear_state':
                $this->clearState();
                break;
            case 'clear_history':
                // Clear history in UI
                $this->container->getUI()->refresh(['history' => []]);
                break;
            case 'show_help':
                $this->container->getUI()->showNotification($result->getMessage() ?? '', 'info');
                break;
            case 'error':
                $this->container->getUI()->showNotification($result->getError() ?? 'Command failed', 'error');
                break;
        }
    }

    /**
     * Process request with AI agent asynchronously
     */
    protected function processRequestAsync(string $input): void
    {
        try {
            $this->logInfo('User request received', [
                'input' => $input,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            // Add to conversation history
            $this->syncedState['conversation_history'][] = [
                'role' => 'user',
                'content' => $input,
                'timestamp' => time(),
            ];

            // Start processing animation
            $ui = $this->container->getUI();
            $ui->startProcessing();

            // Launch background process
            $result = $this->processManager->launch($input);

            // Stop processing animation
            $ui->stopProcessing();

            if ($result->success) {
                // Display response
                $response = $result->getAgentResponse();
                if ($response) {
                    $ui->displayResponse($response);

                    // Add to conversation history
                    $this->syncedState['conversation_history'][] = [
                        'role' => 'assistant',
                        'content' => $response->getMessage(),
                        'timestamp' => time(),
                    ];
                }

                // Update state from agent
                $this->updateStateFromAgent();

                // Save state after successful completion
                $this->saveState();
            } else {
                $ui->displayError($result->error ?? 'Request failed');

                if (str_contains($result->error ?? '', 'timed out')) {
                    $ui->showNotification(
                        'The request timed out. You can retry with a longer timeout or simplify your request.',
                        'warning'
                    );
                }
            }
        } catch (Exception $e) {
            $this->logError('Request processing failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $this->container->getUI()->displayError($e->getMessage());
        }
    }

    /**
     * Update state from agent after task completion
     */
    protected function updateStateFromAgent(): void
    {
        $agent = $this->container->getCodingAgent();
        $taskManager = $this->container->getTaskManager();

        // Clear completed tasks
        $taskManager->clearCompletedTasks();

        // Update synced state
        $this->syncedState['tasks'] = $taskManager->getTasksAsArrays();
        $this->syncedState['task_history'] = $taskManager->getTaskHistory();
        $this->syncedState['current_task'] = null;
        $this->syncedState['operation'] = '';

        // Refresh UI
        $this->container->getUI()->refresh($this->syncedState);
        $this->emitStateUpdate();
    }

    /**
     * Emit state update event
     */
    protected function emitStateUpdate(): void
    {
        $this->emit(new StateUpdateEvent(
            tasks: $this->syncedState['tasks'] ?? [],
            currentTask: $this->syncedState['current_task'] ?? null,
            conversationHistory: $this->syncedState['conversation_history'] ?? [],
            toolLog: $this->syncedState['tool_log'] ?? [],
            context: [],
            status: $this->syncedState['operation'] ?? 'ready'
        ));
    }

    /**
     * Save current state
     */
    protected function saveState(): void
    {
        // Update task history from TaskManager before saving
        $taskManager = $this->container->getTaskManager();
        $this->syncedState['task_history'] = $taskManager->getTaskHistory();

        $this->stateManager->save($this->syncedState);
    }

    /**
     * Clear state
     */
    protected function clearState(): void
    {
        try {
            if ($this->stateManager->clear()) {
                $this->syncedState = $this->stateManager->reset();
                $this->container->getUI()->showNotification('State cleared', 'success');
            } else {
                $this->container->getUI()->showNotification('No saved state found', 'info');
            }
        } catch (Exception $e) {
            $this->logError('Failed to clear state', ['error' => $e->getMessage()]);
            $this->container->getUI()->showNotification('Failed to clear state: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Shutdown the application
     */
    protected function shutdown(): void
    {
        $this->running = false;
        $this->saveState();
        $this->container->getUI()->stop();
    }
}
