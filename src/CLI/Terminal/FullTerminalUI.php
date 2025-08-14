<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ProcessingEvent;
use HelgeSverre\Swarm\Events\StateUpdateEvent;
use HelgeSverre\Swarm\Events\TaskUpdateEvent;
use HelgeSverre\Swarm\Events\ToolCompletedEvent;
use HelgeSverre\Swarm\Events\ToolStartedEvent;
use HelgeSverre\Swarm\Events\UserInputEvent;

class FullTerminalUI
{
    // Focus modes
    const FOCUS_MAIN = 'main';

    const FOCUS_TASKS = 'tasks';

    const FOCUS_CONTEXT = 'context';

    protected EventBus $eventBus;

    protected bool $running = false;

    protected bool $stateChanged = false;

    // UI State
    protected array $history = [];

    protected array $expandedThoughts = [];

    protected array $tasks = [];

    protected array $context = [
        'directory' => '',
        'files' => [],
        'tools' => [],
        'notes' => [],
    ];

    protected string $currentTask = '';

    protected string $status = 'ready';

    protected int $currentStep = 0;

    protected int $totalSteps = 0;

    protected bool $showTaskOverlay = false;

    protected bool $showHelp = false;

    protected int $selectedTaskIndex = 0;

    protected int $selectedContextLine = 0;

    protected int $taskScrollOffset = 0;

    // Terminal dimensions
    protected int $terminalHeight;

    protected int $terminalWidth;

    protected int $mainAreaWidth;

    protected int $sidebarWidth;

    // Input state
    protected string $input = '';

    protected string $contextInput = '';

    protected string $currentFocus = self::FOCUS_MAIN;

    // Platform detection
    protected bool $isMacOS;

    protected string $modKey;

    protected string $modSymbol;

    protected float $startTime;

    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
        $this->startTime = microtime(true);
        $this->detectOS();
        $this->updateTerminalSize();

        // Calculate layout dimensions
        $this->sidebarWidth = max(30, (int) ($this->terminalWidth * 0.25));
        $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;

        // Subscribe to events
        $this->subscribeToEvents();
    }

    /**
     * Main event loop
     */
    public function run(): void
    {
        // Set up terminal for raw mode
        system('stty -echo -icanon min 1 time 0');
        stream_set_blocking(STDIN, false);

        // Hide cursor initially
        echo "\033[?25l";

        // Clear screen
        $this->clearScreen();

        $this->running = true;
        $this->render();

        while ($this->running) {
            $key = $this->readKey();

            if ($key !== null) {
                $this->handleInput($key);
            }

            // Re-render if state changed
            if ($this->stateChanged) {
                $this->render();
                $this->stateChanged = false;
            }

            usleep(50000); // 50ms delay
        }
    }

    /**
     * Stop the UI
     */
    public function stop(): void
    {
        $this->running = false;
        $this->cleanup();
    }

    /**
     * Cleanup on shutdown
     */
    public function cleanup(): void
    {
        // Restore terminal
        system('stty echo icanon');
        echo "\033[?25h"; // Show cursor
        $this->clearScreen();
        echo "Goodbye!\n";
    }

    /**
     * Public methods for external updates
     */
    public function displayResponse(AgentResponse $response): void
    {
        $this->addHistory('assistant', $response->getMessage());
        $this->stateChanged = true;
    }

    public function displayError(string $errorMessage): void
    {
        $this->addHistory('error', $errorMessage);
        $this->stateChanged = true;
    }

    public function showNotification(string $message, string $type = 'info'): void
    {
        $this->addHistory($type, $message);
        $this->stateChanged = true;
    }

    /**
     * Processing state methods for compatibility
     */
    public function startProcessing(): void
    {
        $this->status = 'Processing...';
        $this->stateChanged = true;
    }

    public function stopProcessing(): void
    {
        $this->status = 'Ready';
        $this->stateChanged = true;
    }

    public function showProcessing(): void
    {
        // Animation tick - could update a spinner
        // For now, just ensure UI refreshes if needed
    }

    public function updateProcessingMessage(string $message): void
    {
        $this->status = $message;
        $this->stateChanged = true;
    }

    /**
     * Refresh method for compatibility
     */
    public function refresh(array $status): void
    {
        // Convert legacy status to state update
        $this->tasks = $status['tasks'] ?? [];
        $this->currentTask = $status['current_task'] ?? '';
        if (isset($status['operation'])) {
            $this->status = $status['operation'];
        }
        $this->stateChanged = true;
    }

    protected function subscribeToEvents(): void
    {
        // Processing events
        $this->eventBus->on(ProcessingEvent::class, function (ProcessingEvent $event) {
            $this->onProcessingEvent($event);
        });

        // State updates
        $this->eventBus->on(StateUpdateEvent::class, function (StateUpdateEvent $event) {
            $this->onStateUpdate($event);
        });

        // Task updates
        $this->eventBus->on(TaskUpdateEvent::class, function (TaskUpdateEvent $event) {
            $this->onTaskUpdate($event);
        });

        // Tool events
        $this->eventBus->on(ToolStartedEvent::class, function (ToolStartedEvent $event) {
            $this->onToolStarted($event);
        });

        $this->eventBus->on(ToolCompletedEvent::class, function (ToolCompletedEvent $event) {
            $this->onToolCompleted($event);
        });
    }

    protected function handleInput(string $key): void
    {
        // Handle overlay-specific keys first
        if ($this->showTaskOverlay) {
            $this->handleTaskOverlayInput($key);

            return;
        }

        if ($this->showHelp) {
            $this->handleHelpInput($key);

            return;
        }

        // Handle focus-specific input
        switch ($this->currentFocus) {
            case self::FOCUS_MAIN:
                $this->handleMainInput($key);
                break;
            case self::FOCUS_TASKS:
                $this->handleTasksInput($key);
                break;
            case self::FOCUS_CONTEXT:
                $this->handleContextInput($key);
                break;
        }
    }

    protected function handleMainInput(string $key): void
    {
        // Check for Alt combinations first
        if (str_starts_with($key, 'ALT+')) {
            $this->handleGlobalShortcuts($key);

            return;
        }

        // Tab to cycle focus
        if ($key === 'TAB') {
            $this->currentFocus = self::FOCUS_TASKS;
            $this->stateChanged = true;

            return;
        }

        // Regular text input
        if ($key === "\n") {
            if (! empty($this->input)) {
                $this->addHistory('command', $this->input);
                // Emit user input event
                $this->eventBus->emit(new UserInputEvent($this->input));
                $this->input = '';
                $this->stateChanged = true;
            }
        } elseif ($key === "\177" || $key === "\010") { // Backspace
            if (mb_strlen($this->input) > 0) {
                $this->input = mb_substr($this->input, 0, -1);
                $this->stateChanged = true;
            }
        } elseif (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
            $this->input .= $key;
            $this->stateChanged = true;
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
                $this->currentFocus = self::FOCUS_CONTEXT;
                $this->stateChanged = true;
                break;
            case 'UP':
            case 'k':
                if ($this->selectedTaskIndex > 0) {
                    $this->selectedTaskIndex--;
                    $this->stateChanged = true;
                }
                break;
            case 'DOWN':
            case 'j':
                if ($this->selectedTaskIndex < count($this->tasks) - 1) {
                    $this->selectedTaskIndex++;
                    $this->stateChanged = true;
                }
                break;
            case "\n": // Enter - select task
                if (isset($this->tasks[$this->selectedTaskIndex])) {
                    $task = $this->tasks[$this->selectedTaskIndex];
                    $this->addHistory('command', "Switch to task: {$task['description']}");
                    $this->currentFocus = self::FOCUS_MAIN;
                    $this->stateChanged = true;
                }
                break;
            case 'ESC':
                $this->currentFocus = self::FOCUS_MAIN;
                $this->stateChanged = true;
                break;
        }

        // Number keys for quick jump
        if (mb_strlen($key) === 1 && $key >= '1' && $key <= '9') {
            $index = intval($key) - 1;
            if ($index < count($this->tasks)) {
                $this->selectedTaskIndex = $index;
                $this->stateChanged = true;
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
                $this->currentFocus = self::FOCUS_MAIN;
                $this->stateChanged = true;
                break;
            case 'UP':
                if ($this->selectedContextLine > 0) {
                    $this->selectedContextLine--;
                    $this->stateChanged = true;
                }
                break;
            case 'DOWN':
                $totalLines = 3 + count($this->context['files']) + count($this->context['notes']) + 2;
                if ($this->selectedContextLine < $totalLines - 1) {
                    $this->selectedContextLine++;
                    $this->stateChanged = true;
                }
                break;
            case "\n": // Enter - add note
                if (! empty($this->contextInput)) {
                    $this->context['notes'][] = $this->contextInput;
                    $this->contextInput = '';
                    $this->addHistory('system', 'Added context note');
                    $this->stateChanged = true;
                }
                break;
            case "\177": // Backspace
            case "\010":
                // If we're on a note line, delete the note
                $noteStart = 3 + count($this->context['files']) + 1;
                $noteIndex = $this->selectedContextLine - $noteStart;
                if ($noteIndex >= 0 && $noteIndex < count($this->context['notes'])) {
                    array_splice($this->context['notes'], $noteIndex, 1);
                    $this->addHistory('system', 'Removed context note');
                    if ($this->selectedContextLine > 0) {
                        $this->selectedContextLine--;
                    }
                } elseif (mb_strlen($this->contextInput) > 0) {
                    $this->contextInput = mb_substr($this->contextInput, 0, -1);
                }
                $this->stateChanged = true;
                break;
            case 'ESC':
                $this->currentFocus = self::FOCUS_MAIN;
                $this->contextInput = '';
                $this->stateChanged = true;
                break;
        }

        // Regular text input for notes
        if (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
            $this->contextInput .= $key;
            $this->stateChanged = true;
        }
    }

    protected function handleGlobalShortcuts(string $key): bool
    {
        switch ($key) {
            case 'ALT+Q':
                $this->stop();

                return true;
            case 'ALT+T':
                $this->showTaskOverlay = ! $this->showTaskOverlay;
                $this->stateChanged = true;

                return true;
            case 'ALT+H':
                $this->showHelp = true;
                $this->stateChanged = true;

                return true;
            case 'ALT+C':
                if ($this->currentFocus === self::FOCUS_MAIN) {
                    $this->history = [];
                    $this->addHistory('system', 'History cleared');
                    $this->stateChanged = true;
                }

                return true;
            case 'ALT+R':
                $thoughtToggled = $this->toggleNearestThought();
                if (! $thoughtToggled) {
                    $this->updateTerminalSize();
                    $this->sidebarWidth = max(30, (int) ($this->terminalWidth * 0.25));
                    $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;
                    $this->addHistory('system', 'Display refreshed');
                }
                $this->stateChanged = true;

                return true;
            case 'ALT+1':
                $this->currentFocus = self::FOCUS_MAIN;
                $this->stateChanged = true;

                return true;
            case 'ALT+2':
                $this->currentFocus = self::FOCUS_TASKS;
                $this->stateChanged = true;

                return true;
            case 'ALT+3':
                $this->currentFocus = self::FOCUS_CONTEXT;
                $this->stateChanged = true;

                return true;
        }

        return false;
    }

    protected function handleTaskOverlayInput(string $key): void
    {
        if ($key === 'ESC' || $key === 'ALT+T') {
            $this->showTaskOverlay = false;
            $this->stateChanged = true;

            return;
        }

        switch ($key) {
            case 'UP':
            case 'k':
                if ($this->selectedTaskIndex > 0) {
                    $this->selectedTaskIndex--;
                    $this->adjustTaskScroll();
                    $this->stateChanged = true;
                }
                break;
            case 'DOWN':
            case 'j':
                if ($this->selectedTaskIndex < count($this->tasks) - 1) {
                    $this->selectedTaskIndex++;
                    $this->adjustTaskScroll();
                    $this->stateChanged = true;
                }
                break;
            case "\n": // Enter
                if (isset($this->tasks[$this->selectedTaskIndex])) {
                    $task = $this->tasks[$this->selectedTaskIndex];
                    $this->addHistory('command', "Switch to task: {$task['description']}");
                    $this->showTaskOverlay = false;
                    $this->stateChanged = true;
                }
                break;
        }
    }

    protected function handleHelpInput(string $key): void
    {
        $this->showHelp = false;
        $this->stateChanged = true;
    }

    /**
     * Event handlers
     */
    protected function onProcessingEvent(ProcessingEvent $event): void
    {
        $message = $event->getMessage();
        $this->addHistory('status', $message);
        $this->status = $message;
        $this->stateChanged = true;
    }

    protected function onStateUpdate(StateUpdateEvent $event): void
    {
        $this->tasks = $event->tasks;
        $this->currentTask = $event->currentTask ?? '';
        $this->context = array_merge($this->context, $event->context);
        $this->status = $event->status;
        $this->stateChanged = true;
    }

    protected function onTaskUpdate(TaskUpdateEvent $event): void
    {
        // Update task in our list
        foreach ($this->tasks as &$task) {
            if (($task['id'] ?? '') === $event->task->id) {
                $task['status'] = $event->newStatus;
                break;
            }
        }
        $this->stateChanged = true;
    }

    protected function onToolStarted(ToolStartedEvent $event): void
    {
        $this->addHistory('tool', $event->tool, implode(' ', $event->params), 'Running...');
        $this->stateChanged = true;
    }

    protected function onToolCompleted(ToolCompletedEvent $event): void
    {
        $result = $event->result->isSuccess() ? 'Success' : 'Failed';
        $this->addHistory('tool', $event->tool, implode(' ', $event->params), $result);
        $this->stateChanged = true;
    }

    /**
     * Render methods (simplified versions from mockup)
     */
    protected function render(): void
    {
        $oldWidth = $this->terminalWidth;
        $oldHeight = $this->terminalHeight;
        $this->updateTerminalSize();

        if ($oldWidth !== $this->terminalWidth || $oldHeight !== $this->terminalHeight) {
            $this->sidebarWidth = max(30, (int) ($this->terminalWidth * 0.25));
            $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;
        }

        $this->clearScreen();

        if ($this->showTaskOverlay) {
            $this->renderMainView();
            $this->renderTaskOverlay();
        } elseif ($this->showHelp) {
            $this->renderMainView();
            $this->renderHelpOverlay();
        } else {
            $this->renderMainView();
        }
    }

    protected function renderMainView(): void
    {
        // Hide cursor during rendering
        echo "\033[?25l";

        // Draw the vertical divider
        for ($row = 1; $row <= $this->terminalHeight; $row++) {
            $this->moveCursor($row, $this->mainAreaWidth + 1);
            echo Ansi::DIM . Ansi::BOX_V_HEAVY . Ansi::RESET;
        }

        // Render sidebar first
        $this->renderSidebar();

        // Render main area last (so cursor ends up at prompt)
        $this->renderMainArea();
    }

    protected function renderMainArea(): void
    {
        $row = 1;
        $isActive = $this->currentFocus === self::FOCUS_MAIN;

        // Status bar
        $this->moveCursor($row++, 2);
        $this->renderStatusBar();

        // Recent activity
        if (! empty($this->history)) {
            $row++;
            $this->moveCursor($row++, 2);
            echo Ansi::BOLD . 'Recent activity:' . Ansi::RESET;

            $availableLines = $this->terminalHeight - $row - 6;
            $recentHistory = array_slice($this->history, -$availableLines);

            foreach ($recentHistory as $entry) {
                if ($row >= $this->terminalHeight - 5) {
                    break;
                }
                $this->moveCursor($row, 2);
                $rowsUsed = $this->renderHistoryEntry($entry, $this->mainAreaWidth - 2, $row);
                $row += $rowsUsed;
            }
        }

        // Footer separator
        $this->moveCursor($this->terminalHeight - 2, 1);
        echo Ansi::DIM . str_repeat(Ansi::BOX_H, $this->mainAreaWidth) . Ansi::BOX_R . Ansi::RESET;

        // Footer hints
        $this->moveCursor($this->terminalHeight - 1, 2);
        echo Ansi::DIM . "{$this->modSymbol}T: tasks  {$this->modSymbol}H: help  Tab: switch pane  {$this->modSymbol}Q: quit" . Ansi::RESET;

        // Prompt
        $this->moveCursor($this->terminalHeight, 2);
        if ($isActive) {
            echo Ansi::BLUE . 'swarm >' . Ansi::RESET . ' ' . $this->input;
            $this->moveCursor($this->terminalHeight, 10 + mb_strlen($this->input));
            echo "\033[?25h"; // Show cursor
        } else {
            echo Ansi::DIM . 'swarm >' . Ansi::RESET . ' ' . Ansi::DIM . $this->input . Ansi::RESET;
        }
    }

    protected function renderStatusBar(): void
    {
        echo Ansi::BG_DARK;
        echo ' 💮 swarm ';
        echo Ansi::DIM . Ansi::BOX_V . ' ' . Ansi::RESET . Ansi::BG_DARK;

        if (! empty($this->currentTask)) {
            echo Ansi::GREEN . '● ' . Ansi::WHITE . $this->truncate($this->currentTask, 30);
            echo Ansi::DIM . ' ' . Ansi::BOX_V . ' ' . Ansi::RESET . Ansi::BG_DARK;
        }

        echo Ansi::YELLOW . $this->status . Ansi::RESET . Ansi::BG_DARK;

        if ($this->totalSteps > 0) {
            echo " ({$this->currentStep}/{$this->totalSteps})";
        }

        $padding = str_repeat(' ', max(0, $this->mainAreaWidth - 60));
        echo $padding . Ansi::RESET;
    }

    protected function renderSidebar(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 1;

        // Task Queue section
        $tasksActive = $this->currentFocus === self::FOCUS_TASKS;
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . Ansi::UNDERLINE . 'Task Queue' . Ansi::RESET;
        if ($tasksActive) {
            echo Ansi::BRIGHT_CYAN . ' [ACTIVE]' . Ansi::RESET;
        }

        $running = count(array_filter($this->tasks, fn ($t) => ($t['status'] ?? '') === 'running'));
        $pending = count(array_filter($this->tasks, fn ($t) => ($t['status'] ?? '') === 'pending'));

        $this->moveCursor($row++, $col);
        echo Ansi::GREEN . $running . ' running' . Ansi::RESET . ', ' . Ansi::DIM . $pending . ' pending' . Ansi::RESET;

        $row++;

        // Show tasks
        $maxTasks = min(5, (int) (($this->terminalHeight / 2) - 4));
        $taskDisplay = array_slice($this->tasks, 0, $maxTasks);

        foreach ($taskDisplay as $i => $task) {
            $this->moveCursor($row++, $col);
            $isSelected = $tasksActive && $i === $this->selectedTaskIndex;
            if ($isSelected) {
                echo Ansi::REVERSE;
            }
            $this->renderCompactTaskLine($task, $i + 1, $this->sidebarWidth - 4);
            if ($isSelected) {
                echo Ansi::RESET;
            }
        }

        if (count($this->tasks) > $maxTasks) {
            $this->moveCursor($row++, $col);
            echo Ansi::DIM . '... +' . (count($this->tasks) - $maxTasks) . ' more' . Ansi::RESET;
        }

        // Separator
        $row += 1;
        $this->moveCursor($row++, $this->mainAreaWidth + 2);
        echo Ansi::DIM . str_repeat(Ansi::BOX_H, $this->sidebarWidth - 1) . Ansi::RESET;

        // Context section
        $contextActive = $this->currentFocus === self::FOCUS_CONTEXT;
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . Ansi::UNDERLINE . 'Context' . Ansi::RESET;
        if ($contextActive) {
            echo Ansi::BRIGHT_CYAN . ' [ACTIVE]' . Ansi::RESET;
        }
        $row++;

        $contextLine = 0;

        // Directory
        if (! empty($this->context['directory'])) {
            $this->moveCursor($row++, $col);
            $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
            echo ($isSelected ? Ansi::REVERSE : '') . Ansi::CYAN . 'Dir:' . Ansi::RESET;
            $this->moveCursor($row++, $col);
            echo '  ' . $this->truncate($this->context['directory'], $this->sidebarWidth - 5);
            $row++;
        }

        // Files
        if (! empty($this->context['files'])) {
            $this->moveCursor($row++, $col);
            $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
            echo ($isSelected ? Ansi::REVERSE : '') . Ansi::YELLOW . 'Files:' . Ansi::RESET;
            foreach ($this->context['files'] as $file) {
                if ($row >= $this->terminalHeight - 8) {
                    break;
                }
                $this->moveCursor($row++, $col);
                $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
                echo ($isSelected ? Ansi::REVERSE : '') . '  ' . $this->truncate($file, $this->sidebarWidth - 5) . Ansi::RESET;
            }
        }

        // Notes
        if ($row < $this->terminalHeight - 6) {
            $row++;
            $this->moveCursor($row++, $col);
            $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
            echo ($isSelected ? Ansi::REVERSE : '') . Ansi::MAGENTA . 'Notes:' . Ansi::RESET;

            foreach ($this->context['notes'] as $i => $note) {
                if ($row >= $this->terminalHeight - 4) {
                    break;
                }
                $this->moveCursor($row++, $col);
                $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
                echo ($isSelected ? Ansi::REVERSE : '') . '  • ' . $this->truncate($note, $this->sidebarWidth - 6) . Ansi::RESET;
            }

            // Input line for new notes
            if ($contextActive && $row < $this->terminalHeight - 2) {
                $this->moveCursor($row++, $col);
                echo '  + ' . $this->contextInput;
                if ($contextActive) {
                    $this->moveCursor($row - 1, $col + 4 + mb_strlen($this->contextInput));
                    echo "\033[?25h"; // Show cursor
                }
            }
        }
    }

    protected function renderHistoryEntry(array $entry, int $maxWidth, int $currentRow): int
    {
        $time = date('H:i:s', $entry['time'] ?? time());
        $prefix = Ansi::DIM . "[{$time}]" . Ansi::RESET . ' ';
        $prefixLen = 11;

        $rowsUsed = 1;

        switch ($entry['type']) {
            case 'command':
                echo $prefix . Ansi::BLUE . '$' . Ansi::RESET . ' ';
                echo $this->truncate($entry['content'], $maxWidth - $prefixLen - 2);
                break;
            case 'status':
                echo $prefix . Ansi::GREEN . '✓' . Ansi::RESET . ' ';
                echo $this->truncate($entry['content'], $maxWidth - $prefixLen - 2);
                break;
            case 'tool':
                echo $prefix . Ansi::CYAN . '>' . Ansi::RESET . ' ';
                $toolStr = "{$entry['tool']} {$entry['params']}";
                echo $this->truncate($toolStr, $maxWidth - $prefixLen - 2);
                break;
            case 'system':
                echo $prefix . Ansi::YELLOW . '!' . Ansi::RESET . ' ';
                echo Ansi::DIM . $this->truncate($entry['content'], $maxWidth - $prefixLen - 2) . Ansi::RESET;
                break;
            case 'assistant':
                echo $prefix . Ansi::GREEN . '●' . Ansi::RESET . ' ';
                echo $this->truncate($entry['content'], $maxWidth - $prefixLen - 2);

                // Handle thought display if present
                if (isset($entry['thought']) && ! empty($entry['thought'])) {
                    $thoughtId = md5($entry['time'] . $entry['thought']);
                    $isExpanded = in_array($thoughtId, $this->expandedThoughts);
                    $thoughtLines = $this->wrapText($entry['thought'], $maxWidth - $prefixLen - 4);

                    if (count($thoughtLines) > 4 && ! $isExpanded) {
                        // Show collapsed version
                        for ($i = 0; $i < min(3, count($thoughtLines)); $i++) {
                            $this->moveCursor($currentRow + $rowsUsed, 2);
                            echo str_repeat(' ', $prefixLen) . Ansi::DIM . Ansi::ITALIC . '  ' . $thoughtLines[$i] . Ansi::RESET;
                            $rowsUsed++;
                        }

                        $this->moveCursor($currentRow + $rowsUsed, 2);
                        $remainingLines = count($thoughtLines) - 3;
                        echo str_repeat(' ', $prefixLen) . Ansi::DIM . "  ... +{$remainingLines} more lines ({$this->modSymbol}R to expand)" . Ansi::RESET;
                        $rowsUsed++;
                    } else {
                        // Show all lines
                        foreach ($thoughtLines as $line) {
                            $this->moveCursor($currentRow + $rowsUsed, 2);
                            echo str_repeat(' ', $prefixLen) . Ansi::DIM . Ansi::ITALIC . '  ' . $line . Ansi::RESET;
                            $rowsUsed++;
                        }

                        if (count($thoughtLines) > 4) {
                            $this->moveCursor($currentRow + $rowsUsed, 2);
                            echo str_repeat(' ', $prefixLen) . Ansi::DIM . "  ({$this->modSymbol}R to collapse)" . Ansi::RESET;
                            $rowsUsed++;
                        }
                    }
                }
                break;
            case 'error':
                echo $prefix . Ansi::RED . '✗' . Ansi::RESET . ' ';
                echo Ansi::RED . $this->truncate($entry['content'], $maxWidth - $prefixLen - 2) . Ansi::RESET;
                break;
            default:
                echo $prefix . '• ';
                echo $this->truncate($entry['content'], $maxWidth - $prefixLen - 2);
        }

        return $rowsUsed;
    }

    protected function renderCompactTaskLine(array $task, int $number, int $maxWidth): void
    {
        $status = $task['status'] ?? 'pending';
        $icon = match ($status) {
            'completed' => Ansi::GREEN . '✓',
            'running' => Ansi::YELLOW . '▶',
            'pending' => Ansi::DIM . '○',
            default => ' '
        };

        $num = mb_str_pad($number . '.', 3);
        $desc = $this->truncate($task['description'] ?? '', $maxWidth - 6);

        echo "{$num} {$icon} " . Ansi::RESET . $desc;

        if ($status === 'running' && ($task['steps'] ?? 0) > 0) {
            $percent = round((($task['completed_steps'] ?? 0) / $task['steps']) * 100);
            echo ' ' . Ansi::DIM . "{$percent}%" . Ansi::RESET;
        }
    }

    protected function renderTaskOverlay(): void
    {
        $maxWidth = $this->mainAreaWidth - 4;
        $width = min(70, $maxWidth);
        $height = min(20, $this->terminalHeight - 4);

        $startCol = (int) (($this->mainAreaWidth - $width) / 2) + 1;
        $startRow = (int) (($this->terminalHeight - $height) / 2);

        $this->drawBox($startRow, $startCol, $width, $height, 'Full Task List');

        $visibleHeight = $height - 4;
        $visibleTasks = array_slice($this->tasks, $this->taskScrollOffset, $visibleHeight);

        foreach ($visibleTasks as $i => $task) {
            $taskIndex = $i + $this->taskScrollOffset;
            $row = $startRow + 2 + $i;

            $this->moveCursor($row, $startCol + 2);

            if ($taskIndex === $this->selectedTaskIndex) {
                echo Ansi::REVERSE;
            }

            $num = mb_str_pad($taskIndex + 1, 2);
            $status = $task['status'] ?? 'pending';
            $icon = match ($status) {
                'completed' => Ansi::GREEN . '✓',
                'running' => Ansi::YELLOW . '▶',
                'pending' => Ansi::DIM . '○',
                default => ' '
            };

            $desc = mb_substr($task['description'] ?? '', 0, $width - 20);
            $statusText = mb_str_pad("[{$status}]", 12);

            echo "{$num}. {$icon} " . mb_str_pad($desc, $width - 20) . " {$statusText}";

            if ($taskIndex === $this->selectedTaskIndex) {
                echo Ansi::RESET;
            }
        }

        if ($this->taskScrollOffset > 0) {
            $this->moveCursor($startRow + 2, $startCol + $width - 3);
            echo Ansi::DIM . '▲' . Ansi::RESET;
        }

        if ($this->taskScrollOffset + $visibleHeight < count($this->tasks)) {
            $this->moveCursor($startRow + $height - 2, $startCol + $width - 3);
            echo Ansi::DIM . '▼' . Ansi::RESET;
        }

        $this->moveCursor($startRow + $height - 1, $startCol + 2);
        echo Ansi::DIM . "↑↓/jk: Navigate  Enter: Select  ESC/{$this->modSymbol}T: Close" . Ansi::RESET;
    }

    protected function renderHelpOverlay(): void
    {
        $maxWidth = $this->mainAreaWidth - 4;
        $width = min(60, $maxWidth);
        $height = 20;

        $startCol = (int) (($this->mainAreaWidth - $width) / 2) + 1;
        $startRow = (int) (($this->terminalHeight - $height) / 2);

        $this->drawBox($startRow, $startCol, $width, $height, 'Help');

        $help = [
            ['heading' => "Global Shortcuts ({$this->modKey} + key):"],
            ['key' => "{$this->modSymbol}T", 'desc' => 'Toggle full task list'],
            ['key' => "{$this->modSymbol}H", 'desc' => 'Show this help'],
            ['key' => "{$this->modSymbol}C", 'desc' => 'Clear history (main pane only)'],
            ['key' => "{$this->modSymbol}R", 'desc' => 'Refresh display/toggle thoughts'],
            ['key' => "{$this->modSymbol}Q", 'desc' => 'Quit application'],
            ['key' => "{$this->modSymbol}1/2/3", 'desc' => 'Jump to pane (main/tasks/context)'],
            ['', ''],
            ['heading' => 'Navigation:'],
            ['key' => 'Tab', 'desc' => 'Cycle through panes'],
            ['key' => '↑↓/jk', 'desc' => 'Navigate in lists'],
            ['key' => 'Enter', 'desc' => 'Select/confirm'],
            ['key' => 'ESC', 'desc' => 'Cancel/return to main'],
            ['', ''],
            ['heading' => 'Context Pane:'],
            ['key' => 'Type', 'desc' => 'Add new note'],
            ['key' => 'Backspace', 'desc' => 'Delete selected note'],
        ];

        $row = $startRow + 2;
        foreach ($help as $item) {
            if ($row >= $startRow + $height - 2) {
                break;
            }

            $this->moveCursor($row++, $startCol + 2);
            if (isset($item['heading'])) {
                echo Ansi::BOLD . Ansi::UNDERLINE . $item['heading'] . Ansi::RESET;
            } elseif (! empty($item['key'])) {
                echo Ansi::CYAN . mb_str_pad($item['key'], 15) . Ansi::RESET . $item['desc'];
            }
        }

        $this->moveCursor($startRow + $height - 1, $startCol + 2);
        echo Ansi::DIM . 'Press any key to close' . Ansi::RESET;
    }

    /**
     * Helper methods
     */
    protected function detectOS(): void
    {
        $this->isMacOS = mb_stripos(PHP_OS, 'darwin') !== false;
        if ($this->isMacOS) {
            $this->modKey = 'Option';
            $this->modSymbol = '⌥';
        } else {
            $this->modKey = 'Alt';
            $this->modSymbol = 'Alt+';
        }
    }

    protected function updateTerminalSize(): void
    {
        $this->terminalHeight = (int) exec('tput lines') ?: 24;
        $this->terminalWidth = (int) exec('tput cols') ?: 80;
    }

    protected function clearScreen(): void
    {
        echo "\033[2J\033[3J\033[H";
    }

    protected function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    protected function readKey(): ?string
    {
        $key = fgetc(STDIN);

        if ($key === false || $key === '') {
            return null;
        }

        // Handle escape sequences
        if ($key === "\033") {
            $seq = $key;
            $seq .= fgetc(STDIN);

            // Check for Alt key combinations
            if ($seq[1] !== '[' && $seq[1] !== false && $seq[1] !== "\033") {
                return 'ALT+' . mb_strtoupper($seq[1]);
            }

            // Handle other escape sequences
            if ($seq[1] === "\033") {
                // Option+Arrow on macOS - read and discard
                $seq .= fgetc(STDIN);
                if ($seq[2] === '[') {
                    while (true) {
                        $char = fgetc(STDIN);
                        if ($char === false || ctype_alpha($char)) {
                            break;
                        }
                    }
                }

                return null;
            }

            $seq .= fgetc(STDIN);

            // Check for extended sequences
            if (preg_match('/^\033\[1;9[A-D]$/', $seq)) {
                return null; // Ignore Option+Arrow
            }

            // Check for other modifier sequences
            if ($seq[2] === ';' || ctype_digit($seq[2])) {
                while (true) {
                    $char = fgetc(STDIN);
                    $seq .= $char;
                    if ($char === false || ctype_alpha($char)) {
                        break;
                    }
                }

                return null;
            }

            // Arrow keys
            if ($seq === "\033[A") {
                return 'UP';
            }
            if ($seq === "\033[B") {
                return 'DOWN';
            }
            if ($seq === "\033[C") {
                return 'RIGHT';
            }
            if ($seq === "\033[D") {
                return 'LEFT';
            }

            // Just ESC
            if ($seq === "\033\000\000" || mb_strlen($seq) === 1) {
                return 'ESC';
            }

            return null;
        }

        // Tab key
        if ($key === "\t") {
            return 'TAB';
        }

        return $key;
    }

    protected function addHistory(string $type, string $content, string $params = '', string $result = ''): void
    {
        $entry = [
            'time' => time(),
            'type' => $type,
            'content' => $content,
        ];

        if ($type === 'tool') {
            $entry['tool'] = $content;
            $entry['params'] = $params;
            $entry['result'] = $result;
        }

        $this->history[] = $entry;

        if (count($this->history) > 100) {
            array_shift($this->history);
        }
    }

    protected function adjustTaskScroll(): void
    {
        $visibleHeight = $this->terminalHeight - 10;

        if ($this->selectedTaskIndex < $this->taskScrollOffset) {
            $this->taskScrollOffset = $this->selectedTaskIndex;
        } elseif ($this->selectedTaskIndex >= $this->taskScrollOffset + $visibleHeight) {
            $this->taskScrollOffset = $this->selectedTaskIndex - $visibleHeight + 1;
        }
    }

    protected function drawBox(int $row, int $col, int $width, int $height, string $title = ''): void
    {
        // Top border
        $this->moveCursor($row, $col);
        echo Ansi::GRAY . Ansi::BOX_TL;
        if ($title) {
            $titleLen = mb_strlen($title);
            $padding = (int) (($width - $titleLen - 4) / 2);
            echo str_repeat(Ansi::BOX_H, $padding) . ' ' . Ansi::WHITE . Ansi::BOLD . $title . Ansi::RESET . Ansi::GRAY . ' ';
            echo str_repeat(Ansi::BOX_H, $width - $padding - $titleLen - 4);
        } else {
            echo str_repeat(Ansi::BOX_H, $width - 2);
        }
        echo Ansi::BOX_TR . Ansi::RESET;

        // Sides
        for ($i = 1; $i < $height - 1; $i++) {
            $this->moveCursor($row + $i, $col);
            echo Ansi::GRAY . Ansi::BOX_V . Ansi::RESET . str_repeat(' ', $width - 2) . Ansi::GRAY . Ansi::BOX_V . Ansi::RESET;
        }

        // Bottom border
        $this->moveCursor($row + $height - 1, $col);
        echo Ansi::GRAY . Ansi::BOX_BL . str_repeat(Ansi::BOX_H, $width - 2) . Ansi::BOX_BR . Ansi::RESET;
    }

    protected function wrapText(string $text, int $maxWidth): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            if (mb_strlen($currentLine . ' ' . $word) <= $maxWidth) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    protected function toggleNearestThought(): bool
    {
        $recentHistory = array_slice($this->history, -20);
        foreach (array_reverse($recentHistory) as $entry) {
            if ($entry['type'] === 'assistant' && isset($entry['thought'])) {
                $thoughtId = md5($entry['time'] . $entry['thought']);
                $thoughtLines = $this->wrapText($entry['thought'], $this->mainAreaWidth - 15);

                if (count($thoughtLines) > 4) {
                    if (in_array($thoughtId, $this->expandedThoughts)) {
                        $this->expandedThoughts = array_diff($this->expandedThoughts, [$thoughtId]);
                    } else {
                        $this->expandedThoughts[] = $thoughtId;
                    }

                    return true;
                }
            }
        }

        return false;
    }

    protected function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }
}
