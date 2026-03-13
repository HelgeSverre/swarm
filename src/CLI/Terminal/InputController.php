<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

use Closure;

class InputController
{
    protected ?Closure $stopCallback = null;

    public function __construct(
        protected TerminalDriver $driver,
        protected TuiViewModel $viewModel,
    ) {}

    public function setStopCallback(Closure $callback): void
    {
        $this->stopCallback = $callback;
    }

    public function handleInput(string $key): void
    {
        if ($this->viewModel->isShowTaskOverlay()) {
            $this->handleTaskOverlayInput($key);
            return;
        }

        if ($this->viewModel->isShowHelp()) {
            $this->handleHelpInput($key);
            return;
        }

        switch ($this->viewModel->getCurrentFocus()) {
            case TuiViewModel::FOCUS_MAIN:
                $this->handleMainInput($key);
                break;
            case TuiViewModel::FOCUS_TASKS:
                $this->handleTasksInput($key);
                break;
            case TuiViewModel::FOCUS_CONTEXT:
                $this->handleContextInput($key);
                break;
        }
    }

    protected function handleMainInput(string $key): void
    {
        if (str_starts_with($key, 'ALT+')) {
            $this->handleGlobalShortcuts($key);
            return;
        }

        if ($key === 'TAB') {
            $this->viewModel->setCurrentFocus(TuiViewModel::FOCUS_TASKS);
            $this->viewModel->markStateChanged();
            return;
        }

        if (mb_strtoupper($key) === 'R' && $this->viewModel->getCurrentReasoning()) {
            $this->viewModel->setShowReasoning(! $this->viewModel->isShowReasoning());
            $this->viewModel->markStateChanged();
            return;
        }

        if ($key === "\n") {
            if (! empty($this->viewModel->getInput())) {
                $this->viewModel->addHistory('command', $this->viewModel->getInput());
                $this->viewModel->markStateChanged();
            }
        } elseif ($key === "\177" || $key === "\010") {
            $input = $this->viewModel->getInput();
            if (mb_strlen($input) > 0) {
                $this->viewModel->setInput(mb_substr($input, 0, -1));
                $this->viewModel->markStateChanged();
            }
        } elseif (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
            $this->viewModel->setInput($this->viewModel->getInput() . $key);
            $this->viewModel->markStateChanged();
        }
    }

    protected function handleTasksInput(string $key): void
    {
        if (str_starts_with($key, 'ALT+')) {
            $this->handleGlobalShortcuts($key);
            return;
        }

        switch ($key) {
            case 'TAB':
                $this->viewModel->setCurrentFocus(TuiViewModel::FOCUS_CONTEXT);
                $this->viewModel->markStateChanged();
                break;
            case 'UP':
            case 'k':
                if ($this->viewModel->getSelectedTaskIndex() > 0) {
                    $this->viewModel->setSelectedTaskIndex($this->viewModel->getSelectedTaskIndex() - 1);
                    $this->viewModel->markStateChanged();
                }
                break;
            case 'DOWN':
            case 'j':
                if ($this->viewModel->getSelectedTaskIndex() < count($this->viewModel->getTasks()) - 1) {
                    $this->viewModel->setSelectedTaskIndex($this->viewModel->getSelectedTaskIndex() + 1);
                    $this->viewModel->markStateChanged();
                }
                break;
            case "\n":
                $tasks = $this->viewModel->getTasks();
                if (isset($tasks[$this->viewModel->getSelectedTaskIndex()])) {
                    $task = $tasks[$this->viewModel->getSelectedTaskIndex()];
                    $this->viewModel->addHistory('command', "Switch to task: {$task['description']}");
                    $this->viewModel->setCurrentFocus(TuiViewModel::FOCUS_MAIN);
                    $this->viewModel->markStateChanged();
                }
                break;
            case 'ESC':
                $this->viewModel->setCurrentFocus(TuiViewModel::FOCUS_MAIN);
                $this->viewModel->markStateChanged();
                break;
        }

        if (mb_strlen($key) === 1 && $key >= '1' && $key <= '9') {
            $index = intval($key) - 1;
            if ($index < count($this->viewModel->getTasks())) {
                $this->viewModel->setSelectedTaskIndex($index);
                $this->viewModel->markStateChanged();
            }
        }
    }

    protected function handleContextInput(string $key): void
    {
        if (str_starts_with($key, 'ALT+')) {
            $this->handleGlobalShortcuts($key);
            return;
        }

        switch ($key) {
            case 'TAB':
                $this->viewModel->setCurrentFocus(TuiViewModel::FOCUS_MAIN);
                $this->viewModel->markStateChanged();
                break;
            case 'UP':
                if ($this->viewModel->getSelectedContextLine() > 0) {
                    $this->viewModel->setSelectedContextLine($this->viewModel->getSelectedContextLine() - 1);
                    $this->viewModel->markStateChanged();
                }
                break;
            case 'DOWN':
                $context = $this->viewModel->getContext();
                $totalLines = 3 + count($context['files']) + count($context['notes']) + 2;
                if ($this->viewModel->getSelectedContextLine() < $totalLines - 1) {
                    $this->viewModel->setSelectedContextLine($this->viewModel->getSelectedContextLine() + 1);
                    $this->viewModel->markStateChanged();
                }
                break;
            case "\n":
                $contextInput = $this->viewModel->getContextInput();
                if (! empty($contextInput)) {
                    $this->viewModel->addContextNote($contextInput);
                    $this->viewModel->setContextInput('');
                    $this->viewModel->addHistory('system', 'Added context note');
                    $this->viewModel->markStateChanged();
                }
                break;
            case "\177":
            case "\010":
                $context = $this->viewModel->getContext();
                $noteStart = 3 + count($context['files']) + 1;
                $noteIndex = $this->viewModel->getSelectedContextLine() - $noteStart;
                if ($noteIndex >= 0 && $noteIndex < count($context['notes'])) {
                    $this->viewModel->removeContextNote($noteIndex);
                    $this->viewModel->addHistory('system', 'Removed context note');
                    if ($this->viewModel->getSelectedContextLine() > 0) {
                        $this->viewModel->setSelectedContextLine($this->viewModel->getSelectedContextLine() - 1);
                    }
                } else {
                    $contextInput = $this->viewModel->getContextInput();
                    if (mb_strlen($contextInput) > 0) {
                        $this->viewModel->setContextInput(mb_substr($contextInput, 0, -1));
                    }
                }
                $this->viewModel->markStateChanged();
                break;
            case 'ESC':
                $this->viewModel->setCurrentFocus(TuiViewModel::FOCUS_MAIN);
                $this->viewModel->setContextInput('');
                $this->viewModel->markStateChanged();
                break;
        }

        if (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
            $this->viewModel->setContextInput($this->viewModel->getContextInput() . $key);
            $this->viewModel->markStateChanged();
        }
    }

    protected function handleGlobalShortcuts(string $key): bool
    {
        switch ($key) {
            case 'ALT+Q':
                if ($this->stopCallback) {
                    ($this->stopCallback)();
                }
                return true;
            case 'ALT+T':
                $this->viewModel->setShowTaskOverlay(! $this->viewModel->isShowTaskOverlay());
                $this->viewModel->markStateChanged();
                return true;
            case 'ALT+H':
                $this->viewModel->setShowHelp(true);
                $this->viewModel->markStateChanged();
                return true;
            case 'ALT+C':
                if ($this->viewModel->getCurrentFocus() === TuiViewModel::FOCUS_MAIN) {
                    $this->viewModel->clearHistory();
                    $this->viewModel->addHistory('system', 'History cleared');
                    $this->viewModel->markStateChanged();
                }
                return true;
            case 'ALT+R':
                $thoughtToggled = $this->viewModel->toggleNearestThought($this->driver->getWidth() - max(30, (int) ($this->driver->getWidth() * 0.25)) - 1);
                if (! $thoughtToggled) {
                    $this->driver->updateTerminalSize();
                    $this->viewModel->addHistory('system', 'Display refreshed');
                }
                $this->viewModel->markStateChanged();
                return true;
            case 'ALT+1':
                $this->viewModel->setCurrentFocus(TuiViewModel::FOCUS_MAIN);
                $this->viewModel->markStateChanged();
                return true;
            case 'ALT+2':
                $this->viewModel->setCurrentFocus(TuiViewModel::FOCUS_TASKS);
                $this->viewModel->markStateChanged();
                return true;
            case 'ALT+3':
                $this->viewModel->setCurrentFocus(TuiViewModel::FOCUS_CONTEXT);
                $this->viewModel->markStateChanged();
                return true;
        }

        return false;
    }

    protected function handleTaskOverlayInput(string $key): void
    {
        if ($key === 'ESC' || $key === 'ALT+T') {
            $this->viewModel->setShowTaskOverlay(false);
            $this->viewModel->markStateChanged();
            return;
        }

        switch ($key) {
            case 'UP':
            case 'k':
                if ($this->viewModel->getSelectedTaskIndex() > 0) {
                    $this->viewModel->setSelectedTaskIndex($this->viewModel->getSelectedTaskIndex() - 1);
                    $this->viewModel->adjustTaskScroll($this->driver->getHeight());
                    $this->viewModel->markStateChanged();
                }
                break;
            case 'DOWN':
            case 'j':
                if ($this->viewModel->getSelectedTaskIndex() < count($this->viewModel->getTasks()) - 1) {
                    $this->viewModel->setSelectedTaskIndex($this->viewModel->getSelectedTaskIndex() + 1);
                    $this->viewModel->adjustTaskScroll($this->driver->getHeight());
                    $this->viewModel->markStateChanged();
                }
                break;
            case "\n":
                $tasks = $this->viewModel->getTasks();
                if (isset($tasks[$this->viewModel->getSelectedTaskIndex()])) {
                    $task = $tasks[$this->viewModel->getSelectedTaskIndex()];
                    $this->viewModel->addHistory('command', "Switch to task: {$task['description']}");
                    $this->viewModel->setShowTaskOverlay(false);
                    $this->viewModel->markStateChanged();
                }
                break;
        }
    }

    protected function handleHelpInput(string $key): void
    {
        $this->viewModel->setShowHelp(false);
        $this->viewModel->markStateChanged();
    }
}
