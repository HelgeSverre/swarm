<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use Exception;
use HelgeSverre\Swarm\CLI\Command\CommandAction;
use HelgeSverre\Swarm\CLI\Process\ProcessManager;
use HelgeSverre\Swarm\CLI\Process\Message\WorkerUpdateType;
use HelgeSverre\Swarm\Core\Application;
use HelgeSverre\Swarm\Core\Container;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ProcessCompleteEvent;
use HelgeSverre\Swarm\Events\ProcessProgressEvent;
use HelgeSverre\Swarm\Events\StateUpdateEvent;
use HelgeSverre\Swarm\Events\UserInputEvent;
use HelgeSverre\Swarm\Traits\Loggable;

/**
 * Refactored Swarm orchestrator
 * Coordinates between components but delegates all work to specialized managers
 */
class Swarm
{
    use Loggable;

    protected Container $container;

    protected StateManager $stateManager;

    protected CommandHandler $commandHandler;

    protected ProcessManager $processManager;

    protected EventBus $eventBus;

    protected bool $running = false;

    protected array $syncedState = [];

    protected array $activeRequests = [];

    public function __construct(
        protected Application $app,
        ?Container $container = null,
        ?StateManager $stateManager = null,
        ?CommandHandler $commandHandler = null,
        ?ProcessManager $processManager = null,
        ?EventBus $eventBus = null,
    ) {
        $this->container = $container ?? new Container($app);
        $this->stateManager = $stateManager ?? $this->container->getStateManager();
        $this->commandHandler = $commandHandler ?? $this->container->getCommandHandler();
        $this->processManager = $processManager ?? $this->container->getProcessManager();
        $this->eventBus = $eventBus ?? $this->container->getEventBus();

        $this->setupEventListeners();
        $this->registerShutdownHandlers();
    }

    /**
     * Factory method to create from environment
     */
    public static function createFromEnvironment(Application $app): self
    {
        return new self($app);
    }

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

        // Start the main event loop
        $this->running = true;
        $this->logInfo('Starting async event loop');
        $ui = $this->container->getUI();

        $loopIterations = 0;
        while ($this->running) {
            $loopIterations++;

            // Commented out to reduce log spam
            // if ($loopIterations % 20 === 0) {
            //     $this->logDebug('Main loop running', [
            //         'iterations' => $loopIterations,
            //         'active_requests' => count($this->activeRequests),
            //         'has_active_processes' => $this->processManager->hasActiveProcesses(),
            //     ]);
            // }

            // Handle any user input (non-blocking)
            $input = $ui->checkForInput();
            if ($input !== null) {
                $this->logDebug('User input received in main loop', ['input' => $input]);
                $this->handleUserInput($input);
            }

            // Poll all active processes for updates
            $this->pollActiveProcesses();

            // Update UI with any new state
            $ui->render();

            // Cleanup completed processes
            $this->processManager->cleanupCompletedProcesses();

            // Small sleep to prevent busy waiting
            usleep(50000);
        }
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
        $this->eventBus->on(UserInputEvent::class, function (UserInputEvent $event) {
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
            case CommandAction::Exit:
                $this->shutdown();
                break;
            case CommandAction::SaveState:
                $this->saveState();
                $this->container->getUI()->showNotification('State saved to .swarm.json', 'success');
                break;
            case CommandAction::ClearState:
                $this->clearState();
                break;
            case CommandAction::ClearHistory:
                // Clear history in UI
                $this->container->getUI()->refresh(['history' => []]);
                break;
            case CommandAction::ShowHelp:
                $this->container->getUI()->showNotification($result->getMessage() ?? '', 'info');
                break;
            case CommandAction::Error:
                $this->container->getUI()->showNotification($result->getError() ?? 'Command failed', 'error');
                break;
        }
    }

    protected function processRequestAsync(string $input): void
    {
        try {
            $this->logInfo('User request received', [
                'input' => $input,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            // Start async processing (non-blocking!)
            $processId = $this->processManager->startProcess($input);

            $this->activeRequests[$processId] = [
                'input' => $input,
                'startTime' => microtime(true),
                'conversationUpdated' => false,
            ];

            // Add to conversation history immediately
            $this->syncedState['conversation_history'][] = [
                'role' => 'user',
                'content' => $input,
                'timestamp' => time(),
            ];

            // Show processing started
            $this->container->getUI()->startProcessing();
            $this->emitStateUpdate();
        } catch (Exception $e) {
            $this->logError('Request processing failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $this->container->getUI()->displayError($e->getMessage());
        }
    }

    protected function pollActiveProcesses(): void
    {
        $updates = $this->processManager->pollUpdates();

        if (! empty($updates)) {
            $this->logDebug('Polling found updates', ['update_count' => count($updates)]);
        }

        foreach ($updates as $update) {
            $payload = $update->toArray();
            $processId = $update->processId ?? '';

            $this->logDebug('Processing update from worker', [
                'processId' => $processId,
                'type' => $update->type->value,
                'status' => $update->status(),
            ]);

            // Emit progress events for UI updates
            $this->eventBus->emit(new ProcessProgressEvent(
                processId: $processId,
                type: $update->type->value,
                data: $payload
            ));

            // Handle state_sync updates to update task list
            if ($update->type === WorkerUpdateType::StateSync && isset($payload['data'])) {
                $data = $payload['data'];

                // Update synced state with task data
                if (isset($data['tasks'])) {
                    $this->syncedState['tasks'] = $data['tasks'];
                }
                if (isset($data['current_task'])) {
                    // current_task from agent is an array, extract description for StateUpdateEvent
                    if (is_array($data['current_task'])) {
                        $this->syncedState['current_task'] = $data['current_task']['description'] ?? null;
                    } else {
                        $this->syncedState['current_task'] = $data['current_task'];
                    }
                }
                if (isset($data['operation'])) {
                    $this->syncedState['operation'] = $data['operation'];
                }

                // Emit state update so UI refreshes task list
                $this->emitStateUpdate();
            }

            // Handle completion
            if ($update->isCompletedStatus()) {
                $this->handleProcessComplete($processId, $payload);
            }
        }
    }

    protected function handleProcessComplete(string $processId, array $update): void
    {
        $result = $this->processManager->getProcessResult($processId);

        if ($result && $result->success) {
            $response = $result->getAgentResponse();
            if ($response) {
                // Add to conversation history
                $this->syncedState['conversation_history'][] = [
                    'role' => 'assistant',
                    'content' => $response->getMessage(),
                    'timestamp' => time(),
                ];

                // UI will update via events
                $this->eventBus->emit(new ProcessCompleteEvent($processId, $response));
            }
        }

        // Cleanup
        unset($this->activeRequests[$processId]);
        $this->container->getUI()->stopProcessing();
        $this->saveState();
        $this->emitStateUpdate();
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
        $this->eventBus->emit(new StateUpdateEvent(
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
