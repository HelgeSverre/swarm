<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\CLI\Activity\ToolCallEntry;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ProcessCompleteEvent;
use HelgeSverre\Swarm\Events\ProcessingEvent;
use HelgeSverre\Swarm\Events\ProcessProgressEvent;
use HelgeSverre\Swarm\Events\StateUpdateEvent;
use HelgeSverre\Swarm\Events\TaskUpdateEvent;
use HelgeSverre\Swarm\Events\ToolCompletedEvent;
use HelgeSverre\Swarm\Events\ToolStartedEvent;

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

    protected array $pendingToolCalls = [];

    protected array $activityFeed = [];

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

    // Reasoning display state
    protected ?string $currentReasoning = null;

    protected bool $showReasoning = false;

    // Platform detection
    protected bool $isMacOS;

    protected string $modKey;

    protected string $modSymbol;

    protected float $startTime;

    protected array $currentProgress = [];

    protected bool $initialized = false;

    protected string $originalTermState = '';

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

        // Initialize terminal for non-blocking input
        $this->initializeTerminal();

        // Register cleanup on shutdown
        register_shutdown_function([$this, 'cleanup']);
    }

    /**
     * Destructor to ensure cleanup
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    public function checkForInput(): ?string
    {
        // Read all available characters at once to handle paste efficiently
        $completedInput = null;
        $hasInput = false;

        while (($key = $this->readKey()) !== null) {
            $hasInput = true;
            $this->handleInput($key);

            // Check if we have a complete input to return
            if ($key === "\n" && ! empty($this->input)) {
                $completedInput = $this->input;
                $this->input = '';
                $this->stateChanged = true;
                break; // Stop processing after enter
            }
        }

        // Force render if we processed any input (for immediate feedback)
        if ($hasInput) {
            $this->stateChanged = true;
        }

        return $completedInput;
    }

    public function render(bool $force = false): void
    {
        if (! $this->initialized) {
            return;
        }

        // Re-render if forced or state changed
        if ($force || $this->stateChanged) {
            $this->doRender();
            $this->stateChanged = false;
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
        if (! $this->initialized) {
            return;
        }

        // Show cursor
        echo "\033[?25h";

        // Reset all attributes
        echo "\033[0m";

        // Exit alternate screen buffer (restores original screen)
        echo "\033[?1049l";

        // Restore original terminal state if we saved it
        if (! empty($this->originalTermState)) {
            system("stty {$this->originalTermState}");
        } else {
            // Fallback to sane defaults
            system('stty sane');
        }

        $this->initialized = false;
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

    public function refresh(array $state = []): void
    {
        if (! empty($state['tasks'])) {
            $this->tasks = $state['tasks'];
        }
        if (! empty($state['conversation_history'])) {
            $this->history = $state['conversation_history'];
        }
        if (isset($state['current_task'])) {
            $this->currentTask = $state['current_task'];
        }
        if (isset($state['operation'])) {
            $this->status = $state['operation'];
        }

        $this->stateChanged = true;
    }

    public function updateProcessingMessage(string $message): void
    {
        $this->status = $message;
        $this->stateChanged = true;
    }

    protected function initializeTerminal(): void
    {
        if ($this->initialized) {
            return;
        }

        // Save current terminal state for restoration
        $this->originalTermState = trim(shell_exec('stty -g') ?? '');

        // Enter alternate screen buffer (like vim/less)
        echo "\033[?1049h";

        // Clear the alternate screen and scrollback
        echo "\033[2J\033[3J\033[H";

        // Set up terminal for raw mode
        system('stty -echo -icanon min 1 time 0');
        stream_set_blocking(STDIN, false);

        // Hide cursor initially
        echo "\033[?25l";

        $this->running = true;
        $this->initialized = true;
        $this->render(force: true);
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

        // Process events
        $this->eventBus->on(ProcessProgressEvent::class, function (ProcessProgressEvent $event) {
            $this->onProcessProgress($event);
        });

        $this->eventBus->on(ProcessCompleteEvent::class, function (ProcessCompleteEvent $event) {
            $this->onProcessComplete($event);
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

        // R to toggle reasoning display
        if (mb_strtoupper($key) === 'R' && $this->currentReasoning) {
            $this->showReasoning = ! $this->showReasoning;
            $this->stateChanged = true;

            return;
        }

        // Regular text input
        if ($key === "\n") {
            if (! empty($this->input)) {
                $this->addHistory('command', $this->input);
                // Don't emit here - let checkForInput handle it
                // The input will be returned by checkForInput() for Swarm to handle
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
        error_log('[UI] ProcessingEvent received: ' . json_encode([
            'operation' => $event->operation,
            'phase' => $event->phase,
            'message' => $event->getMessage(),
        ]));

        $message = $event->getMessage();
        $this->addHistory('status', $message);
        $this->status = $message;
        $this->stateChanged = true;
    }

    protected function onStateUpdate(StateUpdateEvent $event): void
    {
        error_log('[UI] StateUpdateEvent received: ' . json_encode([
            'tasks_count' => count($event->tasks),
            'current_task' => $event->currentTask,
            'status' => $event->status,
        ]));

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
        error_log('[UI] ToolStartedEvent received: ' . json_encode([
            'tool' => $event->tool,
            'params' => $event->params,
        ]));

        // Add to history for historical view
        $this->addHistory('tool', $event->tool, implode(' ', $event->params), 'Running...');

        // Store the pending tool call for completion later
        $this->pendingToolCalls[$event->tool] = [
            'tool' => $event->tool,
            'params' => $event->params,
            'startTime' => time(),
        ];

        $this->stateChanged = true;
    }

    protected function onToolCompleted(ToolCompletedEvent $event): void
    {
        error_log('[UI] ToolCompletedEvent received: ' . json_encode([
            'tool' => $event->tool,
            'success' => $event->result->isSuccess(),
        ]));

        $result = $event->result->isSuccess() ? 'Success' : 'Failed';
        $this->addHistory('tool', $event->tool, implode(' ', $event->params), $result);

        // Create and add ToolCallEntry to activity feed
        $toolCallEntry = new ToolCallEntry(
            $event->tool,
            $event->params,
            $event->result,
            $this->pendingToolCalls[$event->tool]['startTime'] ?? time()
        );

        // Add to activity feed for display
        $this->addActivity($toolCallEntry);

        // Clean up pending tool call
        unset($this->pendingToolCalls[$event->tool]);

        $this->stateChanged = true;
    }

    /**
     * Render methods (simplified versions from mockup)
     */
    protected function doRender(): void
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

    protected function onProcessProgress(ProcessProgressEvent $event): void
    {
        error_log('[UI] ProcessProgressEvent received: ' . json_encode([
            'processId' => $event->processId,
            'type' => $event->type,
            'message' => $event->data['message'] ?? null,
            'operation' => $event->data['operation'] ?? null,
        ]));

        $this->currentProgress = $event->data;
        $this->status = $event->data['message'] ?? 'Processing...';

        // Handle reasoning content display
        if (isset($event->data['operation']) && $event->data['operation'] === 'reasoning_received') {
            $this->currentReasoning = $event->data['details']['reasoning_content'] ?? null;
            $this->showReasoning = ! empty($this->currentReasoning);

            // Update status to show reasoning is available
            if ($this->showReasoning) {
                $this->status = 'Thinking... (Press R to toggle reasoning)';
            }
        }

        // Clear reasoning for non-reasoning operations
        if (isset($event->data['operation']) && ! in_array($event->data['operation'], ['reasoning_received', 'calling_openai'])) {
            if ($event->data['operation'] === 'quick_response' || $event->data['operation'] === 'deep_processing') {
                $this->currentReasoning = null;
                $this->showReasoning = false;
            }
        }

        $this->stateChanged = true;
    }

    protected function onProcessComplete(ProcessCompleteEvent $event): void
    {
        error_log('[UI] ProcessCompleteEvent received: ' . json_encode([
            'processId' => $event->processId,
            'message_length' => mb_strlen($event->response->getMessage()),
        ]));

        $this->displayResponse($event->response);
        $this->currentProgress = [];
        $this->status = 'Ready';
        $this->stateChanged = true;
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

        // Status bar - render from column 1 for full width
        $this->moveCursor($row++, 1);
        $this->renderStatusBar();

        // Recent activity (no label needed, just display the content)
        if (! empty($this->history) || ! empty($this->activityFeed)) {
            $row++; // Add some spacing after status bar

            $availableLines = $this->terminalHeight - $row - 6;

            // Merge activity feed and history, prioritizing activity feed
            $allEntries = [];

            // Add activity feed entries (they have a getMessage method)
            foreach ($this->activityFeed as $activity) {
                if (method_exists($activity, 'getMessage')) {
                    $allEntries[] = [
                        'time' => $activity->timestamp ?? time(),
                        'type' => 'tool_activity',
                        'content' => $activity->getMessage(),
                        'activity_object' => $activity,
                    ];
                }
            }

            // Add history entries
            foreach ($this->history as $entry) {
                if ($entry['type'] !== 'tool') { // Skip old tool entries, we use activity feed now
                    $allEntries[] = $entry;
                }
            }

            // Sort by time (oldest first) and get recent ones
            usort($allEntries, fn ($a, $b) => ($a['time'] ?? 0) - ($b['time'] ?? 0));
            // Get the most recent entries but maintain chronological order
            $totalEntries = count($allEntries);
            $startIndex = max(0, $totalEntries - $availableLines);
            $recentEntries = array_slice($allEntries, $startIndex, $availableLines);

            foreach ($recentEntries as $entry) {
                if ($row >= $this->terminalHeight - 5) {
                    break;
                }
                // renderHistoryEntry now handles cursor positioning internally
                $rowsUsed = $this->renderHistoryEntry($entry, $this->mainAreaWidth - 2, $row);
                $row += $rowsUsed;
            }
        }

        // Footer separator
        $this->moveCursor($this->terminalHeight - 2, 1);
        echo Ansi::DIM . str_repeat(Ansi::BOX_H, $this->mainAreaWidth) . Ansi::BOX_R . Ansi::RESET;

        // Reasoning display (if available and enabled)
        if ($this->showReasoning && $this->currentReasoning) {
            $this->renderReasoningDisplay($row);
        }

        // Footer hints
        $this->moveCursor($this->terminalHeight - 1, 2);
        $footerText = "{$this->modSymbol}T: tasks  {$this->modSymbol}H: help  Tab: switch pane  {$this->modSymbol}Q: quit";
        if ($this->currentReasoning) {
            $footerText .= '  R: reasoning';
        }
        echo Ansi::DIM . $footerText . Ansi::RESET;

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

    protected function renderReasoningDisplay(int &$row): void
    {
        if (! $this->currentReasoning) {
            return;
        }

        // Calculate available space for reasoning display
        $maxLines = 5; // Maximum lines to show reasoning
        $availableWidth = $this->mainAreaWidth - 4; // Leave margins

        // Wrap reasoning text
        $reasoningLines = $this->wrapText($this->currentReasoning, $availableWidth);
        $displayLines = array_slice($reasoningLines, 0, $maxLines);

        // Render reasoning box
        $startRow = max(3, min($row, $this->terminalHeight - 8));

        // Top border
        $this->moveCursor($startRow, 2);
        echo Ansi::CYAN . '╭─ Thinking ';
        echo str_repeat('─', max(0, $availableWidth - 11));
        echo '╮' . Ansi::RESET;

        // Content lines
        $currentRow = $startRow + 1;
        foreach ($displayLines as $line) {
            if ($currentRow >= $this->terminalHeight - 3) {
                break;
            }

            $this->moveCursor($currentRow, 2);
            echo Ansi::CYAN . '│' . Ansi::RESET;
            echo ' ' . Ansi::DIM . $line . Ansi::RESET;

            // Pad to full width and add right border
            $lineLength = mb_strlen($line);
            if ($lineLength < $availableWidth) {
                echo str_repeat(' ', $availableWidth - $lineLength);
            }
            echo Ansi::CYAN . '│' . Ansi::RESET;

            $currentRow++;
        }

        // Show truncation indicator if needed
        if (count($reasoningLines) > $maxLines) {
            $this->moveCursor($currentRow, 2);
            echo Ansi::CYAN . '│' . Ansi::RESET;
            echo ' ' . Ansi::DIM . '... (truncated)' . Ansi::RESET;

            $truncText = ' ... (truncated)';
            $padding = max(0, $availableWidth - mb_strlen($truncText));
            echo str_repeat(' ', $padding);
            echo Ansi::CYAN . '│' . Ansi::RESET;
            $currentRow++;
        }

        // Bottom border
        $this->moveCursor($currentRow, 2);
        echo Ansi::CYAN . '╰';
        echo str_repeat('─', $availableWidth);
        echo '╯' . Ansi::RESET;

        $row = $currentRow + 2; // Update row position for next element
    }

    protected function renderStatusBar(): void
    {
        // Start with background color
        echo Ansi::BG_DARK;

        // Build the status content
        $statusContent = ' 💮 swarm ';
        $statusContent .= Ansi::DIM . Ansi::BOX_V . ' ' . Ansi::RESET . Ansi::BG_DARK;

        // Only show the status, no task description
        $statusContent .= Ansi::YELLOW . $this->status . Ansi::RESET . Ansi::BG_DARK;

        if ($this->totalSteps > 0) {
            $statusContent .= " ({$this->currentStep}/{$this->totalSteps})";
        }

        // Output the status content
        echo $statusContent;

        // Calculate how much content we've already rendered (strip ANSI codes for accurate count)
        $contentLength = mb_strlen(Ansi::stripAnsi($statusContent));

        // Fill the rest of the terminal width with spaces (background color still applied)
        $remainingWidth = max(0, $this->terminalWidth - $contentLength);
        echo str_repeat(' ', $remainingWidth);

        // Reset at the end
        echo Ansi::RESET;
    }

    protected function renderSidebar(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 4;  // Start below the status bar

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
                $this->moveCursor($row - 1, $col + 4 + mb_strlen($this->contextInput));
                echo "\033[?25h"; // Show cursor
            }
        }
    }

    protected function renderHistoryEntry(array $entry, int $maxWidth, int $currentRow): int
    {
        $time = date('H:i:s', $entry['time'] ?? time());
        $prefix = Ansi::DIM . "[{$time}]" . Ansi::RESET . ' ';
        $prefixLen = 11; // Length of "[HH:MM:SS] "

        // Calculate actual content width (account for prefix and type indicator)
        // maxWidth is the total width available, we need to subtract prefix and type indicator
        $typeIndicatorLen = 2; // Most indicators are 1 char + space
        $contentWidth = $maxWidth - $prefixLen - $typeIndicatorLen - 2; // Extra margin for safety

        // Ensure content width is positive
        if ($contentWidth < 10) {
            $contentWidth = 10; // Minimum width for content
        }

        $rowsUsed = 0;

        // Prepare the content based on type
        $typeIndicator = '';
        $content = $entry['content'] ?? '';
        $formatting = '';
        $formattingEnd = '';

        switch ($entry['type']) {
            case 'command':
                $typeIndicator = Ansi::BLUE . '$' . Ansi::RESET . ' ';
                break;
            case 'status':
                $typeIndicator = Ansi::GREEN . '✓' . Ansi::RESET . ' ';
                break;
            case 'tool_activity':
                $typeIndicator = Ansi::CYAN . '🔧' . Ansi::RESET . ' ';
                break;
            case 'activity':
                $typeIndicator = Ansi::CYAN . '⚡' . Ansi::RESET . ' ';
                break;
            case 'tool':
                $typeIndicator = Ansi::CYAN . '>' . Ansi::RESET . ' ';
                $content = "{$entry['tool']} {$entry['params']}";
                break;
            case 'system':
                $typeIndicator = Ansi::YELLOW . '!' . Ansi::RESET . ' ';
                $formatting = Ansi::DIM;
                $formattingEnd = Ansi::RESET;
                break;
            case 'assistant':
                $typeIndicator = Ansi::GREEN . '●' . Ansi::RESET . ' ';
                break;
            case 'error':
                $typeIndicator = Ansi::RED . '✗' . Ansi::RESET . ' ';
                $formatting = Ansi::RED;
                $formattingEnd = Ansi::RESET;
                break;
            default:
                $typeIndicator = '• ';
        }

        // Wrap the content to fit the available width
        $wrappedLines = $this->wrapText($content, $contentWidth);

        // Render the first line with prefix and type indicator
        if (! empty($wrappedLines)) {
            // Position cursor for first line
            $this->moveCursor($currentRow, 2);

            // Build the first line and ensure it doesn't exceed maxWidth
            $firstLine = $prefix . $typeIndicator . $formatting . $wrappedLines[0] . $formattingEnd;

            // Truncate if still too long (safety measure)
            if (mb_strlen(Ansi::stripAnsi($firstLine)) > $maxWidth) {
                $firstLine = mb_substr($firstLine, 0, $maxWidth - 3) . '...';
            }

            echo $firstLine;
            $rowsUsed = 1;

            // Render additional wrapped lines with proper indentation
            $indentSpace = str_repeat(' ', $prefixLen + $typeIndicatorLen);
            for ($i = 1; $i < count($wrappedLines); $i++) {
                $this->moveCursor($currentRow + $rowsUsed, 2);

                // Build the continuation line
                $continuationLine = $indentSpace . $formatting . $wrappedLines[$i] . $formattingEnd;

                // Truncate if too long (safety measure)
                if (mb_strlen(Ansi::stripAnsi($continuationLine)) > $maxWidth) {
                    $continuationLine = mb_substr($continuationLine, 0, $maxWidth - 3) . '...';
                }

                echo $continuationLine;
                $rowsUsed++;
            }
        } else {
            // If no content, just show the prefix and type indicator
            $this->moveCursor($currentRow, 2);
            echo $prefix . $typeIndicator;
            $rowsUsed = 1;
        }

        // Special handling for assistant thoughts (if present)
        if ($entry['type'] === 'assistant' && isset($entry['thought']) && ! empty($entry['thought'])) {
            $thoughtId = md5($entry['time'] . $entry['thought']);
            $isExpanded = in_array($thoughtId, $this->expandedThoughts);
            $thoughtLines = $this->wrapText($entry['thought'], $contentWidth - 2);

            $thoughtIndent = str_repeat(' ', $prefixLen + $typeIndicatorLen);

            if (count($thoughtLines) > 4 && ! $isExpanded) {
                // Show collapsed version
                for ($i = 0; $i < min(3, count($thoughtLines)); $i++) {
                    $this->moveCursor($currentRow + $rowsUsed, 2);
                    $thoughtLine = $thoughtIndent . Ansi::DIM . Ansi::ITALIC . $thoughtLines[$i] . Ansi::RESET;

                    // Ensure thought line doesn't exceed width
                    if (mb_strlen(Ansi::stripAnsi($thoughtLine)) > $maxWidth) {
                        $thoughtLine = mb_substr($thoughtLine, 0, $maxWidth - 3) . '...';
                    }

                    echo $thoughtLine;
                    $rowsUsed++;
                }

                $this->moveCursor($currentRow + $rowsUsed, 2);
                $remainingLines = count($thoughtLines) - 3;
                $expandLine = $thoughtIndent . Ansi::DIM . "... +{$remainingLines} more lines ({$this->modSymbol}R to expand)" . Ansi::RESET;

                if (mb_strlen(Ansi::stripAnsi($expandLine)) > $maxWidth) {
                    $expandLine = mb_substr($expandLine, 0, $maxWidth - 3) . '...';
                }

                echo $expandLine;
                $rowsUsed++;
            } else {
                // Show all thought lines
                foreach ($thoughtLines as $line) {
                    $this->moveCursor($currentRow + $rowsUsed, 2);
                    $thoughtLine = $thoughtIndent . Ansi::DIM . Ansi::ITALIC . $line . Ansi::RESET;

                    // Ensure thought line doesn't exceed width
                    if (mb_strlen(Ansi::stripAnsi($thoughtLine)) > $maxWidth) {
                        $thoughtLine = mb_substr($thoughtLine, 0, $maxWidth - 3) . '...';
                    }

                    echo $thoughtLine;
                    $rowsUsed++;
                }

                if (count($thoughtLines) > 4) {
                    $this->moveCursor($currentRow + $rowsUsed, 2);
                    $collapseLine = $thoughtIndent . Ansi::DIM . "({$this->modSymbol}R to collapse)" . Ansi::RESET;

                    if (mb_strlen(Ansi::stripAnsi($collapseLine)) > $maxWidth) {
                        $collapseLine = mb_substr($collapseLine, 0, $maxWidth - 3) . '...';
                    }

                    echo $collapseLine;
                    $rowsUsed++;
                }
            }
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

            $num = mb_str_pad((string) ($taskIndex + 1), 2);
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
        // Clear screen and scrollback in alternate buffer
        echo "\033[2J\033[3J\033[H";
    }

    protected function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    protected function readKey(): ?string
    {
        // Check if input is available to avoid blocking
        $read = [STDIN];
        $write = null;
        $except = null;
        $result = stream_select($read, $write, $except, 0, 0);

        if ($result === 0 || $result === false) {
            return null; // No input available
        }

        $key = fgetc(STDIN);

        if ($key === false || $key === '') {
            return null;
        }

        // Handle escape sequences
        if ($key === "\033") {
            $seq = $key;

            // Wait a bit for the next character (escape sequences come quickly)
            $read2 = [STDIN];
            $result2 = stream_select($read2, $write, $except, 0, 10000); // 10ms timeout

            if ($result2 > 0) {
                $next = fgetc(STDIN);
                if ($next !== false && $next !== '') {
                    $seq .= $next;

                    // Check for Alt key combinations
                    if ($next !== '[' && $next !== "\033") {
                        return 'ALT+' . mb_strtoupper($next);
                    }
                } else {
                    // Just ESC key
                    return 'ESC';
                }
            } else {
                // Just ESC key (no following character)
                return 'ESC';
            }

            // Handle other escape sequences (like arrow keys)
            if (isset($seq[1]) && $seq[1] === '[') {
                // Read the third character for arrow keys and other sequences
                $read3 = [STDIN];
                $result3 = stream_select($read3, $write, $except, 0, 10000); // 10ms timeout

                if ($result3 > 0) {
                    $third = fgetc(STDIN);
                    if ($third !== false && $third !== '') {
                        $seq .= $third;
                    }
                }
            } elseif (isset($seq[1]) && $seq[1] === "\033") {
                // Option+Arrow on macOS - read and discard
                $seq .= fgetc(STDIN);
                if (isset($seq[2]) && $seq[2] === '[') {
                    while (true) {
                        $char = fgetc(STDIN);
                        if ($char === false || ctype_alpha($char)) {
                            break;
                        }
                    }
                }

                return null;
            }

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

    protected function addActivity($activityEntry): void
    {
        // Add to activity feed for display in main area
        $this->activityFeed[] = $activityEntry;

        // Keep activity feed from growing too large
        if (count($this->activityFeed) > 50) {
            array_shift($this->activityFeed);
        }

        // Also add a simplified version to history
        if (method_exists($activityEntry, 'getMessage')) {
            $this->history[] = [
                'time' => time(),
                'type' => 'activity',
                'content' => $activityEntry->getMessage(),
            ];
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
