#!/usr/bin/env php
<?php

/**
 * AI Agent Activity UI - No Custom Background Version
 * Terminal UI that uses default terminal background colors
 *
 * Features:
 * - Uses terminal's default background
 * - Proper alternate screen buffer usage
 * - Double buffering for flicker-free rendering
 * - Differential updates for performance
 * - Mouse support and keyboard navigation
 * - Terminal resize handling
 * - Signal handling for clean exit
 */

// Modern ANSI escape codes
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const UNDERLINE = "\033[4m";
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";

// Proper alternate screen buffer sequences
const ENTER_ALT_SCREEN = "\033[?1049h";  // Save screen and enter alternate buffer
const EXIT_ALT_SCREEN = "\033[?1049l";   // Restore screen and exit alternate buffer

// Mouse support
const ENABLE_MOUSE = "\033[?1000h";      // Enable mouse click tracking
const DISABLE_MOUSE = "\033[?1000l";     // Disable mouse tracking

// Terminal modes
const DISABLE_WRAP = "\033[?7l";
const ENABLE_WRAP = "\033[?7h";

// Clear and positioning
const CLEAR_SCREEN = "\033[2J";
const HOME = "\033[H";
const CLEAR_LINE = "\033[2K";

// Color scheme - foreground colors only
const FG_WHITE = "\033[38;5;255m";
const FG_BRIGHT_WHITE = "\033[38;5;231m";
const FG_GRAY = "\033[38;5;245m";
const FG_DARK_GRAY = "\033[38;5;240m";
const FG_BLUE = "\033[38;5;117m";
const FG_GREEN = "\033[38;5;120m";
const FG_YELLOW = "\033[38;5;221m";
const FG_RED = "\033[38;5;203m";
const FG_PURPLE = "\033[38;5;141m";
const FG_CYAN = "\033[38;5;87m";
const FG_ORANGE = "\033[38;5;214m";

// Subtle background highlights (very dark)
const BG_SUBTLE_HIGHLIGHT = "\033[48;5;237m";
const BG_SELECTED = "\033[48;5;238m";

function moveTo(int $row, int $col): string
{
    return "\033[{$row};{$col}H";
}

function clearLine(): string
{
    return "\033[2K";
}

function formatTimeAgo(int $timestamp): string
{
    $diff = time() - $timestamp;

    if ($diff < 10) {
        return 'just now';
    }
    if ($diff < 60) {
        return $diff . 's ago';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }

    return floor($diff / 86400) . 'd ago';
}

class AgentActivityUI
{
    private int $width = 120;

    private int $height = 40;

    private int $sidebarWidth = 30;

    private int $frame = 0;

    // UI State
    private array $activities = [];

    private array $tasks = [];

    private bool $sidebarExpanded = true;

    private int $activityScrollOffset = 0;

    private int $taskScrollOffset = 0;

    private int $selectedTask = 0;

    // Double buffering
    private array $currentBuffer = [];  // Current screen state

    private array $nextBuffer = [];     // Next frame to render

    private string $outputBuffer = '';  // String buffer for atomic writes

    // Performance tracking
    private float $lastRenderTime = 0;

    private int $renderInterval = 50;   // 50ms = ~20 FPS

    private bool $forceFullRender = true;

    // Input handling
    private bool $running = false;

    private string $inputBuffer = '';

    // Terminal state
    private bool $mouseEnabled = false;

    private bool $altScreenActive = false;

    // Timer for activity updates
    private int $lastActivityUpdate = 0;

    private int $lastTaskUpdate = 0;

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->initializeBuffers();
        $this->initializeActivities();
        $this->initializeTasks();

        // Register signal handlers for clean exit
        $this->registerSignalHandlers();
    }

    public function handleSignal(int $signal): void
    {
        $this->cleanup();
        exit(0);
    }

    public function handleResize(int $signal): void
    {
        $this->updateTerminalSize();
    }

    public function run(): void
    {
        $this->initializeTerminal();
        $this->running = true;

        // Main event loop
        while ($this->running) {
            $currentTime = microtime(true) * 1000; // milliseconds

            // Handle input (non-blocking)
            $this->handleInput();

            // Update animations and state
            $this->update();

            // Render if enough time has passed
            if ($currentTime - $this->lastRenderTime >= $this->renderInterval) {
                $this->render();
                $this->lastRenderTime = $currentTime;
            }

            // Process pending signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Small sleep to prevent excessive CPU usage
            usleep(5000); // 5ms

            $this->frame++;
        }

        $this->cleanup();
    }

    public function cleanup(): void
    {
        if ($this->altScreenActive) {
            // Disable mouse
            if ($this->mouseEnabled) {
                echo DISABLE_MOUSE;
            }

            // Show cursor and enable wrap
            echo SHOW_CURSOR . ENABLE_WRAP;

            // Exit alternate screen buffer
            echo EXIT_ALT_SCREEN;

            $this->altScreenActive = false;
        }

        // Restore terminal settings
        system('stty echo icanon isig 2>/dev/null');
        stream_set_blocking(STDIN, true);
    }

    private function updateTerminalSize(): void
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;

        // Reinitialize buffers when size changes
        if ($this->altScreenActive) {
            $this->initializeBuffers();
            $this->forceFullRender = true;
        }
    }

    private function initializeBuffers(): void
    {
        // Initialize screen buffers with empty cells
        $this->currentBuffer = [];
        $this->nextBuffer = [];

        for ($row = 1; $row <= $this->height; $row++) {
            $this->currentBuffer[$row] = [];
            $this->nextBuffer[$row] = [];
            for ($col = 1; $col <= $this->width; $col++) {
                $this->currentBuffer[$row][$col] = ['char' => ' ', 'style' => RESET];
                $this->nextBuffer[$row][$col] = ['char' => ' ', 'style' => RESET];
            }
        }
    }

    private function registerSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGWINCH, [$this, 'handleResize']);
        }
    }

    private function initializeTerminal(): void
    {
        // Enter alternate screen buffer
        echo ENTER_ALT_SCREEN;

        // Set up raw mode for input
        system('stty -echo -icanon -isig min 1 time 0 2>/dev/null');

        // Configure terminal
        echo HIDE_CURSOR . DISABLE_WRAP;

        // Enable mouse support
        echo ENABLE_MOUSE;

        // Make STDIN non-blocking
        stream_set_blocking(STDIN, false);

        $this->altScreenActive = true;
        $this->mouseEnabled = true;
        $this->forceFullRender = true;
    }

    private function handleInput(): void
    {
        $input = fread(STDIN, 1024);
        if ($input === false || $input === '') {
            return;
        }

        $this->inputBuffer .= $input;

        // Process complete input sequences
        while (mb_strlen($this->inputBuffer) > 0) {
            $processed = $this->processInputSequence();
            if (! $processed) {
                break; // Need more input for complete sequence
            }
        }
    }

    private function processInputSequence(): bool
    {
        if (mb_strlen($this->inputBuffer) === 0) {
            return false;
        }

        $char = $this->inputBuffer[0];

        // Handle escape sequences
        if ($char === "\033" && mb_strlen($this->inputBuffer) >= 3) {
            $sequence = mb_substr($this->inputBuffer, 0, 3);
            $this->inputBuffer = mb_substr($this->inputBuffer, 3);

            switch ($sequence) {
                case "\033[A": // Up arrow
                    $this->selectedTask = max(0, $this->selectedTask - 1);
                    break;
                case "\033[B": // Down arrow
                    $this->selectedTask = min(count($this->tasks) - 1, $this->selectedTask + 1);
                    break;
                case "\033[C": // Right arrow
                    $this->sidebarExpanded = true;
                    break;
                case "\033[D": // Left arrow
                    $this->sidebarExpanded = false;
                    break;
            }

            return true;
        }

        // Handle regular keys
        $this->inputBuffer = mb_substr($this->inputBuffer, 1);

        switch ($char) {
            case 'q':
            case 'Q':
            case "\x03": // Ctrl+C
                $this->running = false;
                break;
            case 't':
            case 'T':
                $this->sidebarExpanded = ! $this->sidebarExpanded;
                break;
            case 'r':
            case 'R':
                $this->forceFullRender = true;
                break;
        }

        return true;
    }

    private function initializeActivities(): void
    {
        $this->activities = [
            ['time' => time() - 2, 'type' => 'thinking', 'agent' => 'Analyzer', 'message' => 'Analyzing codebase structure...', 'duration' => 1.2],
            ['time' => time() - 5, 'type' => 'tool', 'agent' => 'Coder', 'message' => 'Reading file: src/Application.php', 'tool' => 'ReadFile', 'status' => 'success'],
            ['time' => time() - 8, 'type' => 'response', 'agent' => 'Planner', 'message' => 'Created implementation plan with 5 steps'],
            ['time' => time() - 12, 'type' => 'tool', 'agent' => 'Coder', 'message' => 'Writing file: src/NewFeature.php', 'tool' => 'WriteFile', 'status' => 'success'],
            ['time' => time() - 18, 'type' => 'thinking', 'agent' => 'Reviewer', 'message' => 'Reviewing code changes...', 'duration' => 2.3],
            ['time' => time() - 25, 'type' => 'tool', 'agent' => 'Tester', 'message' => 'Running tests: phpunit', 'tool' => 'Terminal', 'status' => 'success'],
            ['time' => time() - 30, 'type' => 'error', 'agent' => 'Debugger', 'message' => 'Found issue: Undefined variable $config on line 42'],
            ['time' => time() - 35, 'type' => 'tool', 'agent' => 'Coder', 'message' => 'Fixed issue in Application.php', 'tool' => 'EditFile', 'status' => 'success'],
            ['time' => time() - 45, 'type' => 'response', 'agent' => 'Assistant', 'message' => 'Task completed successfully! All tests passing.'],
            ['time' => time() - 60, 'type' => 'thinking', 'agent' => 'Architect', 'message' => 'Evaluating system design patterns...', 'duration' => 3.1],
        ];
    }

    private function initializeTasks(): void
    {
        $this->tasks = [
            ['id' => 1, 'title' => 'Implement user authentication', 'status' => 'completed', 'agent' => 'Coder', 'subtasks' => [
                ['title' => 'Create User model', 'done' => true],
                ['title' => 'Add login controller', 'done' => true],
                ['title' => 'Setup JWT tokens', 'done' => true],
            ]],
            ['id' => 2, 'title' => 'Refactor database layer', 'status' => 'in_progress', 'agent' => 'Architect', 'progress' => 65, 'subtasks' => [
                ['title' => 'Extract repository pattern', 'done' => true],
                ['title' => 'Update models', 'done' => true],
                ['title' => 'Migrate queries', 'done' => false],
                ['title' => 'Update tests', 'done' => false],
            ]],
            ['id' => 3, 'title' => 'API documentation', 'status' => 'in_progress', 'agent' => 'Documenter', 'progress' => 30, 'subtasks' => [
                ['title' => 'Generate OpenAPI spec', 'done' => true],
                ['title' => 'Write endpoint descriptions', 'done' => false],
                ['title' => 'Add examples', 'done' => false],
            ]],
            ['id' => 4, 'title' => 'Performance optimization', 'status' => 'pending', 'agent' => 'Optimizer', 'subtasks' => [
                ['title' => 'Profile application', 'done' => false],
                ['title' => 'Optimize queries', 'done' => false],
                ['title' => 'Add caching', 'done' => false],
            ]],
            ['id' => 5, 'title' => 'Security audit', 'status' => 'pending', 'agent' => 'Security', 'subtasks' => [
                ['title' => 'Check dependencies', 'done' => false],
                ['title' => 'Review auth flow', 'done' => false],
            ]],
        ];
    }

    private function render(): void
    {
        // Clear next buffer
        $this->clearNextBuffer();

        // Render all components to next buffer
        $this->renderHeader();
        $this->renderSidebar();
        $this->renderActivityFeed();
        $this->renderStatusBar();

        // Perform differential update or full render
        if ($this->forceFullRender) {
            $this->performFullRender();
            $this->forceFullRender = false;
        } else {
            $this->performDifferentialRender();
        }

        // Swap buffers
        $temp = $this->currentBuffer;
        $this->currentBuffer = $this->nextBuffer;
        $this->nextBuffer = $temp;
    }

    private function clearNextBuffer(): void
    {
        for ($row = 1; $row <= $this->height; $row++) {
            for ($col = 1; $col <= $this->width; $col++) {
                $this->nextBuffer[$row][$col] = ['char' => ' ', 'style' => RESET];
            }
        }
    }

    private function performFullRender(): void
    {
        $this->outputBuffer = HOME;

        for ($row = 1; $row <= $this->height; $row++) {
            $this->outputBuffer .= $this->moveTo($row, 1);
            $currentStyle = '';

            for ($col = 1; $col <= $this->width; $col++) {
                $cell = $this->nextBuffer[$row][$col];

                // Only add style codes when they change
                if ($cell['style'] !== $currentStyle) {
                    $this->outputBuffer .= $cell['style'];
                    $currentStyle = $cell['style'];
                }

                $this->outputBuffer .= $cell['char'];
            }
        }

        // Atomic write
        echo $this->outputBuffer;
        $this->outputBuffer = '';
    }

    private function performDifferentialRender(): void
    {
        $this->outputBuffer = '';
        $currentStyle = '';

        for ($row = 1; $row <= $this->height; $row++) {
            $lineChanged = false;
            $changes = [];

            // Detect changes in this row
            for ($col = 1; $col <= $this->width; $col++) {
                $current = $this->currentBuffer[$row][$col] ?? ['char' => ' ', 'style' => RESET];
                $next = $this->nextBuffer[$row][$col];

                if ($current['char'] !== $next['char'] || $current['style'] !== $next['style']) {
                    $changes[] = $col;
                    $lineChanged = true;
                }
            }

            // If line changed, update it efficiently
            if ($lineChanged) {
                // Group consecutive changes
                $groups = $this->groupConsecutiveChanges($changes);

                foreach ($groups as $group) {
                    $startCol = $group[0];
                    $endCol = $group[count($group) - 1];

                    $this->outputBuffer .= $this->moveTo($row, $startCol);

                    for ($col = $startCol; $col <= $endCol; $col++) {
                        $cell = $this->nextBuffer[$row][$col];

                        if ($cell['style'] !== $currentStyle) {
                            $this->outputBuffer .= $cell['style'];
                            $currentStyle = $cell['style'];
                        }

                        $this->outputBuffer .= $cell['char'];
                    }
                }
            }
        }

        // Atomic write
        if (mb_strlen($this->outputBuffer) > 0) {
            echo $this->outputBuffer;
            $this->outputBuffer = '';
        }
    }

    private function groupConsecutiveChanges(array $changes): array
    {
        if (empty($changes)) {
            return [];
        }

        $groups = [];
        $currentGroup = [$changes[0]];

        for ($i = 1; $i < count($changes); $i++) {
            if ($changes[$i] === $changes[$i - 1] + 1) {
                $currentGroup[] = $changes[$i];
            } else {
                $groups[] = $currentGroup;
                $currentGroup = [$changes[$i]];
            }
        }

        $groups[] = $currentGroup;

        return $groups;
    }

    private function setCell(int $row, int $col, string $char, string $style = RESET): void
    {
        if ($row >= 1 && $row <= $this->height && $col >= 1 && $col <= $this->width) {
            $this->nextBuffer[$row][$col] = ['char' => $char, 'style' => $style];
        }
    }

    private function setCellString(int $row, int $col, string $text, string $style = RESET): void
    {
        $length = mb_strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $this->setCell($row, $col + $i, mb_substr($text, $i, 1), $style);
        }
    }

    private function moveTo(int $row, int $col): string
    {
        return "\033[{$row};{$col}H";
    }

    private function renderHeader(): void
    {
        // Draw header line with subtle separator
        for ($col = 1; $col <= $this->width; $col++) {
            $this->setCell(2, $col, '─', FG_DARK_GRAY);
        }

        // Static title
        $title = '🤖 AI AGENT ACTIVITY MONITOR';
        $this->setCellString(1, 2, $title, FG_BLUE . BOLD);

        // Right side info with live updates
        $time = date('H:i:s');
        $fps = $this->calculateFPS();
        $rightText = sprintf('FPS: %.1f | Agents: 3 | %s ', $fps, $time);
        $startCol = $this->width - mb_strlen($rightText) + 1;
        $this->setCellString(1, $startCol, $rightText, FG_GRAY);
    }

    private function calculateFPS(): float
    {
        static $lastTime = 0;
        static $frameCount = 0;
        static $fps = 0;

        $currentTime = microtime(true);
        $frameCount++;

        if ($currentTime - $lastTime >= 1.0) {
            $fps = $frameCount / ($currentTime - $lastTime);
            $frameCount = 0;
            $lastTime = $currentTime;
        }

        return $fps;
    }

    private function renderSidebar(): void
    {
        $effectiveWidth = $this->sidebarExpanded ? $this->sidebarWidth : 3;
        $startRow = 3;
        $sidebarHeight = $this->height - 4;

        // Draw vertical separator
        for ($row = $startRow; $row < $startRow + $sidebarHeight; $row++) {
            $this->setCell($row, $effectiveWidth + 1, '│', FG_DARK_GRAY);
        }

        if ($this->sidebarExpanded) {
            // Static header
            $this->setCellString($startRow, 2, '📋 TASK QUEUE', FG_WHITE . BOLD);

            // Collapse button
            $this->setCell($startRow, $this->sidebarWidth - 1, '◀', FG_GRAY);

            // Static separator
            $separatorRow = $startRow + 1;
            for ($col = 2; $col < $this->sidebarWidth - 1; $col++) {
                $this->setCell($separatorRow, $col, '─', FG_DARK_GRAY);
            }

            // Tasks
            $this->renderTasks($startRow + 3);
        } else {
            // Collapsed state
            $this->setCellString($startRow, 1, ' ▶ ', FG_GRAY);

            // Vertical text
            $text = 'TASKS';
            for ($i = 0; $i < mb_strlen($text); $i++) {
                $this->setCell($startRow + 3 + $i, 2, $text[$i], FG_GRAY);
            }
        }
    }

    private function renderTasks(int $startRow): void
    {
        $maxVisible = $this->height - $startRow - 3;
        $visibleTasks = array_slice($this->tasks, $this->taskScrollOffset, $maxVisible);

        $row = $startRow;
        foreach ($visibleTasks as $index => $task) {
            $isSelected = ($index + $this->taskScrollOffset) === $this->selectedTask;

            // Task background with selection highlighting
            $bgStyle = $isSelected ? BG_SELECTED : '';

            // Status icon with colors
            $statusData = match ($task['status']) {
                'completed' => ['icon' => '✓', 'style' => FG_GREEN],
                'in_progress' => ['icon' => '◐', 'style' => FG_YELLOW],
                'pending' => ['icon' => '○', 'style' => FG_GRAY],
                default => ['icon' => '·', 'style' => FG_DARK_GRAY]
            };

            $iconStyle = $bgStyle . $statusData['style'];
            $this->setCell($row, 2, $statusData['icon'], $iconStyle);
            $this->setCell($row, 3, ' ', $bgStyle);

            // Task title with truncation
            $maxTitleLen = $this->sidebarWidth - 7;
            $title = mb_substr($task['title'], 0, $maxTitleLen);
            $titleStyle = $bgStyle . ($isSelected ? FG_BRIGHT_WHITE : FG_WHITE);
            $this->setCellString($row, 4, $title, $titleStyle);

            // Fill rest of row if selected
            if ($isSelected) {
                for ($col = 4 + mb_strlen($title); $col <= $this->sidebarWidth; $col++) {
                    $this->setCell($row, $col, ' ', $bgStyle);
                }
            }

            $row++;

            // Agent assignment
            if (isset($task['agent'])) {
                $agentText = '→ ' . $task['agent'];
                $this->setCellString($row, 4, $agentText, FG_CYAN . DIM);
                $row++;
            }

            // Animated progress bar for in-progress tasks
            if ($task['status'] === 'in_progress' && isset($task['progress'])) {
                $this->renderAnimatedProgressBar($row, 4, $task['progress'], 18);
                $row++;
            }

            // Subtasks
            if ($isSelected && isset($task['subtasks'])) {
                foreach ($task['subtasks'] as $subIndex => $subtask) {
                    if ($row >= $this->height - 2) {
                        break;
                    }

                    $checkbox = $subtask['done'] ? '☑' : '☐';
                    $subtaskText = $checkbox . ' ' . mb_substr($subtask['title'], 0, $maxTitleLen - 6);
                    $this->setCellString($row, 4, $subtaskText, FG_GRAY);
                    $row++;
                }
            }

            $row++; // Space between tasks

            if ($row >= $this->height - 2) {
                break;
            }
        }

        // Task count at bottom
        $completed = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));
        $countText = sprintf('%d/%d tasks', $completed, count($this->tasks));
        $this->setCellString($this->height - 2, 2, $countText, FG_GRAY . DIM);
    }

    private function renderAnimatedProgressBar(int $row, int $col, float $progress, int $width): void
    {
        $filled = (int) ($progress / 100 * $width);

        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                // Static filled portion
                $this->setCell($row, $col + $i, '█', FG_GREEN);
            } else {
                // Empty portion
                $this->setCell($row, $col + $i, '░', FG_DARK_GRAY);
            }
        }

        // Percentage text
        $percentText = sprintf(' %d%%', $progress);
        $this->setCellString($row, $col + $width, $percentText, FG_GRAY);
    }

    private function renderActivityFeed(): void
    {
        $feedStart = $this->sidebarExpanded ? $this->sidebarWidth + 3 : 5;
        $feedWidth = $this->width - $feedStart - 1;
        $feedHeight = $this->height - 3;

        // Feed header
        $this->setCellString(3, $feedStart + 2, 'AGENT ACTIVITY TIMELINE', FG_WHITE . BOLD);

        // Scrolling activities with stagger animation
        $row = 5;
        $visibleActivities = array_slice($this->activities, $this->activityScrollOffset, 15);

        foreach ($visibleActivities as $index => $activity) {
            if ($row >= $this->height - 2) {
                break;
            }

            // Render activity
            $this->renderActivity($activity, $row, $feedStart + 2, $feedWidth - 4);

            // Calculate row height based on activity type
            $height = match ($activity['type']) {
                'thinking' => 3,
                'tool' => 3,
                'error' => 3,
                default => 2
            };

            $row += $height;
        }
    }

    private function renderActivity(array $activity, int $row, int $col, int $maxWidth): void
    {
        // Time ago
        $timeAgo = formatTimeAgo($activity['time']);
        $this->setCellString($row, $col, $timeAgo, FG_GRAY);

        // Agent name with color
        $agentColor = $this->getAgentColorStyle($activity['agent']);
        $this->setCellString($row, $col + 10, $activity['agent'], $agentColor . BOLD);

        // Activity type icon and message with animation
        $messageRow = $row + 1;
        $messageCol = $col + 2;

        switch ($activity['type']) {
            case 'thinking':
                $message = '🤔 ' . $activity['message'];
                if (isset($activity['duration'])) {
                    $message .= ' (' . $activity['duration'] . 's)';
                }
                $this->setCellString($messageRow, $messageCol, $message, FG_PURPLE);
                break;
            case 'tool':
                $toolIcon = $this->getToolIcon($activity['tool']);
                $statusStyle = $activity['status'] === 'success' ? FG_GREEN : FG_RED;

                $message = $toolIcon . ' ' . $activity['tool'] . ': ' . $activity['message'] . ' ';
                $this->setCellString($messageRow, $messageCol, $message, FG_CYAN);
                $this->setCell($messageRow, $messageCol + mb_strlen($message), '●', $statusStyle);
                break;
            case 'response':
                $message = '✅ ' . $activity['message'];
                $this->setCellString($messageRow, $messageCol, $message, FG_GREEN);
                break;
            case 'error':
                $this->setCellString($messageRow, $messageCol, '⚠️  ', FG_RED);
                $this->setCellString($messageRow, $messageCol + 3, $activity['message'], FG_YELLOW);
                break;
            default:
                $message = '• ' . $activity['message'];
                $this->setCellString($messageRow, $messageCol, $message, FG_GRAY);
        }
    }

    private function renderStatusBar(): void
    {
        $statusRow = $this->height;

        // Draw status bar separator
        for ($col = 1; $col <= $this->width; $col++) {
            $this->setCell($statusRow - 1, $col, '─', FG_DARK_GRAY);
        }

        // Status indicator
        $this->setCellString($statusRow, 2, '● Active', FG_GREEN);

        // Stats
        $activitiesCount = count($this->activities);
        $tasksCount = count($this->tasks);

        $statsText = " | Activities: {$activitiesCount} | Tasks: {$tasksCount}";
        $this->setCellString($statusRow, 11, $statsText, FG_GRAY);

        // Performance info
        $fps = $this->calculateFPS();
        $perfText = sprintf(' | FPS: %.1f', $fps);
        $this->setCellString($statusRow, 11 + mb_strlen($statsText), $perfText, FG_GRAY);

        // Controls hint
        $controls = '[T] Sidebar | [↑↓] Navigate | [R] Refresh | [Q] Quit';
        $controlsStartCol = $this->width - mb_strlen($controls) + 1;
        $this->setCellString($statusRow, $controlsStartCol, $controls, FG_DARK_GRAY);
    }

    private function update(): void
    {
        $currentTime = time();

        // Add new activities every 8-15 seconds
        if ($currentTime - $this->lastActivityUpdate >= rand(8, 15)) {
            $this->addRandomActivity();
            $this->lastActivityUpdate = $currentTime;
        }

        // Update task progress every 10-20 seconds
        if ($currentTime - $this->lastTaskUpdate >= rand(10, 20)) {
            $this->updateTaskProgress();
            $this->lastTaskUpdate = $currentTime;
        }
    }

    private function addRandomActivity(): void
    {
        $activities = [
            ['type' => 'thinking', 'agent' => 'Analyzer', 'message' => 'Analyzing code patterns...', 'duration' => rand(10, 30) / 10],
            ['type' => 'tool', 'agent' => 'Coder', 'message' => 'Updated configuration file', 'tool' => 'EditFile', 'status' => 'success'],
            ['type' => 'tool', 'agent' => 'Searcher', 'message' => 'Searching for references...', 'tool' => 'Grep', 'status' => 'success'],
            ['type' => 'response', 'agent' => 'Assistant', 'message' => 'Found 3 potential optimizations'],
            ['type' => 'error', 'agent' => 'Validator', 'message' => 'Type mismatch in function signature'],
            ['type' => 'tool', 'agent' => 'Tester', 'message' => 'Running unit tests...', 'tool' => 'Terminal', 'status' => 'success'],
        ];

        $newActivity = $activities[array_rand($activities)];
        $newActivity['time'] = time();

        array_unshift($this->activities, $newActivity);
        $this->activities = array_slice($this->activities, 0, 50); // Keep last 50
    }

    private function updateTaskProgress(): void
    {
        // Only update one random in-progress task at a time for realism
        $inProgressTasks = [];
        foreach ($this->tasks as $index => &$task) {
            if ($task['status'] === 'in_progress' && isset($task['progress'])) {
                $inProgressTasks[] = $index;
            }
        }

        if (! empty($inProgressTasks)) {
            $randomIndex = $inProgressTasks[array_rand($inProgressTasks)];
            $task = &$this->tasks[$randomIndex];

            // Smaller, more realistic progress increments
            $task['progress'] = min(100, $task['progress'] + rand(2, 8));

            if ($task['progress'] >= 100) {
                $task['status'] = 'completed';

                // Mark all subtasks as done
                if (isset($task['subtasks'])) {
                    foreach ($task['subtasks'] as &$subtask) {
                        $subtask['done'] = true;
                    }
                }

                // Maybe start a pending task
                foreach ($this->tasks as &$pendingTask) {
                    if ($pendingTask['status'] === 'pending') {
                        $pendingTask['status'] = 'in_progress';
                        $pendingTask['progress'] = rand(0, 15);
                        break;
                    }
                }
            }
        }
    }

    private function getAgentColorStyle(string $agent): string
    {
        $colors = [
            'Analyzer' => FG_BLUE,
            'Coder' => FG_GREEN,
            'Planner' => FG_PURPLE,
            'Reviewer' => FG_CYAN,
            'Tester' => FG_YELLOW,
            'Debugger' => FG_RED,
            'Assistant' => FG_WHITE,
            'Architect' => FG_PURPLE,
            'Documenter' => FG_CYAN,
            'Optimizer' => FG_ORANGE,
            'Security' => FG_RED,
            'Searcher' => FG_BLUE,
            'Validator' => FG_YELLOW,
        ];

        return $colors[$agent] ?? FG_GRAY;
    }

    private function getToolIcon(string $tool): string
    {
        $icons = [
            'ReadFile' => '📖',
            'WriteFile' => '📝',
            'EditFile' => '✏️',
            'Terminal' => '💻',
            'Grep' => '🔍',
            'Search' => '🔎',
        ];

        return $icons[$tool] ?? '🔧';
    }
}

// Main execution with error handling
$ui = new AgentActivityUI;

// Set up global reference for signal handlers
$GLOBALS['ui'] = $ui;

try {
    $ui->run();
} catch (Exception $e) {
    $ui->cleanup();
    echo "\nError: " . $e->getMessage() . "\n";
    exit(1);
} catch (Error $e) {
    $ui->cleanup();
    echo "\nFatal Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Global signal handler function for compatibility
function globalSignalHandler(int $signal): void
{
    if (isset($GLOBALS['ui'])) {
        $GLOBALS['ui']->cleanup();
    }
    exit(0);
}

// Register global signal handlers if pcntl is available
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, 'globalSignalHandler');
    pcntl_signal(SIGTERM, 'globalSignalHandler');
}
