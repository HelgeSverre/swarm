#!/usr/bin/env php
<?php

/**
 * AI Agent Activity UI - Optimized Version
 * Smooth terminal UI with proper alternate screen buffer, double buffering, and optimized rendering
 *
 * Features:
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

// Color scheme
const BG_MAIN = "\033[48;5;236m";
const BG_SIDEBAR = "\033[48;5;235m";
const BG_HEADER = "\033[48;5;234m";
const BG_SELECTED = "\033[48;5;238m";
const BG_SUCCESS = "\033[48;5;22m";
const BG_WARNING = "\033[48;5;58m";
const BG_ERROR = "\033[48;5;52m";

const FG_WHITE = "\033[38;5;255m";
const FG_GRAY = "\033[38;5;245m";
const FG_DARK_GRAY = "\033[38;5;240m";
const FG_BLUE = "\033[38;5;117m";
const FG_GREEN = "\033[38;5;120m";
const FG_YELLOW = "\033[38;5;221m";
const FG_RED = "\033[38;5;203m";
const FG_PURPLE = "\033[38;5;141m";
const FG_CYAN = "\033[38;5;87m";
const FG_ORANGE = "\033[38;5;214m";

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

function interpolateColor(float $factor, array $color1, array $color2): array
{
    $r = (int) ($color1[0] + ($color2[0] - $color1[0]) * $factor);
    $g = (int) ($color1[1] + ($color2[1] - $color1[1]) * $factor);
    $b = (int) ($color1[2] + ($color2[2] - $color1[2]) * $factor);

    return [$r, $g, $b];
}

function rgbToAnsi(array $rgb): string
{
    [$r, $g, $b] = $rgb;

    return "\033[38;2;{$r};{$g};{$b}m";
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

    // Animation state
    private array $animations = [];

    private float $animationSpeed = 1.0;

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->initializeBuffers();
        $this->initializeActivities();
        $this->initializeTasks();
        $this->initializeAnimations();

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

    private function initializeAnimations(): void
    {
        $this->animations = [
            'pulse' => ['phase' => 0, 'speed' => 0.1],
            'scroll' => ['offset' => 0, 'speed' => 0.5],
            'fade' => ['alpha' => 1.0, 'direction' => -1, 'speed' => 0.02],
        ];
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
            case '+':
                $this->animationSpeed = min(3.0, $this->animationSpeed + 0.1);
                break;
            case '-':
                $this->animationSpeed = max(0.1, $this->animationSpeed - 0.1);
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
        $headerStyle = BG_HEADER . FG_WHITE;

        // Clear header row
        for ($col = 1; $col <= $this->width; $col++) {
            $this->setCell(1, $col, ' ', $headerStyle);
        }

        // Title with animation
        $pulse = sin($this->animations['pulse']['phase']) * 0.3 + 0.7;
        $titleColor = $this->getAnimatedColor([70, 130, 180], [100, 160, 220], $pulse);
        $titleStyle = BG_HEADER . rgbToAnsi($titleColor) . BOLD;

        $title = ' 🤖 AI AGENT ACTIVITY MONITOR';
        $this->setCellString(1, 2, $title, $titleStyle);

        // Right side info with live updates
        $time = date('H:i:s');
        $fps = $this->calculateFPS();
        $rightText = sprintf('FPS: %.1f | Agents: 3 | %s ', $fps, $time);
        $startCol = $this->width - mb_strlen($rightText) + 1;
        $this->setCellString(1, $startCol, $rightText, BG_HEADER . FG_GRAY);
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

    private function getAnimatedColor(array $color1, array $color2, float $factor): array
    {
        return interpolateColor($factor, $color1, $color2);
    }

    private function renderSidebar(): void
    {
        $effectiveWidth = $this->sidebarExpanded ? $this->sidebarWidth : 3;
        $startRow = 2;
        $sidebarHeight = $this->height - 3;
        $sidebarStyle = BG_SIDEBAR . FG_WHITE;

        // Sidebar background
        for ($row = 0; $row < $sidebarHeight; $row++) {
            for ($col = 1; $col <= $effectiveWidth; $col++) {
                $this->setCell($startRow + $row, $col, ' ', $sidebarStyle);
            }
        }

        if ($this->sidebarExpanded) {
            // Animated header
            $pulse = $this->animations['pulse']['phase'];
            $headerColor = $this->getAnimatedColor([255, 255, 255], [100, 200, 255], sin($pulse) * 0.2 + 0.8);
            $headerStyle = BG_SIDEBAR . rgbToAnsi($headerColor) . BOLD;

            $this->setCellString($startRow, 2, '📋 TASK QUEUE', $headerStyle);

            // Collapse button with animation
            $buttonAlpha = $this->animations['fade']['alpha'];
            $buttonColor = [128 + (int) (127 * $buttonAlpha), 128 + (int) (127 * $buttonAlpha), 128 + (int) (127 * $buttonAlpha)];
            $buttonStyle = BG_SIDEBAR . rgbToAnsi($buttonColor);
            $this->setCell($startRow, $this->sidebarWidth - 1, '◀', $buttonStyle);

            // Animated separator
            $separatorRow = $startRow + 1;
            for ($col = 2; $col < $this->sidebarWidth - 1; $col++) {
                $phase = ($col - 2) * 0.5 + $this->animations['scroll']['offset'];
                $intensity = sin($phase) * 0.3 + 0.7;
                $gray = (int) (100 + 50 * $intensity);
                $sepStyle = BG_SIDEBAR . "\033[38;2;{$gray};{$gray};{$gray}m";
                $this->setCell($separatorRow, $col, '─', $sepStyle);
            }

            // Tasks
            $this->renderTasks($startRow + 3);
        } else {
            // Collapsed state with animation
            $expandAlpha = sin($this->animations['pulse']['phase'] * 2) * 0.3 + 0.7;
            $expandColor = $this->getAnimatedColor([128, 128, 128], [200, 200, 200], $expandAlpha);
            $expandStyle = BG_SIDEBAR . rgbToAnsi($expandColor);

            $this->setCellString($startRow, 1, ' ▶ ', $expandStyle);

            // Vertical text with fade
            $text = 'TASKS';
            for ($i = 0; $i < mb_strlen($text); $i++) {
                $charAlpha = $this->animations['fade']['alpha'] * (0.5 + 0.5 * sin($i * 0.5 + $this->animations['pulse']['phase']));
                $charGray = (int) (80 + 100 * $charAlpha);
                $charStyle = BG_SIDEBAR . "\033[38;2;{$charGray};{$charGray};{$charGray}m";
                $this->setCell($startRow + 3 + $i, 2, $text[$i], $charStyle);
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
            $bgStyle = $isSelected ? BG_SELECTED : BG_SIDEBAR;

            // Animated selection indicator
            if ($isSelected) {
                $pulse = sin($this->animations['pulse']['phase'] * 2) * 0.3 + 0.7;
                $selectionColor = $this->getAnimatedColor([100, 100, 150], [150, 150, 200], $pulse);
                $bgStyle = "\033[48;2;{$selectionColor[0]};{$selectionColor[1]};{$selectionColor[2]}m";
            }

            // Status icon with colors
            $statusData = match ($task['status']) {
                'completed' => ['icon' => '✓', 'color' => [0, 255, 0]],
                'in_progress' => ['icon' => '◐', 'color' => [255, 255, 0]],
                'pending' => ['icon' => '○', 'color' => [128, 128, 128]],
                default => ['icon' => '·', 'color' => [100, 100, 100]]
            };

            $iconStyle = $bgStyle . rgbToAnsi($statusData['color']);
            $this->setCell($row, 2, $statusData['icon'], $iconStyle);
            $this->setCell($row, 3, ' ', $bgStyle);

            // Task title with truncation
            $maxTitleLen = $this->sidebarWidth - 7;
            $title = mb_substr($task['title'], 0, $maxTitleLen);
            $titleStyle = $bgStyle . FG_WHITE;
            $this->setCellString($row, 4, $title, $titleStyle);

            // Fill rest of row
            for ($col = 4 + mb_strlen($title); $col <= $this->sidebarWidth; $col++) {
                $this->setCell($row, $col, ' ', $bgStyle);
            }

            $row++;

            // Agent assignment with animation
            if (isset($task['agent'])) {
                $agentAlpha = $this->animations['fade']['alpha'] * 0.8 + 0.2;
                $agentColor = $this->getAnimatedColor([100, 200, 255], [150, 220, 255], $agentAlpha);
                $agentStyle = BG_SIDEBAR . rgbToAnsi($agentColor) . DIM;

                $agentText = '→ ' . $task['agent'];
                $this->setCellString($row, 4, $agentText, $agentStyle);

                // Fill rest of row
                for ($col = 4 + mb_strlen($agentText); $col <= $this->sidebarWidth; $col++) {
                    $this->setCell($row, $col, ' ', BG_SIDEBAR);
                }
                $row++;
            }

            // Animated progress bar for in-progress tasks
            if ($task['status'] === 'in_progress' && isset($task['progress'])) {
                $this->renderAnimatedProgressBar($row, 4, $task['progress'], 18);
                $row++;
            }

            // Subtasks with fade animation
            if ($isSelected && isset($task['subtasks'])) {
                foreach ($task['subtasks'] as $subIndex => $subtask) {
                    if ($row >= $this->height - 2) {
                        break;
                    }

                    $fadeOffset = $subIndex * 0.2 + $this->animations['pulse']['phase'];
                    $subtaskAlpha = sin($fadeOffset) * 0.2 + 0.8;
                    $subtaskGray = (int) (120 + 80 * $subtaskAlpha);
                    $subtaskStyle = BG_SIDEBAR . "\033[38;2;{$subtaskGray};{$subtaskGray};{$subtaskGray}m";

                    $checkbox = $subtask['done'] ? '☑' : '☐';
                    $subtaskText = $checkbox . ' ' . mb_substr($subtask['title'], 0, $maxTitleLen - 6);
                    $this->setCellString($row, 4, $subtaskText, $subtaskStyle);

                    // Fill rest of row
                    for ($col = 4 + mb_strlen($subtaskText); $col <= $this->sidebarWidth; $col++) {
                        $this->setCell($row, $col, ' ', BG_SIDEBAR);
                    }
                    $row++;
                }
            }

            $row++; // Space between tasks

            if ($row >= $this->height - 2) {
                break;
            }
        }

        // Animated task count at bottom
        $completed = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));
        $countAlpha = $this->animations['fade']['alpha'];
        $countGray = (int) (100 + 80 * $countAlpha);
        $countStyle = BG_SIDEBAR . "\033[38;2;{$countGray};{$countGray};{$countGray}m" . DIM;

        $countText = sprintf('%d/%d tasks', $completed, count($this->tasks));
        $this->setCellString($this->height - 2, 2, $countText, $countStyle);
    }

    private function renderAnimatedProgressBar(int $row, int $col, float $progress, int $width): void
    {
        $filled = (int) ($progress / 100 * $width);
        $phase = $this->animations['pulse']['phase'];

        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                // Animated filled portion
                $intensity = sin($phase + $i * 0.3) * 0.2 + 0.8;
                $green = (int) (100 + 155 * $intensity);
                $barStyle = BG_SIDEBAR . "\033[38;2;0;{$green};0m";
                $this->setCell($row, $col + $i, '█', $barStyle);
            } else {
                // Empty portion
                $this->setCell($row, $col + $i, '░', BG_SIDEBAR . FG_DARK_GRAY);
            }
        }

        // Percentage text
        $percentText = sprintf(' %d%%', $progress);
        $this->setCellString($row, $col + $width, $percentText, BG_SIDEBAR . FG_GRAY);
    }

    private function renderActivityFeed(): void
    {
        $feedStart = $this->sidebarExpanded ? $this->sidebarWidth + 2 : 5;
        $feedWidth = $this->width - $feedStart - 1;
        $feedHeight = $this->height - 3;
        $mainStyle = BG_MAIN . FG_WHITE;

        // Feed background with subtle animation
        for ($row = 2; $row <= $this->height - 2; $row++) {
            for ($col = $feedStart; $col <= $this->width; $col++) {
                // Subtle background animation
                $bgPhase = ($row + $col) * 0.1 + $this->animations['scroll']['offset'] * 0.1;
                $bgIntensity = sin($bgPhase) * 0.02 + 0.98;
                $bgGray = (int) (45 * $bgIntensity);
                $bgStyle = "\033[48;2;{$bgGray};{$bgGray};{$bgGray}m";
                $this->setCell($row, $col, ' ', $bgStyle);
            }
        }

        // Animated feed header
        $headerPhase = $this->animations['pulse']['phase'];
        $headerColor = $this->getAnimatedColor([255, 255, 255], [150, 200, 255], sin($headerPhase) * 0.3 + 0.7);
        $headerStyle = BG_MAIN . rgbToAnsi($headerColor) . BOLD;

        $this->setCellString(2, $feedStart + 2, 'AGENT ACTIVITY TIMELINE', $headerStyle);

        // Scrolling activities with stagger animation
        $row = 4;
        $visibleActivities = array_slice($this->activities, $this->activityScrollOffset, 15);

        foreach ($visibleActivities as $index => $activity) {
            if ($row >= $this->height - 2) {
                break;
            }

            // Staggered fade-in animation
            $activityAlpha = $this->animations['fade']['alpha'] * (0.6 + 0.4 * sin($index * 0.5 + $this->animations['pulse']['phase']));

            $this->renderActivity($activity, $row, $feedStart + 2, $feedWidth - 4, $activityAlpha);

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

    private function renderActivity(array $activity, int $row, int $col, int $maxWidth, float $alpha = 1.0): void
    {
        // Time ago with alpha blending
        $timeAgo = formatTimeAgo($activity['time']);
        $timeGray = (int) (100 * $alpha + 50);
        $timeStyle = BG_MAIN . "\033[38;2;{$timeGray};{$timeGray};{$timeGray}m";
        $this->setCellString($row, $col, $timeAgo, $timeStyle);

        // Agent name with animated color and alpha
        $agentColor = $this->getAgentColor($activity['agent']);
        $agentAlpha = $alpha * (0.8 + 0.2 * sin($this->animations['pulse']['phase']));

        // Apply alpha to agent color
        $agentColorWithAlpha = [
            (int) ($agentColor[0] * $agentAlpha),
            (int) ($agentColor[1] * $agentAlpha),
            (int) ($agentColor[2] * $agentAlpha),
        ];
        $agentStyle = BG_MAIN . rgbToAnsi($agentColorWithAlpha) . BOLD;
        $this->setCellString($row, $col + 10, $activity['agent'], $agentStyle);

        // Activity type icon and message with animation
        $messageRow = $row + 1;
        $messageCol = $col + 2;

        switch ($activity['type']) {
            case 'thinking':
                // Pulsing thinking indicator
                $thinkPulse = sin($this->animations['pulse']['phase'] * 3) * 0.3 + 0.7;
                $purpleIntensity = (int) (150 * $thinkPulse * $alpha);
                $thinkStyle = BG_MAIN . "\033[38;2;{$purpleIntensity};100;{$purpleIntensity}m";

                $message = '🤔 ' . $activity['message'];
                if (isset($activity['duration'])) {
                    $message .= ' (' . $activity['duration'] . 's)';
                }
                $this->setCellString($messageRow, $messageCol, $message, $thinkStyle);
                break;
            case 'tool':
                $toolIcon = $this->getToolIcon($activity['tool']);
                $statusColor = $activity['status'] === 'success' ? [0, 255, 0] : [255, 0, 0];

                // Apply alpha to colors
                $cyanAlpha = (int) (135 * $alpha);
                $statusAlpha = [
                    (int) ($statusColor[0] * $alpha),
                    (int) ($statusColor[1] * $alpha),
                    (int) ($statusColor[2] * $alpha),
                ];

                $toolStyle = BG_MAIN . "\033[38;2;100;{$cyanAlpha};{$cyanAlpha}m";
                $statusStyle = BG_MAIN . rgbToAnsi($statusAlpha);

                $message = $toolIcon . ' ' . $activity['tool'] . ': ' . $activity['message'] . ' ';
                $this->setCellString($messageRow, $messageCol, $message, $toolStyle);
                $this->setCell($messageRow, $messageCol + mb_strlen($message), '●', $statusStyle);
                break;
            case 'response':
                $greenAlpha = (int) (200 * $alpha);
                $responseStyle = BG_MAIN . "\033[38;2;0;{$greenAlpha};0m";
                $message = '✅ ' . $activity['message'];
                $this->setCellString($messageRow, $messageCol, $message, $responseStyle);
                break;
            case 'error':
                // Blinking error indicator
                $errorBlink = sin($this->animations['pulse']['phase'] * 4) > 0 ? 1.0 : 0.5;
                $redAlpha = (int) (255 * $errorBlink * $alpha);
                $yellowAlpha = (int) (255 * $alpha);

                $errorStyle = BG_MAIN . "\033[38;2;{$redAlpha};0;0m";
                $this->setCellString($messageRow, $messageCol, '⚠️  ', $errorStyle);

                $messageStyle = BG_MAIN . "\033[38;2;{$yellowAlpha};{$yellowAlpha};0m";
                $this->setCellString($messageRow, $messageCol + 3, $activity['message'], $messageStyle);
                break;
            default:
                $grayAlpha = (int) (150 * $alpha);
                $defaultStyle = BG_MAIN . "\033[38;2;{$grayAlpha};{$grayAlpha};{$grayAlpha}m";
                $message = '• ' . $activity['message'];
                $this->setCellString($messageRow, $messageCol, $message, $defaultStyle);
        }
    }

    private function renderStatusBar(): void
    {
        $statusRow = $this->height;
        $statusStyle = BG_HEADER . FG_WHITE;

        // Clear status bar
        for ($col = 1; $col <= $this->width; $col++) {
            $this->setCell($statusRow, $col, ' ', $statusStyle);
        }

        // Animated status indicator
        $statusPulse = sin($this->animations['pulse']['phase'] * 2) * 0.3 + 0.7;
        $greenIntensity = (int) (200 * $statusPulse);
        $statusIndicatorStyle = BG_HEADER . "\033[38;2;0;{$greenIntensity};0m";
        $this->setCellString($statusRow, 2, '● Active', $statusIndicatorStyle);

        // Stats with animations
        $activitiesCount = count($this->activities);
        $tasksCount = count($this->tasks);

        $statsAlpha = $this->animations['fade']['alpha'];
        $statsGray = (int) (150 + 50 * $statsAlpha);
        $statsStyle = BG_HEADER . "\033[38;2;{$statsGray};{$statsGray};{$statsGray}m";

        $statsText = " | Activities: {$activitiesCount} | Tasks: {$tasksCount}";
        $this->setCellString($statusRow, 11, $statsText, $statsStyle);

        // Performance info
        $fps = $this->calculateFPS();
        $animSpeed = $this->animationSpeed;
        $perfText = sprintf(' | FPS: %.1f | Speed: %.1fx', $fps, $animSpeed);
        $this->setCellString($statusRow, 11 + mb_strlen($statsText), $perfText, $statsStyle);

        // Controls hint with subtle animation
        $controls = '[T] Sidebar | [↑↓] Navigate | [+/-] Speed | [R] Refresh | [Q] Quit';
        $controlsStartCol = $this->width - mb_strlen($controls) + 1;

        $controlsAlpha = 0.6 + 0.2 * sin($this->animations['pulse']['phase'] * 0.5);
        $controlsGray = (int) (100 + 50 * $controlsAlpha);
        $controlsStyle = BG_HEADER . "\033[38;2;{$controlsGray};{$controlsGray};{$controlsGray}m";

        $this->setCellString($statusRow, $controlsStartCol, $controls, $controlsStyle);
    }

    private function update(): void
    {
        // Update animations
        $this->updateAnimations();

        // Add new activities periodically (less frequently)
        if ($this->frame % 60 === 0) {
            $this->addRandomActivity();
        }

        // Update task progress
        if ($this->frame % 40 === 0) {
            $this->updateTaskProgress();
        }

        // Auto-cycle selected task (slower)
        if ($this->frame % 120 === 0) {
            $this->selectedTask = ($this->selectedTask + 1) % count($this->tasks);
        }
    }

    private function updateAnimations(): void
    {
        // Update pulse animation
        $this->animations['pulse']['phase'] += $this->animations['pulse']['speed'] * $this->animationSpeed;
        if ($this->animations['pulse']['phase'] > 2 * M_PI) {
            $this->animations['pulse']['phase'] -= 2 * M_PI;
        }

        // Update scroll animation
        $this->animations['scroll']['offset'] += $this->animations['scroll']['speed'] * $this->animationSpeed;
        if ($this->animations['scroll']['offset'] > 10) {
            $this->animations['scroll']['offset'] = 0;
        }

        // Update fade animation
        $this->animations['fade']['alpha'] += $this->animations['fade']['direction'] * $this->animations['fade']['speed'] * $this->animationSpeed;
        if ($this->animations['fade']['alpha'] <= 0.3) {
            $this->animations['fade']['alpha'] = 0.3;
            $this->animations['fade']['direction'] = 1;
        } elseif ($this->animations['fade']['alpha'] >= 1.0) {
            $this->animations['fade']['alpha'] = 1.0;
            $this->animations['fade']['direction'] = -1;
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
        foreach ($this->tasks as &$task) {
            if ($task['status'] === 'in_progress' && isset($task['progress'])) {
                $task['progress'] = min(100, $task['progress'] + rand(5, 15));

                if ($task['progress'] >= 100) {
                    $task['status'] = 'completed';

                    // Mark all subtasks as done
                    if (isset($task['subtasks'])) {
                        foreach ($task['subtasks'] as &$subtask) {
                            $subtask['done'] = true;
                        }
                    }
                }
            }
        }
    }

    private function getAgentColor(string $agent): array
    {
        $colors = [
            'Analyzer' => [117, 169, 255],
            'Coder' => [120, 255, 120],
            'Planner' => [200, 120, 255],
            'Reviewer' => [120, 255, 255],
            'Tester' => [255, 255, 120],
            'Debugger' => [255, 120, 120],
            'Assistant' => [255, 255, 255],
            'Architect' => [200, 120, 255],
            'Documenter' => [120, 255, 255],
            'Optimizer' => [255, 180, 120],
            'Security' => [255, 120, 120],
            'Searcher' => [117, 169, 255],
            'Validator' => [255, 255, 120],
        ];

        return $colors[$agent] ?? [128, 128, 128];
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
