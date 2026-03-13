<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\Contracts\UIInterface;
use HelgeSverre\Swarm\Events\EventBus;

class FullTerminalUI implements UIInterface
{
    protected TerminalDriver $driver;

    protected TuiViewModel $viewModel;

    protected InputController $inputController;

    protected TuiRenderer $renderer;

    public function __construct(EventBus $eventBus)
    {
        $this->driver = new TerminalDriver();
        $this->viewModel = new TuiViewModel($eventBus);
        $this->inputController = new InputController($this->driver, $this->viewModel);
        $this->renderer = new TuiRenderer($this->driver, $this->viewModel);

        $this->inputController->setStopCallback($this->stop(...));

        $this->driver->initializeTerminal();

        register_shutdown_function([$this, 'cleanup']);
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    public function prompt(string $label = '>'): string
    {
        while (true) {
            $input = $this->checkForInput();
            if ($input !== null) {
                return $input;
            }
            $this->render();
            usleep(16000);
        }
    }

    public function checkForInput(): ?string
    {
        $completedInput = null;
        $hasInput = false;

        while (($key = $this->driver->readKey()) !== null) {
            $hasInput = true;
            $this->inputController->handleInput($key);

            if ($key === "\n" && ! empty($this->viewModel->getInput())) {
                $completedInput = $this->viewModel->getInput();
                $this->viewModel->setInput('');
                $this->viewModel->markStateChanged();
                break;
            }
        }

        if ($hasInput) {
            $this->viewModel->markStateChanged();
        }

        return $completedInput;
    }

    public function render(bool $force = false): void
    {
        if (! $this->driver->isInitialized()) {
            return;
        }

        if ($force || $this->viewModel->consumeStateChanged()) {
            $this->renderer->render();
        }
    }

    public function stop(): void
    {
        $this->cleanup();
    }

    public function cleanup(): void
    {
        $this->driver->cleanup();
    }

    public function displayResponse(AgentResponse $response): void
    {
        $this->viewModel->displayResponse($response);
    }

    public function displayError(string $errorMessage): void
    {
        $this->viewModel->displayError($errorMessage);
    }

    public function showNotification(string $message, string $type = 'info'): void
    {
        $this->viewModel->showNotification($message, $type);
    }

    public function startProcessing(): void
    {
        $this->viewModel->startProcessing();
    }

    public function stopProcessing(): void
    {
        $this->viewModel->stopProcessing();
    }

    public function showProcessing(): void
    {
        $this->viewModel->showProcessing();
    }

    public function refresh(array $state = []): void
    {
        $this->viewModel->refresh($state);
    }

    public function updateProcessingMessage(string $message): void
    {
        $this->viewModel->updateProcessingMessage($message);
    }
}
