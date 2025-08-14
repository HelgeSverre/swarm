#!/usr/bin/env php
<?php

/**
 * Terminal-Style UI Mockup with Side Panel and Focus Modes
 *
 * Features:
 * - Option + key shortcuts (no interference with typing)
 * - Pane focus system (main, tasks, context)
 * - Editable context pane
 *
 * Shortcuts:
 * - ⌥T: Toggle task overlay
 * - ⌥H: Show help
 * - ⌥C: Clear history
 * - ⌥R: Refresh display
 * - ⌥Q: Quit
 * - Tab: Cycle focus between panes
 * - ⌥1/2/3: Jump to pane (main/tasks/context)
 */
class TerminalUI3
{
    // ANSI color codes
    const RESET = "\033[0m";

    const BOLD = "\033[1m";

    const DIM = "\033[2m";

    const ITALIC = "\033[3m";

    const UNDERLINE = "\033[4m";

    const REVERSE = "\033[7m";

    const BLINK = "\033[5m";

    // Colors
    const BLACK = "\033[30m";

    const RED = "\033[31m";

    const GREEN = "\033[32m";

    const YELLOW = "\033[33m";

    const BLUE = "\033[34m";

    const MAGENTA = "\033[35m";

    const CYAN = "\033[36m";

    const WHITE = "\033[37m";

    const GRAY = "\033[90m";

    const BRIGHT_RED = "\033[91m";

    const BRIGHT_GREEN = "\033[92m";

    const BRIGHT_YELLOW = "\033[93m";

    const BRIGHT_BLUE = "\033[94m";

    const BRIGHT_CYAN = "\033[96m";

    // Background colors
    const BG_BLACK = "\033[40m";

    const BG_GRAY = "\033[100m";

    const BG_DARK = "\033[48;5;236m"; // Dark gray background

    // Box drawing characters (Unicode)
    // Single lines
    const BOX_H = '─';      // Horizontal

    const BOX_V = '│';      // Vertical

    const BOX_V_HEAVY = '┃';  // Heavy vertical

    const BOX_TL = '┌';     // Top-left corner

    const BOX_TR = '┐';     // Top-right corner

    const BOX_BL = '└';     // Bottom-left corner

    const BOX_BR = '┘';     // Bottom-right corner

    const BOX_T = '┬';      // T-junction down

    const BOX_B = '┴';      // T-junction up

    const BOX_L = '├';      // T-junction right

    const BOX_R = '┤';      // T-junction left

    const BOX_CROSS = '┼';  // Cross

    // Double lines
    const BOX_H2 = '═';     // Double horizontal

    const BOX_V2 = '║';     // Double vertical

    const BOX_TL2 = '╔';    // Double top-left

    const BOX_TR2 = '╗';    // Double top-right

    const BOX_BL2 = '╚';    // Double bottom-left

    const BOX_BR2 = '╝';    // Double bottom-right

    // Focus modes
    const FOCUS_MAIN = 'main';

    const FOCUS_TASKS = 'tasks';

    const FOCUS_CONTEXT = 'context';

    private array $history = [];

    private array $expandedThoughts = []; // Track which thoughts are expanded

    private array $tasks = [];

    private array $context = [
        'directory' => '/home/user/project',
        'files' => ['src/validators.php', 'tests/ValidatorTest.php'],
        'tools' => ['read_file', 'write_file', 'terminal', 'grep'],
        'notes' => [],
    ];

    private string $currentTask = '';

    private string $status = 'ready';

    private int $currentStep = 0;

    private int $totalSteps = 0;

    private bool $showTaskOverlay = false;

    private bool $showHelp = false;

    private int $selectedTaskIndex = 0;

    private int $selectedContextLine = 0;

    private int $taskScrollOffset = 0;

    private int $terminalHeight;

    private int $terminalWidth;

    private int $mainAreaWidth;

    private int $sidebarWidth;

    private string $input = '';

    private string $contextInput = '';

    private string $currentFocus = self::FOCUS_MAIN;

    private float $startTime;

    private bool $altPressed = false;

    private bool $isMacOS;

    private string $modKey;

    private string $modSymbol;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->detectOS();
        $this->initializeMockData();
        $this->updateTerminalSize();

        // Calculate layout dimensions
        $this->sidebarWidth = max(30, (int) ($this->terminalWidth * 0.25));
        $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;

        // Set up terminal for raw mode
        system('stty -echo -icanon min 1 time 0');

        // Hide cursor
        echo "\033[?25l";

        // Clear screen
        $this->clearScreen();
    }

    public function __destruct()
    {
        // Restore terminal
        system('stty echo icanon');
        echo "\033[?25h"; // Show cursor
        //        $this->clearScreen();
        echo "Goodbye!\n";
    }

    public function run(): void
    {
        $this->render();

        while (true) {
            $key = $this->readKey();

            if ($key === null) {
                usleep(50000); // 50ms delay

                continue;
            }

            $handled = false;

            // Handle overlay-specific keys first
            if ($this->showTaskOverlay) {
                $handled = $this->handleTaskOverlayInput($key);
            } elseif ($this->showHelp) {
                $handled = $this->handleHelpInput($key);
            } else {
                // Handle focus-specific input
                switch ($this->currentFocus) {
                    case self::FOCUS_MAIN:
                        $handled = $this->handleMainInput($key);
                        break;
                    case self::FOCUS_TASKS:
                        $handled = $this->handleTasksInput($key);
                        break;
                    case self::FOCUS_CONTEXT:
                        $handled = $this->handleContextInput($key);
                        break;
                }
            }

            $this->render();
        }
    }

    private function detectOS(): void
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

    private function initializeMockData(): void
    {
        // Mock command history
        $this->history = [
            ['time' => time() - 300, 'type' => 'command', 'content' => 'Create a function to validate email addresses'],
            ['time' => time() - 295, 'type' => 'status', 'content' => 'Analyzing requirements...'],
            ['time' => time() - 290, 'type' => 'tool', 'tool' => 'read_file', 'params' => 'src/validators.php', 'result' => 'Found existing validation utilities'],
            ['time' => time() - 285, 'type' => 'tool', 'tool' => 'write_file', 'params' => 'src/validators.php', 'result' => "+ function validateEmail(\$email) {\n+     return filter_var(\$email, FILTER_VALIDATE_EMAIL);\n+ }"],
            ['time' => time() - 280, 'type' => 'status', 'content' => 'Email validation function created'],
            ['time' => time() - 120, 'type' => 'command', 'content' => 'Add advanced email validation with DNS checks'],
            [
                'time' => time() - 118,
                'type' => 'assistant',
                'content' => 'I\'ll enhance the email validation function to include DNS record verification for more robust validation.',
                'thought' => 'The user wants to add DNS validation to the email validator. This is a good security practice as it verifies that the email domain actually exists and can receive emails. I should check for MX records primarily, and fall back to A records if no MX records exist. This will help prevent invalid domains while still allowing legitimate email addresses. I\'ll need to handle DNS lookup failures gracefully and provide appropriate error messages. The implementation should also consider performance implications since DNS lookups can be slow.',
            ],
            ['time' => time() - 115, 'type' => 'tool', 'tool' => 'read_file', 'params' => 'src/validators.php', 'result' => 'Reading current validator implementation'],
            ['time' => time() - 110, 'type' => 'status', 'content' => 'Adding DNS validation...'],
        ];

        // Mock tasks
        $this->tasks = [
            ['id' => 1, 'status' => 'completed', 'description' => 'Setup project structure', 'steps' => 3, 'completed_steps' => 3],
            ['id' => 2, 'status' => 'running', 'description' => 'Create email validator function', 'steps' => 4, 'completed_steps' => 2],
            ['id' => 3, 'status' => 'pending', 'description' => 'Write unit tests', 'steps' => 0, 'completed_steps' => 0],
            ['id' => 4, 'status' => 'pending', 'description' => 'Update documentation', 'steps' => 0, 'completed_steps' => 0],
            ['id' => 5, 'status' => 'pending', 'description' => 'Add intl domain support', 'steps' => 0, 'completed_steps' => 0],
        ];

        // Set current task
        $runningTask = array_filter($this->tasks, fn ($t) => $t['status'] === 'running');
        if (! empty($runningTask)) {
            $task = reset($runningTask);
            $this->currentTask = $task['description'];
            $this->currentStep = $task['completed_steps'];
            $this->totalSteps = $task['steps'];
            $this->status = 'working';
        }

        // Add some initial context notes
        $this->context['notes'] = [
            'Email validation RFC 5322',
            'Support unicode domains',
            'Check MX records optional',
        ];
    }

    private function updateTerminalSize(): void
    {
        $this->terminalHeight = (int) exec('tput lines') ?: 24;
        $this->terminalWidth = (int) exec('tput cols') ?: 80;
    }

    private function clearScreen(): void
    {
        // Clear screen and scrollback buffer, then move cursor to home
        echo "\033[2J\033[3J\033[H";
    }

    private function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    private function readKey(): ?string
    {
        $key = fgetc(STDIN);

        if ($key === false || $key === '') {
            return null;
        }

        // Handle escape sequences
        if ($key === "\033") {
            $seq = $key;
            $seq .= fgetc(STDIN);

            // Check for Alt key combinations (ESC followed by character)
            if ($seq[1] !== '[' && $seq[1] !== false && $seq[1] !== "\033") {
                return 'ALT+' . mb_strtoupper($seq[1]);
            }

            // If we got another ESC, it might be Option+Arrow on macOS
            if ($seq[1] === "\033") {
                // Read the rest of the sequence and discard it
                $seq .= fgetc(STDIN);
                if ($seq[2] === '[') {
                    // Read until we get a letter (end of sequence)
                    while (true) {
                        $char = fgetc(STDIN);
                        if ($char === false || ctype_alpha($char)) {
                            break;
                        }
                    }
                }

                return null; // Ignore Option+Arrow sequences
            }

            $seq .= fgetc(STDIN);

            // Check for extended sequences (like Option+Arrow)
            if (preg_match('/^\033\[1;9[A-D]$/', $seq)) {
                // This is Option+Arrow, ignore it
                return null;
            }

            // Check for other modifier sequences
            if ($seq[2] === ';' || ctype_digit($seq[2])) {
                // Read the rest of the sequence
                while (true) {
                    $char = fgetc(STDIN);
                    $seq .= $char;
                    if ($char === false || ctype_alpha($char)) {
                        break;
                    }
                }

                // Ignore complex sequences
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

            // Unknown sequence, ignore it
            return null;
        }

        // Tab key
        if ($key === "\t") {
            return 'TAB';
        }

        return $key;
    }

    private function handleMainInput(string $key): bool
    {
        // Check for Alt combinations first
        if (str_starts_with($key, 'ALT+')) {
            return $this->handleGlobalShortcuts($key);
        }

        // Tab to cycle focus
        if ($key === 'TAB') {
            $this->currentFocus = self::FOCUS_TASKS;

            return true;
        }

        // Regular text input
        if ($key === "\n") {
            if (! empty($this->input)) {
                $this->addHistory('command', $this->input);
                $this->simulateResponse($this->input);
                $this->input = '';
            }

            return true;
        } elseif ($key === "\177" || $key === "\010") { // Backspace
            if (mb_strlen($this->input) > 0) {
                $this->input = mb_substr($this->input, 0, -1);
            }

            return true;
        } elseif (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
            $this->input .= $key;

            return true;
        }

        return false;
    }

    private function handleTasksInput(string $key): bool
    {
        // Check for Alt combinations
        if (str_starts_with($key, 'ALT+')) {
            return $this->handleGlobalShortcuts($key);
        }

        switch ($key) {
            case 'TAB':
                $this->currentFocus = self::FOCUS_CONTEXT;

                return true;
            case 'UP':
            case 'k':
                if ($this->selectedTaskIndex > 0) {
                    $this->selectedTaskIndex--;
                }

                return true;
            case 'DOWN':
            case 'j':
                if ($this->selectedTaskIndex < count($this->tasks) - 1) {
                    $this->selectedTaskIndex++;
                }

                return true;
            case "\n": // Enter - select task
                $task = $this->tasks[$this->selectedTaskIndex];
                $this->addHistory('command', "Switch to task: {$task['description']}");
                $this->currentFocus = self::FOCUS_MAIN;

                return true;
            case 'ESC':
                $this->currentFocus = self::FOCUS_MAIN;

                return true;
        }

        // Number keys for quick jump
        if (mb_strlen($key) === 1 && $key >= '1' && $key <= '9') {
            $index = intval($key) - 1;
            if ($index < count($this->tasks)) {
                $this->selectedTaskIndex = $index;
            }

            return true;
        }

        return false;
    }

    private function handleContextInput(string $key): bool
    {
        // Check for Alt combinations
        if (str_starts_with($key, 'ALT+')) {
            return $this->handleGlobalShortcuts($key);
        }

        switch ($key) {
            case 'TAB':
                $this->currentFocus = self::FOCUS_MAIN;

                return true;
            case 'UP':
                if ($this->selectedContextLine > 0) {
                    $this->selectedContextLine--;
                }

                return true;
            case 'DOWN':
                $totalLines = 3 + count($this->context['files']) + count($this->context['notes']) + 2;
                if ($this->selectedContextLine < $totalLines - 1) {
                    $this->selectedContextLine++;
                }

                return true;
            case "\n": // Enter - add note
                if (! empty($this->contextInput)) {
                    $this->context['notes'][] = $this->contextInput;
                    $this->contextInput = '';
                    $this->addHistory('system', 'Added context note');
                }

                return true;
            case "\177": // Backspace
            case "\010":
                // If we're on a note line, delete the note
                $noteStart = 3 + count($this->context['files']) + 1;
                $noteIndex = $this->selectedContextLine - $noteStart;
                if ($noteIndex >= 0 && $noteIndex < count($this->context['notes'])) {
                    array_splice($this->context['notes'], $noteIndex, 1);
                    $this->addHistory('system', 'Removed context note');
                    // Adjust selected line if needed
                    if ($this->selectedContextLine > 0) {
                        $this->selectedContextLine--;
                    }
                } elseif (mb_strlen($this->contextInput) > 0) {
                    // Otherwise handle input backspace
                    $this->contextInput = mb_substr($this->contextInput, 0, -1);
                }

                return true;
            case 'ESC':
                $this->currentFocus = self::FOCUS_MAIN;
                $this->contextInput = '';

                return true;
        }

        // Regular text input for notes
        if (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
            $this->contextInput .= $key;

            return true;
        }

        return false;
    }

    private function handleGlobalShortcuts(string $key): bool
    {
        switch ($key) {
            case 'ALT+Q':
                exit(0);

            case 'ALT+T':
                $this->showTaskOverlay = ! $this->showTaskOverlay;

                return true;
            case 'ALT+H':
                $this->showHelp = true;

                return true;
            case 'ALT+C':
                if ($this->currentFocus === self::FOCUS_MAIN) {
                    $this->history = [];
                    $this->addHistory('system', 'History cleared');
                }

                return true;
            case 'ALT+R':
                // Check if we're hovering over a thought that can be expanded/collapsed
                $thoughtToggled = $this->toggleNearestThought();
                if (! $thoughtToggled) {
                    // If no thought was toggled, refresh display as before
                    $this->updateTerminalSize();
                    $this->sidebarWidth = max(30, (int) ($this->terminalWidth * 0.25));
                    $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;
                    $this->addHistory('system', 'Display refreshed');
                }

                return true;
            case 'ALT+1':
                $this->currentFocus = self::FOCUS_MAIN;

                return true;
            case 'ALT+2':
                $this->currentFocus = self::FOCUS_TASKS;

                return true;
            case 'ALT+3':
                $this->currentFocus = self::FOCUS_CONTEXT;

                return true;
        }

        return false;
    }

    private function handleTaskOverlayInput(string $key): bool
    {
        if ($key === 'ESC' || $key === 'ALT+T') {
            $this->showTaskOverlay = false;

            return true;
        }

        switch ($key) {
            case 'UP':
            case 'k':
                if ($this->selectedTaskIndex > 0) {
                    $this->selectedTaskIndex--;
                    $this->adjustTaskScroll();
                }

                return true;
            case 'DOWN':
            case 'j':
                if ($this->selectedTaskIndex < count($this->tasks) - 1) {
                    $this->selectedTaskIndex++;
                    $this->adjustTaskScroll();
                }

                return true;
            case "\n": // Enter
                $task = $this->tasks[$this->selectedTaskIndex];
                $this->addHistory('command', "Switch to task: {$task['description']}");
                $this->showTaskOverlay = false;

                return true;
        }

        return false;
    }

    private function handleHelpInput(string $key): bool
    {
        $this->showHelp = false;

        return true;
    }

    private function simulateResponse(string $command): void
    {
        $this->addHistory('status', 'Processing request...');

        if (mb_stripos($command, 'test') !== false) {
            $this->addHistory('tool', 'read_file', 'tests/ValidatorTest.php', 'Reading test file');
            $this->addHistory('tool', 'write_file', 'tests/ValidatorTest.php', 'Added email validation tests');
            $this->addHistory('status', 'Tests created successfully');
        } else {
            $this->addHistory('tool', 'grep', '"function" src/*.php', 'Searching for functions');
            $this->addHistory('status', 'Analysis complete');
        }
    }

    private function addHistory(string $type, string $content, string $params = '', string $result = ''): void
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

        if (count($this->history) > 50) {
            array_shift($this->history);
        }
    }

    private function adjustTaskScroll(): void
    {
        $visibleHeight = $this->terminalHeight - 10;

        if ($this->selectedTaskIndex < $this->taskScrollOffset) {
            $this->taskScrollOffset = $this->selectedTaskIndex;
        } elseif ($this->selectedTaskIndex >= $this->taskScrollOffset + $visibleHeight) {
            $this->taskScrollOffset = $this->selectedTaskIndex - $visibleHeight + 1;
        }
    }

    private function render(): void
    {
        // Check for terminal resize
        $oldWidth = $this->terminalWidth;
        $oldHeight = $this->terminalHeight;
        $this->updateTerminalSize();

        if ($oldWidth !== $this->terminalWidth || $oldHeight !== $this->terminalHeight) {
            $this->sidebarWidth = max(30, (int) ($this->terminalWidth * 0.25));
            $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;
        }

        $this->clearScreen();

        if ($this->showTaskOverlay) {
            $this->renderWithOverlay();
        } elseif ($this->showHelp) {
            $this->renderWithHelp();
        } else {
            $this->renderMainView();
        }
    }

    private function renderMainView(): void
    {
        // Hide cursor during rendering
        echo "\033[?25l";

        // Draw the vertical divider
        for ($row = 1; $row <= $this->terminalHeight; $row++) {
            $this->moveCursor($row, $this->mainAreaWidth + 1);
            echo self::DIM . self::BOX_V_HEAVY . self::RESET;
        }

        // Render sidebar first
        $this->renderSidebar();

        // Render main area last (so cursor ends up at prompt)
        $this->renderMainArea();
    }

    private function renderMainArea(): void
    {
        $row = 1;
        $isActive = $this->currentFocus === self::FOCUS_MAIN;

        // Single-line status bar with 1 char inset
        $this->moveCursor($row++, 2);

        // Build the status content
        $prefix = ' 💮 swarm ';
        $prefix .= self::BOX_V . ' ';

        $task = '● ' . $this->truncate($this->currentTask, 30) . ' ' . self::BOX_V . ' ';

        $status = $this->status;
        if ($this->totalSteps > 0) {
            $status .= " ({$this->currentStep}/{$this->totalSteps})";
        }

        // Calculate actual text lengths
        $prefixLen = mb_strlen($prefix);
        $taskLen = mb_strlen($task);
        $statusLen = mb_strlen($status);
        $totalLen = $prefixLen + $taskLen + $statusLen;
        $padding = str_repeat(' ', max(0, $this->mainAreaWidth - $totalLen - 1));

        // Render with background color
        echo self::BG_DARK;
        echo $prefix;
        echo self::GREEN . '● ' . self::WHITE . $this->truncate($this->currentTask, 30);
        echo self::DIM . ' ' . self::BOX_V . ' ' . self::RESET . self::BG_DARK;
        echo self::YELLOW . $status . self::RESET . self::BG_DARK;
        echo $padding;
        echo self::RESET . "\n";

        // Recent activity with inset
        if (! empty($this->history)) {
            $row++; // Add blank line before recent activity
            $this->moveCursor($row++, 2);
            echo self::BOLD . 'Recent activity:' . self::RESET;

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

        // Footer separator - clean horizontal line
        $this->moveCursor($this->terminalHeight - 3, 1);
        echo self::DIM . str_repeat(self::BOX_H, $this->mainAreaWidth);
        echo self::BOX_R . self::RESET; // ┤

        // Footer hints with inset
        $this->moveCursor($this->terminalHeight - 2, 2);
        echo self::DIM . "{$this->modSymbol}T: tasks  {$this->modSymbol}H: help  Tab: switch pane  {$this->modSymbol}Q: quit" . self::RESET;

        // Prompt with inset
        $this->moveCursor($this->terminalHeight, 2);
        if ($isActive) {
            echo self::BLUE . 'swarm >' . self::RESET . ' ' . $this->input;
            $this->moveCursor($this->terminalHeight, 10 + mb_strlen($this->input));
            echo "\033[?25h"; // Show cursor
        } else {
            echo self::DIM . 'swarm >' . self::RESET . ' ' . self::DIM . $this->input . self::RESET;
        }
    }

    private function renderSidebar(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 1;

        // Task Queue section
        $tasksActive = $this->currentFocus === self::FOCUS_TASKS;
        $this->moveCursor($row++, $col);
        echo self::BOLD . self::UNDERLINE . 'Task Queue' . self::RESET;
        if ($tasksActive) {
            echo self::BRIGHT_CYAN . ' [ACTIVE]' . self::RESET;
        }

        $running = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'running'));
        $pending = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'pending'));

        $this->moveCursor($row++, $col);
        echo self::GREEN . $running . ' running' . self::RESET . ', ' . self::DIM . $pending . ' pending' . self::RESET;

        $row++;

        // Show tasks
        $maxTasks = min(5, (int) (($this->terminalHeight / 2) - 4));
        $taskDisplay = array_slice($this->tasks, 0, $maxTasks);

        foreach ($taskDisplay as $i => $task) {
            $this->moveCursor($row++, $col);
            $isSelected = $tasksActive && $i === $this->selectedTaskIndex;
            if ($isSelected) {
                echo self::REVERSE;
            }
            $this->renderCompactTaskLine($task, $i + 1, $this->sidebarWidth - 4);
            if ($isSelected) {
                echo self::RESET;
            }
        }

        if (count($this->tasks) > $maxTasks) {
            $this->moveCursor($row++, $col);
            echo self::DIM . '... +' . (count($this->tasks) - $maxTasks) . ' more' . self::RESET;
        }

        // Separator
        $row += 1;
        $this->moveCursor($row++, $this->mainAreaWidth + 2);
        echo self::DIM . str_repeat(self::BOX_H, $this->sidebarWidth - 1) . self::RESET;

        // Context section
        $contextActive = $this->currentFocus === self::FOCUS_CONTEXT;
        $this->moveCursor($row++, $col);
        echo self::BOLD . self::UNDERLINE . 'Context' . self::RESET;
        if ($contextActive) {
            echo self::BRIGHT_CYAN . ' [ACTIVE]' . self::RESET;
        }
        $row++;

        $contextLine = 0;

        // Directory
        $this->moveCursor($row++, $col);
        $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
        echo ($isSelected ? self::REVERSE : '') . self::CYAN . 'Dir:' . self::RESET;
        $this->moveCursor($row++, $col);
        echo '  ' . $this->truncate($this->context['directory'], $this->sidebarWidth - 5);
        $row++;

        // Files
        $this->moveCursor($row++, $col);
        $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
        echo ($isSelected ? self::REVERSE : '') . self::YELLOW . 'Files:' . self::RESET;
        foreach ($this->context['files'] as $file) {
            if ($row >= $this->terminalHeight - 8) {
                break;
            }
            $this->moveCursor($row++, $col);
            $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
            echo ($isSelected ? self::REVERSE : '') . '  ' . $this->truncate($file, $this->sidebarWidth - 5) . self::RESET;
        }

        // Notes
        if ($row < $this->terminalHeight - 6) {
            $row++;
            $this->moveCursor($row++, $col);
            $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
            echo ($isSelected ? self::REVERSE : '') . self::MAGENTA . 'Notes:' . self::RESET;

            foreach ($this->context['notes'] as $i => $note) {
                if ($row >= $this->terminalHeight - 4) {
                    break;
                }
                $this->moveCursor($row++, $col);
                $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
                echo ($isSelected ? self::REVERSE : '') . '  • ' . $this->truncate($note, $this->sidebarWidth - 6) . self::RESET;
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

    private function renderHistoryEntry(array $entry, int $maxWidth, int $currentRow): int
    {
        $time = date('H:i:s', $entry['time']);
        $prefix = self::DIM . "[{$time}]" . self::RESET . ' ';
        $prefixLen = 11;

        $rowsUsed = 1;

        switch ($entry['type']) {
            case 'command':
                echo $prefix . self::BLUE . '$' . self::RESET . ' ';
                echo $this->truncate($entry['content'], $maxWidth - $prefixLen - 2);
                break;
            case 'status':
                echo $prefix . self::GREEN . '✓' . self::RESET . ' ';
                echo $this->truncate($entry['content'], $maxWidth - $prefixLen - 2);
                break;
            case 'tool':
                echo $prefix . self::CYAN . '>' . self::RESET . ' ';
                $toolStr = "{$entry['tool']} {$entry['params']}";
                echo $this->truncate($toolStr, $maxWidth - $prefixLen - 2);
                break;
            case 'system':
                echo $prefix . self::YELLOW . '!' . self::RESET . ' ';
                echo self::DIM . $this->truncate($entry['content'], $maxWidth - $prefixLen - 2) . self::RESET;
                break;
            case 'assistant':
                echo $prefix . self::GREEN . '●' . self::RESET . ' ';
                echo $this->truncate($entry['content'], $maxWidth - $prefixLen - 2);

                // Handle thought display
                if (isset($entry['thought']) && ! empty($entry['thought'])) {
                    $thoughtId = md5($entry['time'] . $entry['thought']);
                    $isExpanded = in_array($thoughtId, $this->expandedThoughts);
                    $thoughtLines = $this->wrapText($entry['thought'], $maxWidth - $prefixLen - 4);

                    if (count($thoughtLines) > 4 && ! $isExpanded) {
                        // Show collapsed version
                        $this->moveCursor($currentRow + $rowsUsed, 2);
                        echo str_repeat(' ', $prefixLen) . self::DIM . self::ITALIC . '  ' . $thoughtLines[0] . self::RESET;
                        $rowsUsed++;

                        $this->moveCursor($currentRow + $rowsUsed, 2);
                        echo str_repeat(' ', $prefixLen) . self::DIM . self::ITALIC . '  ' . $thoughtLines[1] . self::RESET;
                        $rowsUsed++;

                        $this->moveCursor($currentRow + $rowsUsed, 2);
                        echo str_repeat(' ', $prefixLen) . self::DIM . self::ITALIC . '  ' . $thoughtLines[2] . self::RESET;
                        $rowsUsed++;

                        $this->moveCursor($currentRow + $rowsUsed, 2);
                        $remainingLines = count($thoughtLines) - 3;
                        echo str_repeat(' ', $prefixLen) . self::DIM . "  ... +{$remainingLines} more lines ({$this->modSymbol}R to expand)" . self::RESET;
                        $rowsUsed++;
                    } else {
                        // Show all lines
                        foreach ($thoughtLines as $line) {
                            $this->moveCursor($currentRow + $rowsUsed, 2);
                            echo str_repeat(' ', $prefixLen) . self::DIM . self::ITALIC . '  ' . $line . self::RESET;
                            $rowsUsed++;
                        }

                        if (count($thoughtLines) > 4) {
                            $this->moveCursor($currentRow + $rowsUsed, 2);
                            echo str_repeat(' ', $prefixLen) . self::DIM . "  ({$this->modSymbol}R to collapse)" . self::RESET;
                            $rowsUsed++;
                        }
                    }
                }
                break;
        }

        return $rowsUsed;
    }

    private function renderCompactTaskLine(array $task, int $number, int $maxWidth): void
    {
        $icon = match ($task['status']) {
            'completed' => self::GREEN . '✓',
            'running' => self::YELLOW . '▶',
            'pending' => self::DIM . '○',
            default => ' '
        };

        $num = mb_str_pad($number . '.', 3);
        $desc = $this->truncate($task['description'], $maxWidth - 6);

        echo "{$num} {$icon} " . self::RESET . $desc;

        if ($task['status'] === 'running' && $task['steps'] > 0) {
            $percent = round(($task['completed_steps'] / $task['steps']) * 100);
            echo ' ' . self::DIM . "{$percent}%" . self::RESET;
        }
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }

    private function renderWithOverlay(): void
    {
        $this->renderMainView();
        $this->renderTaskOverlay();
    }

    private function renderWithHelp(): void
    {
        $this->renderMainView();
        $this->renderHelpOverlay();
    }

    private function renderTaskOverlay(): void
    {
        // Calculate overlay dimensions to fit within main area
        $maxWidth = $this->mainAreaWidth - 4; // Leave some padding
        $width = min(70, $maxWidth);
        $height = min(20, $this->terminalHeight - 4);

        // Center within the main area, not the full terminal
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
                echo self::REVERSE;
            }

            $num = mb_str_pad($taskIndex + 1, 2);
            $icon = match ($task['status']) {
                'completed' => self::GREEN . '✓',
                'running' => self::YELLOW . '▶',
                'pending' => self::DIM . '○',
                default => ' '
            };

            $desc = mb_substr($task['description'], 0, $width - 20);
            $status = mb_str_pad("[{$task['status']}]", 12);

            echo "{$num}. {$icon} " . mb_str_pad($desc, $width - 20) . " {$status}";

            if ($taskIndex === $this->selectedTaskIndex) {
                echo self::RESET;
            }
        }

        if ($this->taskScrollOffset > 0) {
            $this->moveCursor($startRow + 2, $startCol + $width - 3);
            echo self::DIM . '▲' . self::RESET;
        }

        if ($this->taskScrollOffset + $visibleHeight < count($this->tasks)) {
            $this->moveCursor($startRow + $height - 2, $startCol + $width - 3);
            echo self::DIM . '▼' . self::RESET;
        }

        $this->moveCursor($startRow + $height - 1, $startCol + 2);
        echo self::DIM . "↑↓/jk: Navigate  Enter: Select  ESC/{$this->modSymbol}T: Close" . self::RESET;
    }

    private function renderHelpOverlay(): void
    {
        // Calculate overlay dimensions to fit within main area
        $maxWidth = $this->mainAreaWidth - 4;
        $width = min(60, $maxWidth);
        $height = 20;

        // Center within the main area
        $startCol = (int) (($this->mainAreaWidth - $width) / 2) + 1;
        $startRow = (int) (($this->terminalHeight - $height) / 2);

        $this->drawBox($startRow, $startCol, $width, $height, 'Help');

        $help = [
            ['heading' => "Global Shortcuts ({$this->modKey} + key):", 'items' => []],
            ['key' => "{$this->modSymbol}T", 'desc' => 'Toggle full task list'],
            ['key' => "{$this->modSymbol}H", 'desc' => 'Show this help'],
            ['key' => "{$this->modSymbol}C", 'desc' => 'Clear history (main pane only)'],
            ['key' => "{$this->modSymbol}R", 'desc' => 'Refresh display'],
            ['key' => "{$this->modSymbol}Q", 'desc' => 'Quit application'],
            ['key' => "{$this->modSymbol}1/2/3", 'desc' => 'Jump to pane (main/tasks/context)'],
            ['', ''],
            ['heading' => 'Navigation:', 'items' => []],
            ['key' => 'Tab', 'desc' => 'Cycle through panes'],
            ['key' => '↑↓/jk', 'desc' => 'Navigate in lists'],
            ['key' => 'Enter', 'desc' => 'Select/confirm'],
            ['key' => 'ESC', 'desc' => 'Cancel/return to main'],
            ['', ''],
            ['heading' => 'Context Pane:', 'items' => []],
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
                echo self::BOLD . self::UNDERLINE . $item['heading'] . self::RESET;
            } elseif (! empty($item['key'])) {
                echo self::CYAN . mb_str_pad($item['key'], 15) . self::RESET . $item['desc'];
            }
        }

        $this->moveCursor($startRow + $height - 1, $startCol + 2);
        echo self::DIM . 'Press any key to close' . self::RESET;
    }

    private function drawBox(int $row, int $col, int $width, int $height, string $title = ''): void
    {
        // Top border
        $this->moveCursor($row, $col);
        echo self::GRAY . self::BOX_TL;
        if ($title) {
            $titleLen = mb_strlen($title);
            $padding = (int) (($width - $titleLen - 4) / 2);
            echo str_repeat(self::BOX_H, $padding) . ' ' . self::WHITE . self::BOLD . $title . self::RESET . self::GRAY . ' ';
            echo str_repeat(self::BOX_H, $width - $padding - $titleLen - 4);
        } else {
            echo str_repeat(self::BOX_H, $width - 2);
        }
        echo self::BOX_TR . self::RESET;

        // Sides
        for ($i = 1; $i < $height - 1; $i++) {
            $this->moveCursor($row + $i, $col);
            echo self::GRAY . self::BOX_V . self::RESET . str_repeat(' ', $width - 2) . self::GRAY . self::BOX_V . self::RESET;
        }

        // Bottom border
        $this->moveCursor($row + $height - 1, $col);
        echo self::GRAY . self::BOX_BL . str_repeat(self::BOX_H, $width - 2) . self::BOX_BR . self::RESET;
    }

    private function wrapText(string $text, int $maxWidth): array
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

    private function toggleNearestThought(): bool
    {
        // Find the most recent assistant entry with a thought
        $recentHistory = array_slice($this->history, -20);
        foreach (array_reverse($recentHistory) as $entry) {
            if ($entry['type'] === 'assistant' && isset($entry['thought'])) {
                $thoughtId = md5($entry['time'] . $entry['thought']);
                $thoughtLines = $this->wrapText($entry['thought'], $this->mainAreaWidth - 15);

                // Only toggle if thought is long enough to be collapsible
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
}

// Run the UI
$ui = new TerminalUI3;
$ui->run();
