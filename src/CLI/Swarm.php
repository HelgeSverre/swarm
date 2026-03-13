<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use HelgeSverre\Swarm\Core\Application;
use HelgeSverre\Swarm\Core\Container;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\UserInputEvent;
use HelgeSverre\Swarm\Traits\Loggable;

/**
 * Top-level orchestrator.
 * Wires together the command loop, worker reconciler, and state-sync adapter,
 * then delegates all real work to them.
 */
class Swarm
{
    use Loggable;

    protected Container $container;

    protected StateSyncAdapter $stateSync;

    protected CommandLoop $commandLoop;

    protected EventBus $eventBus;

    public function __construct(
        protected Application $app,
        ?Container $container = null,
        ?StateSyncAdapter $stateSync = null,
        ?CommandLoop $commandLoop = null,
        ?EventBus $eventBus = null,
    ) {
        $this->container = $container ?? new Container($app);
        $this->eventBus = $eventBus ?? $this->container->getEventBus();

        $this->stateSync = $stateSync ?? new StateSyncAdapter(
            $this->container->getStateManager(),
            $this->eventBus,
        );

        $reconciler = new WorkerReconciler(
            $this->container->getProcessManager(),
            $this->stateSync,
            $this->eventBus,
            $this->container->getUI(),
        );

        $this->commandLoop = $commandLoop ?? new CommandLoop(
            $this->container->getCommandHandler(),
            $this->container->getProcessManager(),
            $reconciler,
            $this->stateSync,
            $this->container->getUI(),
        );

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
        $this->stateSync->load();

        // Restore conversation history to agent if available
        $conversationHistory = $this->stateSync->getConversationHistory();
        if (! empty($conversationHistory)) {
            $this->container->getCodingAgent()->setConversationHistory($conversationHistory);
        }

        // Restore task history to TaskManager if available
        $taskHistory = $this->stateSync->getTaskHistory();
        if (! empty($taskHistory)) {
            $this->container->getTaskManager()->setTaskHistory($taskHistory);
        }

        $this->stateSync->emitUpdate();

        $this->commandLoop->run();
    }

    // ── Lifecycle ────────────────────────────────────────────────

    public function saveStateOnShutdown(): void
    {
        if ($this->stateSync->hasContent()) {
            $this->persistTaskHistory();
            $this->stateSync->save();
        }
    }

    public function handleSignal(int $signal): void
    {
        $this->logInfo('Received signal, saving state', ['signal' => $signal]);
        $this->persistTaskHistory();
        $this->stateSync->save();
        $this->container->getUI()->cleanup();

        exit(0);
    }

    // ── Internal ─────────────────────────────────────────────────

    protected function setupEventListeners(): void
    {
        $this->eventBus->on(UserInputEvent::class, function (UserInputEvent $event) {
            // Forward to command loop via its public input path
            // (kept for event-driven input sources like tests)
        });
    }

    protected function registerShutdownHandlers(): void
    {
        register_shutdown_function([$this, 'saveStateOnShutdown']);

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_async_signals(true);
        }
    }

    protected function persistTaskHistory(): void
    {
        $taskManager = $this->container->getTaskManager();
        $this->stateSync->setTaskHistory($taskManager->getTaskHistory());
    }
}
