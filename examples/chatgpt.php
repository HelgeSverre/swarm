#!/usr/bin/env php
<?php

// chatgpt.php — TUI modeled after FullTerminalUI with tasks panel only

// ====== Ansi Utilities ======
final class Ansi
{
    // Colors
    public const RESET = "\033[0m";

    public const BOLD = "\033[1m";

    public const DIM = "\033[2m";

    public const ITALIC = "\033[3m";

    public const UNDERLINE = "\033[4m";

    public const REVERSE = "\033[7m";

    public const BLACK = "\033[30m";

    public const RED = "\033[31m";

    public const GREEN = "\033[32m";

    public const YELLOW = "\033[33m";

    public const BLUE = "\033[34m";

    public const MAGENTA = "\033[35m";

    public const CYAN = "\033[36m";

    public const WHITE = "\033[37m";

    public const BRIGHT_CYAN = "\033[96m";

    public const GRAY = "\033[90m";

    public const BG_DARK = "\033[48;5;236m";

    // Box drawing
    public const BOX_H = '─';

    public const BOX_V = '│';

    public const BOX_V_HEAVY = '┃';

    public const BOX_TL = '┌';

    public const BOX_TR = '┐';

    public const BOX_BL = '└';

    public const BOX_BR = '┘';

    public const BOX_R = '┤';

    public static function csi(string $s): string
    {
        return "\033[" . $s;
    }

    public static function goto(int $row, int $col): string
    {
        return self::csi($row . ';' . $col . 'H');
    }

    public static function clear(): string
    {
        return self::csi('2J');
    }

    public static function altScreen(bool $on): string
    {
        return self::csi($on ? '?1049h' : '?1049l');
    }

    public static function showCursor(bool $on): string
    {
        return self::csi($on ? '?25h' : '?25l');
    }

    public static function wrap(bool $on): string
    {
        return self::csi($on ? '?7h' : '?7l');
    }

    public static function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
    }
}

// ====== Terminal Management ======
final class Terminal
{
    public int $cols = 80;

    public int $rows = 24;

    private string $sttySaved = '';

    public function __construct()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            @sapi_windows_vt100_support(STDOUT, true);
        }
        $this->sttySaved = trim(shell_exec('stty -g') ?? '');
        @shell_exec('stty -echo -icanon time 0 min 0 -isig -ixon -ixoff');
        echo Ansi::altScreen(true), Ansi::showCursor(false), Ansi::wrap(false);
        $this->resize();
        stream_set_blocking(STDIN, false);
    }

    public function __destruct()
    {
        $this->restore();
    }

    public function resize(): void
    {
        $out = trim(shell_exec('stty size') ?? '');
        if ($out && preg_match('/^(\d+)\s+(\d+)$/', $out, $m)) {
            $this->rows = (int) $m[1];
            $this->cols = (int) $m[2];
        }
    }

    public function restore(): void
    {
        echo Ansi::showCursor(true), Ansi::wrap(true), Ansi::altScreen(false);
        if ($this->sttySaved) {
            @shell_exec('stty ' . $this->sttySaved);
        }
    }
}

// ====== Input Handling ======
final class Input
{
    public const UP = 'UP';

    public const DOWN = 'DOWN';

    public const LEFT = 'LEFT';

    public const RIGHT = 'RIGHT';

    public const TAB = 'TAB';

    public const ENTER = 'ENTER';

    public const ESC = 'ESC';

    public const BACKSPACE = 'BACKSPACE';

    public const CHAR = 'CHAR';

    public static function poll(): array
    {
        $events = [];
        while (($key = self::readKey()) !== null) {
            $events[] = $key;
        }

        return $events;
    }

    private static function readKey(): ?array
    {
        $read = [STDIN];
        $write = null;
        $except = null;
        $result = stream_select($read, $write, $except, 0, 0);

        if ($result === 0 || $result === false) {
            return null;
        }

        $key = fgetc(STDIN);
        if ($key === false || $key === '') {
            return null;
        }

        // Handle escape sequences
        if ($key === "\033") {
            $seq = $key;

            // Read next character quickly
            $read2 = [STDIN];
            $result2 = stream_select($read2, $write, $except, 0, 10000);

            if ($result2 > 0) {
                $next = fgetc(STDIN);
                if ($next !== false && $next !== '') {
                    $seq .= $next;

                    // Alt key combinations
                    if ($next !== '[' && $next !== "\033") {
                        return ['type' => 'ALT', 'value' => mb_strtoupper($next)];
                    }
                }
            }

            // Handle arrow keys
            if (isset($seq[1]) && $seq[1] === '[') {
                $read3 = [STDIN];
                $result3 = stream_select($read3, $write, $except, 0, 10000);

                if ($result3 > 0) {
                    $third = fgetc(STDIN);
                    if ($third !== false && $third !== '') {
                        $seq .= $third;
                    }
                }

                return match ($seq) {
                    "\033[A" => ['type' => self::UP, 'value' => null],
                    "\033[B" => ['type' => self::DOWN, 'value' => null],
                    "\033[C" => ['type' => self::RIGHT, 'value' => null],
                    "\033[D" => ['type' => self::LEFT, 'value' => null],
                    default => null
                };
            }

            return ['type' => self::ESC, 'value' => null];
        }

        return match ($key) {
            "\t" => ['type' => self::TAB, 'value' => null],
            "\n", "\r" => ['type' => self::ENTER, 'value' => null],
            "\177", "\010" => ['type' => self::BACKSPACE, 'value' => null],
            default => ord($key) >= 32 && ord($key) <= 126
                ? ['type' => self::CHAR, 'value' => $key]
                : null
        };
    }
}

// ====== Task Management ======
readonly class Task
{
    public function __construct(
        public string $id,
        public string $description,
        public string $status = 'pending'
    ) {}
}

// ====== App ======
final class App
{
    private const TARGET_FPS = 60;

    private const SIDEBAR_RATIO = 0.25;

    private const MIN_SIDEBAR_WIDTH = 30;

    // Frame budget constants
    private const FRAME_BUDGET_MS = 16.0;  // 60 FPS = ~16.67ms per frame

    private const MIN_RENDER_INTERVAL_MS = 3.0;  // Prevent stress flicker

    // Focus modes
    private const FOCUS_MAIN = 'main';

    private const FOCUS_TASKS = 'tasks';

    private Terminal $term;

    // Layout
    private int $mainAreaWidth;

    private int $sidebarWidth;

    // State
    private array $history = [];

    private array $tasks = [];

    private string $input = '';

    private string $status = 'Ready';

    private string $currentFocus = self::FOCUS_MAIN;

    private int $selectedTaskIndex = 0;

    private bool $needsRedraw = true;

    // Frame timing
    private float $lastRenderTime = 0.0;

    // OS detection
    private string $modSymbol;

    public function __construct()
    {
        $this->term = new Terminal;
        $this->modSymbol = PHP_OS_FAMILY === 'Darwin' ? '⌥' : 'Alt+';

        $this->updateLayout();
        $this->initializeDemoData();
    }

    public function run(): void
    {
        while (true) {
            $hasInput = false;

            // Handle input
            foreach (Input::poll() as $event) {
                $hasInput = true;
                if ($this->handleInput($event)) {
                    return; // Quit requested
                }
            }

            // Get current time in milliseconds
            $now = hrtime(true) / 1_000_000;
            $timeSinceLastRender = $now - $this->lastRenderTime;

            // Only render if we have changes AND enough time has passed
            if (($hasInput || $this->needsRedraw) && $timeSinceLastRender >= self::MIN_RENDER_INTERVAL_MS) {
                $this->render();
                $this->needsRedraw = false;
                $this->lastRenderTime = $now;
                $timeSinceLastRender = 0; // Reset for sleep calculation
            }

            // Smart sleep to maintain frame budget
            $remainingTime = self::FRAME_BUDGET_MS - $timeSinceLastRender;
            if ($remainingTime > 0) {
                usleep((int) ($remainingTime * 1000)); // Convert to microseconds
            }
        }
    }

    private function updateLayout(): void
    {
        $this->term->resize();
        $this->sidebarWidth = max(self::MIN_SIDEBAR_WIDTH, (int) ($this->term->cols * self::SIDEBAR_RATIO));
        $this->mainAreaWidth = $this->term->cols - $this->sidebarWidth - 1;
    }

    private function initializeDemoData(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $status = match ($i % 4) {
                0 => 'completed',
                1 => 'running',
                default => 'pending'
            };
            $this->tasks[] = new Task(
                "task-{$i}",
                "Task {$i}: Example task description that might be quite long",
                $status
            );
        }

        $this->addHistory('system', 'Swarm initialized');
        $this->addHistory('command', 'help');
        $this->addHistory('assistant', 'Available commands: help, quit, status');
    }

    private function handleInput(array $event): bool
    {
        // Global shortcuts
        if ($event['type'] === 'ALT') {
            return match ($event['value']) {
                'Q' => true, // Quit
                'T' => $this->toggleFocus(),
                '1' => $this->setFocus(self::FOCUS_MAIN),
                '2' => $this->setFocus(self::FOCUS_TASKS),
                'C' => $this->clearHistory(),
                default => false
            };
        }

        // Focus-specific input
        return match ($this->currentFocus) {
            self::FOCUS_MAIN => $this->handleMainInput($event),
            self::FOCUS_TASKS => $this->handleTasksInput($event),
            default => false
        };
    }

    private function handleMainInput(array $event): bool
    {
        return match ($event['type']) {
            Input::TAB => $this->setFocus(self::FOCUS_TASKS),
            Input::ENTER => $this->processCommand(),
            Input::BACKSPACE => $this->backspaceInput(),
            Input::CHAR => $this->addInputChar($event['value']),
            default => false
        };
    }

    private function handleTasksInput(array $event): bool
    {
        return match ($event['type']) {
            Input::TAB => $this->setFocus(self::FOCUS_MAIN),
            Input::ESC => $this->setFocus(self::FOCUS_MAIN),
            Input::UP => $this->moveTaskSelection(-1),
            Input::DOWN => $this->moveTaskSelection(1),
            Input::ENTER => $this->selectTask(),
            default => false
        };
    }

    private function toggleFocus(): bool
    {
        $this->currentFocus = $this->currentFocus === self::FOCUS_MAIN
            ? self::FOCUS_TASKS
            : self::FOCUS_MAIN;

        return false;
    }

    private function setFocus(string $focus): bool
    {
        if ($this->currentFocus !== $focus) {
            $this->currentFocus = $focus;
            $this->needsRedraw = true;
        }

        return false;
    }

    private function processCommand(): bool
    {
        if (empty($this->input)) {
            return false;
        }

        $command = trim($this->input);
        $this->addHistory('command', $command);
        $this->input = '';

        // Process some basic commands
        return match ($command) {
            'quit', 'q', 'exit' => true,
            'help' => $this->showHelp(),
            'status' => $this->showStatus(),
            'clear' => $this->clearHistory(),
            default => $this->handleUnknownCommand($command)
        };
    }

    private function showHelp(): bool
    {
        $this->addHistory('assistant', 'Available commands: help, status, quit, clear');
        $this->addHistory('assistant', "Keyboard shortcuts: {$this->modSymbol}Q=quit, {$this->modSymbol}T=toggle focus, Tab=switch panes");

        return false;
    }

    private function showStatus(): bool
    {
        $running = count(array_filter($this->tasks, fn ($t) => $t->status === 'running'));
        $pending = count(array_filter($this->tasks, fn ($t) => $t->status === 'pending'));
        $completed = count(array_filter($this->tasks, fn ($t) => $t->status === 'completed'));

        $this->addHistory('assistant', "Tasks: {$running} running, {$pending} pending, {$completed} completed");

        return false;
    }

    private function clearHistory(): bool
    {
        $this->history = [];
        $this->addHistory('system', 'History cleared');

        return false;
    }

    private function handleUnknownCommand(string $command): bool
    {
        $this->addHistory('error', "Unknown command: {$command}. Type 'help' for available commands.");

        return false;
    }

    private function backspaceInput(): bool
    {
        if (mb_strlen($this->input) > 0) {
            $this->input = mb_substr($this->input, 0, -1);
            $this->needsRedraw = true;
        }

        return false;
    }

    private function addInputChar(string $char): bool
    {
        $this->input .= $char;
        $this->needsRedraw = true;

        return false;
    }

    private function moveTaskSelection(int $direction): bool
    {
        $newIndex = $this->selectedTaskIndex + $direction;
        $oldIndex = $this->selectedTaskIndex;
        $this->selectedTaskIndex = max(0, min(count($this->tasks) - 1, $newIndex));
        if ($oldIndex !== $this->selectedTaskIndex) {
            $this->needsRedraw = true;
        }

        return false;
    }

    private function selectTask(): bool
    {
        if (isset($this->tasks[$this->selectedTaskIndex])) {
            $task = $this->tasks[$this->selectedTaskIndex];
            $this->addHistory('command', "Selected task: {$task->description}");
            $this->currentFocus = self::FOCUS_MAIN;
        }

        return false;
    }

    private function addHistory(string $type, string $content): void
    {
        $this->history[] = [
            'time' => time(),
            'type' => $type,
            'content' => $content,
        ];

        if (count($this->history) > 100) {
            array_shift($this->history);
        }

        $this->needsRedraw = true;
    }

    private function render(): void
    {
        $this->updateLayout();
        $this->clearScreen();
        $this->renderStatusBar();
        $this->renderMainArea();
        $this->renderSidebar();

        // Ensure cursor is positioned correctly at the end
        $this->positionCursor();
    }

    private function clearScreen(): void
    {
        echo "\033[2J\033[3J\033[H";
    }

    private function renderStatusBar(): void
    {
        echo Ansi::goto(1, 1);
        echo Ansi::BG_DARK;

        $statusContent = ' 💮 swarm ';
        $statusContent .= Ansi::DIM . Ansi::BOX_V . ' ' . Ansi::RESET . Ansi::BG_DARK;
        $statusContent .= Ansi::YELLOW . $this->status . Ansi::RESET . Ansi::BG_DARK;

        echo $statusContent;

        $contentLength = mb_strlen(Ansi::stripAnsi($statusContent));
        $remainingWidth = max(0, $this->term->cols - $contentLength);
        echo str_repeat(' ', $remainingWidth);

        echo Ansi::RESET;
    }

    private function renderMainArea(): void
    {
        $row = 3;
        $isActive = $this->currentFocus === self::FOCUS_MAIN;

        // Draw vertical divider
        for ($r = 1; $r <= $this->term->rows; $r++) {
            echo Ansi::goto($r, $this->mainAreaWidth + 1);
            echo Ansi::DIM . Ansi::BOX_V_HEAVY . Ansi::RESET;
        }

        // Render history
        $availableLines = $this->term->rows - 6;
        $recentHistory = array_slice($this->history, -$availableLines);

        foreach ($recentHistory as $entry) {
            if ($row >= $this->term->rows - 3) {
                break;
            }

            echo Ansi::goto($row++, 2);
            $this->renderHistoryEntry($entry);
        }

        // Footer separator
        echo Ansi::goto($this->term->rows - 2, 1);
        echo Ansi::DIM . str_repeat(Ansi::BOX_H, $this->mainAreaWidth) . Ansi::BOX_R . Ansi::RESET;

        // Footer hints
        echo Ansi::goto($this->term->rows - 1, 2);
        echo Ansi::DIM . "{$this->modSymbol}T: tasks  {$this->modSymbol}Q: quit  Tab: switch pane" . Ansi::RESET;

        // Prompt
        echo Ansi::goto($this->term->rows, 2);
        if ($isActive) {
            echo Ansi::BLUE . 'swarm >' . Ansi::RESET . ' ' . $this->input;
            echo Ansi::goto($this->term->rows, 10 + mb_strlen($this->input));
            echo Ansi::showCursor(true);
        } else {
            echo Ansi::DIM . 'swarm >' . Ansi::RESET . ' ' . Ansi::DIM . $this->input . Ansi::RESET;
        }
    }

    private function renderHistoryEntry(array $entry): void
    {
        $time = date('H:i:s', $entry['time']);
        $prefix = Ansi::DIM . "[{$time}]" . Ansi::RESET . ' ';

        [$icon, $formatting] = match ($entry['type']) {
            'command' => [Ansi::BLUE . '$' . Ansi::RESET . ' ', ''],
            'assistant' => [Ansi::GREEN . '●' . Ansi::RESET . ' ', ''],
            'system' => [Ansi::YELLOW . '!' . Ansi::RESET . ' ', Ansi::DIM],
            'error' => [Ansi::RED . '✗' . Ansi::RESET . ' ', Ansi::RED],
            default => ['• ', '']
        };

        $content = $this->truncate($entry['content'], $this->mainAreaWidth - 15);
        echo $prefix . $icon . $formatting . $content . ($formatting ? Ansi::RESET : '');
    }

    private function renderSidebar(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 4;
        $tasksActive = $this->currentFocus === self::FOCUS_TASKS;

        // Task Queue header
        echo Ansi::goto($row++, $col);
        echo Ansi::BOLD . Ansi::UNDERLINE . 'Task Queue' . Ansi::RESET;
        if ($tasksActive) {
            echo Ansi::BRIGHT_CYAN . ' [ACTIVE]' . Ansi::RESET;
        }

        // Task counts
        $running = count(array_filter($this->tasks, fn ($t) => $t->status === 'running'));
        $pending = count(array_filter($this->tasks, fn ($t) => $t->status === 'pending'));

        echo Ansi::goto($row++, $col);
        echo Ansi::GREEN . $running . ' running' . Ansi::RESET . ', ' .
             Ansi::DIM . $pending . ' pending' . Ansi::RESET;

        $row++;

        // Task list
        $maxTasks = min(count($this->tasks), $this->term->rows - 10);
        for ($i = 0; $i < $maxTasks; $i++) {
            $task = $this->tasks[$i];
            $isSelected = $tasksActive && $i === $this->selectedTaskIndex;

            echo Ansi::goto($row++, $col);

            if ($isSelected) {
                echo Ansi::REVERSE;
            }

            $this->renderTaskLine($task, $i + 1);

            if ($isSelected) {
                echo Ansi::RESET;
            }
        }
    }

    private function renderTaskLine(Task $task, int $number): void
    {
        $icon = match ($task->status) {
            'completed' => Ansi::GREEN . '✓',
            'running' => Ansi::YELLOW . '▶',
            'pending' => Ansi::DIM . '○',
            default => ' '
        };

        $num = mb_str_pad($number . '.', 3);
        $desc = $this->truncate($task->description, $this->sidebarWidth - 10);

        echo "{$num} {$icon} " . Ansi::RESET . $desc;
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }

    private function positionCursor(): void
    {
        if ($this->currentFocus === self::FOCUS_MAIN) {
            // Position cursor at input field
            echo Ansi::goto($this->term->rows, 10 + mb_strlen($this->input));
            echo Ansi::showCursor(true);
        } elseif ($this->currentFocus === self::FOCUS_TASKS) {
            // Hide cursor when not in main input
            echo Ansi::showCursor(false);
        }
    }
}

// Cleanup handler
register_shutdown_function(function () {
    echo Ansi::showCursor(true), Ansi::wrap(true), Ansi::altScreen(false), "\033[0m";
});

(new App)->run();
