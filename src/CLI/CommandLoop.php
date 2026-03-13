<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use Exception;
use HelgeSverre\Swarm\CLI\Command\CommandAction;
use HelgeSverre\Swarm\CLI\Process\ProcessManager;
use HelgeSverre\Swarm\Contracts\UIInterface;
use HelgeSverre\Swarm\Traits\Loggable;

/**
 * Runs the main event loop: input → command/async → poll → render → cleanup.
 */
class CommandLoop
{
    use Loggable;

    protected bool $running = false;

    /** @var array<string, array{input: string, startTime: float, conversationUpdated: bool}> */
    protected array $activeRequests = [];

    public function __construct(
        protected CommandHandler $commandHandler,
        protected ProcessManager $processManager,
        protected WorkerReconciler $reconciler,
        protected StateSyncAdapter $stateSync,
        protected UIInterface $ui,
    ) {}

    /**
     * Run the main event loop (blocking)
     */
    public function run(): void
    {
        $this->running = true;
        $this->logInfo('Starting async event loop');

        while ($this->running) {
            $input = $this->ui->checkForInput();
            if ($input !== null) {
                $this->logDebug('User input received in main loop', ['input' => $input]);
                $this->handleUserInput($input);
            }

            $this->reconciler->poll();

            $this->ui->render();

            $this->processManager->cleanupCompletedProcesses();

            usleep(50000);
        }
    }

    /**
     * Stop the event loop
     */
    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Route user input to a built-in command or async AI request
     */
    protected function handleUserInput(string $input): void
    {
        $result = $this->commandHandler->handle($input);

        if ($result->handled) {
            $this->processCommand($result);

            return;
        }

        $this->processRequestAsync($input);
    }

    /**
     * Dispatch a command result to the appropriate action
     */
    protected function processCommand(CommandResult $result): void
    {
        match ($result->action) {
            CommandAction::Exit => $this->stop(),
            CommandAction::SaveState => $this->handleSaveState(),
            CommandAction::ClearState => $this->handleClearState(),
            CommandAction::ClearHistory => $this->ui->refresh(['history' => []]),
            CommandAction::ShowHelp => $this->ui->showNotification($result->getMessage() ?? '', 'info'),
            CommandAction::Error => $this->ui->showNotification($result->getError() ?? 'Command failed', 'error'),
            default => null,
        };
    }

    protected function handleSaveState(): void
    {
        $this->stateSync->save();
        $this->ui->showNotification('State saved to .swarm.json', 'success');
    }

    protected function handleClearState(): void
    {
        try {
            if ($this->stateSync->clear()) {
                $this->ui->showNotification('State cleared', 'success');
            } else {
                $this->ui->showNotification('No saved state found', 'info');
            }
        } catch (Exception $e) {
            $this->logError('Failed to clear state', ['error' => $e->getMessage()]);
            $this->ui->showNotification('Failed to clear state: ' . $e->getMessage(), 'error');
        }
    }

    protected function processRequestAsync(string $input): void
    {
        try {
            $this->logInfo('User request received', [
                'input' => $input,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            $processId = $this->processManager->startProcess($input);

            $this->activeRequests[$processId] = [
                'input' => $input,
                'startTime' => microtime(true),
                'conversationUpdated' => false,
            ];

            $this->stateSync->addConversationMessage('user', $input);

            $this->ui->startProcessing();
            $this->stateSync->emitUpdate();
        } catch (Exception $e) {
            $this->logError('Request processing failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $this->ui->displayError($e->getMessage());
        }
    }

    /**
     * Remove a completed request from tracking
     */
    public function clearRequest(string $processId): void
    {
        unset($this->activeRequests[$processId]);
    }
}
