#!/usr/bin/env php
<?php

/**
 * Minimal AI Agent UI with Right Sidebar
 * Clean, professional terminal UI with task list on the right
 */

// ANSI escape codes
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const UNDERLINE = "\033[4m";
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";

// Terminal control
const ENTER_ALT_SCREEN = "\033[?1049h";
const EXIT_ALT_SCREEN = "\033[?1049l";
const CLEAR_SCREEN = "\033[2J";
const HOME = "\033[H";

// Colors
const FG_WHITE = "\033[38;5;255m";
const FG_GRAY = "\033[38;5;245m";
const FG_DARK_GRAY = "\033[38;5;240m";
const FG_BLUE = "\033[38;5;117m";
const FG_GREEN = "\033[38;5;120m";
const FG_YELLOW = "\033[38;5;221m";
const FG_RED = "\033[38;5;203m";
const FG_CYAN = "\033[38;5;87m";
const FG_MAGENTA = "\033[38;5;141m";

// Box drawing
const BOX_H = '─';
const BOX_V = '│';
const BOX_TL = '┌';
const BOX_TR = '┐';
const BOX_BL = '└';
const BOX_BR = '┘';

function moveTo(int $row, int $col): string
{
    return "\033[{$row};{$col}H";
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

function truncate(string $text, int $maxLength): string
{
    if (mb_strlen($text) <= $maxLength) {
        return $text;
    }

    return mb_substr($text, 0, $maxLength - 3) . '...';
}

class MinimalAgentUI
{
    private int $width = 120;

    private int $height = 40;

    private int $sidebarWidth = 35;

    private int $mainAreaWidth;

    private bool $running = false;

    private bool $initialized = false;

    // UI State
    private array $activities = [];

    private array $tasks = [];

    private int $selectedTaskIndex = 0;

    private int $taskScrollOffset = 0;

    private int $activityScrollOffset = 0;

    // Stats
    private int $completedTasks = 0;

    private int $totalToolCalls = 0;

    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->updateTerminalSize();
        $this->mainAreaWidth = $this->width - $this->sidebarWidth - 1;
        $this->initializeSampleData();
        $this->registerSignalHandlers();
    }

    public function run(): void
    {
        $this->initializeTerminal();
        $this->running = true;

        $lastUpdate = 0;
        $updateInterval = 5; // Update every 5 seconds

        while ($this->running) {
            $currentTime = time();

            // Handle input
            $this->handleInput();

            // Periodic updates (more realistic timing)
            if ($currentTime - $lastUpdate >= $updateInterval) {
                $this->updateData();
                $lastUpdate = $currentTime;
                $updateInterval = rand(5, 15); // Vary between 5-15 seconds
            }

            // Render
            $this->render();

            // Small sleep to prevent high CPU usage
            usleep(50000); // 50ms
        }

        $this->cleanup();
    }

    public function cleanup(): void
    {
        if (! $this->initialized) {
            return;
        }

        // Show cursor
        echo SHOW_CURSOR;

        // Reset attributes
        echo RESET;

        // Exit alternate screen
        echo EXIT_ALT_SCREEN;

        // Restore terminal
        system('stty sane 2>/dev/null');

        $this->initialized = false;
    }

    public function handleSignal(int $signal): void
    {
        $this->cleanup();
        exit(0);
    }

    private function initializeTerminal(): void
    {
        if ($this->initialized) {
            return;
        }

        // Enter alternate screen
        echo ENTER_ALT_SCREEN;

        // Clear screen
        echo CLEAR_SCREEN . HOME;

        // Set up terminal
        system('stty -echo -icanon min 1 time 0 2>/dev/null');
        stream_set_blocking(STDIN, false);

        // Hide cursor
        echo HIDE_CURSOR;

        $this->initialized = true;
    }

    private function registerSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        }
    }

    private function handleInput(): void
    {
        $input = fread(STDIN, 1024);
        if ($input === false || $input === '') {
            return;
        }

        // Handle escape sequences
        if ($input === "\033") {
            $seq = fread(STDIN, 2);
            if ($seq === '[A') { // Up arrow
                if ($this->selectedTaskIndex > 0) {
                    $this->selectedTaskIndex--;
                    $this->adjustTaskScroll();
                }
            } elseif ($seq === '[B') { // Down arrow
                if ($this->selectedTaskIndex < count($this->tasks) - 1) {
                    $this->selectedTaskIndex++;
                    $this->adjustTaskScroll();
                }
            }

            return;
        }

        switch ($input) {
            case 'q':
            case 'Q':
            case "\x03": // Ctrl+C
                $this->running = false;
                break;
            case 'r':
            case 'R':
                $this->updateData();
                break;
            case 'c':
            case 'C':
                $this->activities = [];
                break;
            case "\n": // Enter - mark selected task as completed
                if (isset($this->tasks[$this->selectedTaskIndex])) {
                    if ($this->tasks[$this->selectedTaskIndex]['status'] === 'running') {
                        $this->tasks[$this->selectedTaskIndex]['status'] = 'completed';
                        $this->completedTasks++;
                    }
                }
                break;
        }
    }

    private function render(): void
    {
        // Clear screen
        echo HOME;

        // Draw layout
        $this->renderHeader();
        $this->renderMainArea();
        $this->renderSidebar();
        $this->renderFooter();
    }

    private function renderHeader(): void
    {
        // Status bar
        echo moveTo(1, 1);
        echo FG_BLUE . '■' . RESET . ' AI Agent Monitor';

        // Stats
        $uptime = $this->formatUptime();
        $stats = sprintf('Uptime: %s | Tasks: %d/%d | Tools: %d',
            $uptime,
            $this->completedTasks,
            count($this->tasks),
            $this->totalToolCalls
        );

        $statsCol = $this->width - mb_strlen($stats) - 1;
        echo moveTo(1, $statsCol) . FG_GRAY . $stats . RESET;

        // Separator
        echo moveTo(2, 1) . FG_DARK_GRAY . str_repeat(BOX_H, $this->width) . RESET;
    }

    private function renderMainArea(): void
    {
        $startRow = 3;
        $endRow = $this->height - 2;
        $currentRow = $startRow;

        // Activity feed title
        echo moveTo($currentRow++, 2) . FG_WHITE . BOLD . 'Activity Feed' . RESET;
        $currentRow++;

        // Activities
        $maxActivities = $endRow - $currentRow - 1;
        $visibleActivities = array_slice($this->activities, -$maxActivities);

        foreach ($visibleActivities as $activity) {
            if ($currentRow >= $endRow) {
                break;
            }

            echo moveTo($currentRow, 2);
            $this->renderActivity($activity);
            $currentRow++;
        }

        // Draw vertical separator
        for ($row = $startRow; $row < $endRow; $row++) {
            echo moveTo($row, $this->mainAreaWidth + 1);
            echo FG_DARK_GRAY . BOX_V . RESET;
        }
    }

    private function renderActivity(array $activity): void
    {
        $time = FG_DARK_GRAY . '[' . date('H:i:s', $activity['time']) . ']' . RESET;

        $icon = match ($activity['type']) {
            'thinking' => FG_MAGENTA . '○',
            'tool' => FG_CYAN . '◆',
            'complete' => FG_GREEN . '✓',
            'error' => FG_RED . '✗',
            default => FG_GRAY . '•'
        };

        $agent = match ($activity['agent']) {
            'Analyzer' => FG_BLUE,
            'Coder' => FG_GREEN,
            'Tester' => FG_YELLOW,
            'Reviewer' => FG_CYAN,
            default => FG_WHITE
        } . $activity['agent'] . RESET;

        $message = truncate($activity['message'], $this->mainAreaWidth - 25);

        echo "{$time} {$icon} {$agent}: {$message}" . RESET;
    }

    private function renderSidebar(): void
    {
        $col = $this->mainAreaWidth + 3;
        $startRow = 3;
        $endRow = $this->height - 2;

        // Task Queue header
        echo moveTo($startRow, $col) . FG_WHITE . BOLD . 'Task Queue' . RESET;

        // Task stats
        $running = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'running'));
        $pending = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'pending'));
        $completed = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));

        echo moveTo($startRow + 1, $col);
        echo FG_GREEN . "✓ {$completed}" . RESET . ' ';
        echo FG_YELLOW . "▶ {$running}" . RESET . ' ';
        echo FG_GRAY . "○ {$pending}" . RESET;

        // Task list
        $taskStartRow = $startRow + 3;
        $maxTasks = $endRow - $taskStartRow - 1;

        // Calculate visible tasks based on scroll
        $visibleTasks = array_slice($this->tasks, $this->taskScrollOffset, $maxTasks);

        $row = $taskStartRow;
        foreach ($visibleTasks as $index => $task) {
            if ($row >= $endRow) {
                break;
            }

            $actualIndex = $index + $this->taskScrollOffset;
            $isSelected = $actualIndex === $this->selectedTaskIndex;

            echo moveTo($row, $col);

            if ($isSelected) {
                echo "\033[7m"; // Reverse video
            }

            $this->renderTaskLine($task, $actualIndex + 1);

            if ($isSelected) {
                echo RESET;
            }

            $row++;
        }

        // Scroll indicators
        if ($this->taskScrollOffset > 0) {
            echo moveTo($taskStartRow - 1, $col + $this->sidebarWidth - 5);
            echo FG_DARK_GRAY . '▲' . RESET;
        }

        if ($this->taskScrollOffset + $maxTasks < count($this->tasks)) {
            echo moveTo($endRow - 1, $col + $this->sidebarWidth - 5);
            echo FG_DARK_GRAY . '▼' . RESET;
        }
    }

    private function renderTaskLine(array $task, int $number): void
    {
        $icon = match ($task['status']) {
            'completed' => FG_GREEN . '✓',
            'running' => FG_YELLOW . '▶',
            'pending' => FG_GRAY . '○',
            'failed' => FG_RED . '✗',
            default => ' '
        };

        $num = mb_str_pad($number . '.', 3);
        $desc = truncate($task['description'], $this->sidebarWidth - 8);

        echo "{$num} {$icon} " . RESET . $desc;

        if ($task['status'] === 'running' && isset($task['progress'])) {
            $percent = $task['progress'];
            echo ' ' . FG_DARK_GRAY . "{$percent}%" . RESET;
        }
    }

    private function renderFooter(): void
    {
        // Separator
        echo moveTo($this->height - 1, 1);
        echo FG_DARK_GRAY . str_repeat(BOX_H, $this->width) . RESET;

        // Controls
        echo moveTo($this->height, 2);
        echo FG_DARK_GRAY . '[Q] Quit  [↑↓] Navigate  [Enter] Complete  [R] Refresh  [C] Clear' . RESET;
    }

    private function updateTerminalSize(): void
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;
        $this->mainAreaWidth = $this->width - $this->sidebarWidth - 1;
    }

    private function adjustTaskScroll(): void
    {
        $maxVisible = $this->height - 8;

        if ($this->selectedTaskIndex < $this->taskScrollOffset) {
            $this->taskScrollOffset = $this->selectedTaskIndex;
        } elseif ($this->selectedTaskIndex >= $this->taskScrollOffset + $maxVisible) {
            $this->taskScrollOffset = $this->selectedTaskIndex - $maxVisible + 1;
        }
    }

    private function formatUptime(): string
    {
        $uptime = microtime(true) - $this->startTime;

        if ($uptime < 60) {
            return round($uptime) . 's';
        } elseif ($uptime < 3600) {
            return floor($uptime / 60) . 'm ' . round($uptime % 60) . 's';
        }
        $hours = floor($uptime / 3600);
        $minutes = floor(($uptime % 3600) / 60);

        return $hours . 'h ' . $minutes . 'm';
    }

    private function initializeSampleData(): void
    {
        // Sample tasks
        $this->tasks = [
            ['description' => 'Analyze codebase structure', 'status' => 'completed', 'agent' => 'Analyzer'],
            ['description' => 'Identify optimization opportunities', 'status' => 'completed', 'agent' => 'Analyzer'],
            ['description' => 'Refactor database layer', 'status' => 'running', 'agent' => 'Coder', 'progress' => 65],
            ['description' => 'Update unit tests', 'status' => 'running', 'agent' => 'Tester', 'progress' => 30],
            ['description' => 'Generate API documentation', 'status' => 'pending', 'agent' => 'Documenter'],
            ['description' => 'Review security vulnerabilities', 'status' => 'pending', 'agent' => 'Security'],
            ['description' => 'Optimize query performance', 'status' => 'pending', 'agent' => 'Optimizer'],
            ['description' => 'Implement caching layer', 'status' => 'pending', 'agent' => 'Coder'],
            ['description' => 'Setup monitoring', 'status' => 'pending', 'agent' => 'DevOps'],
            ['description' => 'Create deployment pipeline', 'status' => 'pending', 'agent' => 'DevOps'],
        ];

        $this->completedTasks = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));

        // Sample activities
        $this->activities = [
            ['time' => time() - 120, 'type' => 'thinking', 'agent' => 'Analyzer', 'message' => 'Analyzing codebase patterns...'],
            ['time' => time() - 100, 'type' => 'tool', 'agent' => 'Analyzer', 'message' => 'ReadFile: src/Application.php'],
            ['time' => time() - 80, 'type' => 'complete', 'agent' => 'Analyzer', 'message' => 'Found 3 optimization opportunities'],
            ['time' => time() - 60, 'type' => 'tool', 'agent' => 'Coder', 'message' => 'EditFile: src/Database.php'],
            ['time' => time() - 40, 'type' => 'thinking', 'agent' => 'Tester', 'message' => 'Running test suite...'],
            ['time' => time() - 20, 'type' => 'error', 'agent' => 'Tester', 'message' => '2 tests failed'],
            ['time' => time() - 10, 'type' => 'tool', 'agent' => 'Coder', 'message' => 'Fixing test failures...'],
        ];

        $this->totalToolCalls = count(array_filter($this->activities, fn ($a) => $a['type'] === 'tool'));
    }

    private function updateData(): void
    {
        // Simulate realistic task progression
        foreach ($this->tasks as &$task) {
            if ($task['status'] === 'running' && isset($task['progress'])) {
                $task['progress'] = min(100, $task['progress'] + rand(3, 12));

                if ($task['progress'] >= 100) {
                    $task['status'] = 'completed';
                    $this->completedTasks++;

                    // Add completion activity
                    $this->addActivity('complete', $task['agent'], "Completed: {$task['description']}");

                    // Start a pending task
                    foreach ($this->tasks as &$pendingTask) {
                        if ($pendingTask['status'] === 'pending') {
                            $pendingTask['status'] = 'running';
                            $pendingTask['progress'] = rand(5, 20);
                            $this->addActivity('thinking', $pendingTask['agent'], "Starting: {$pendingTask['description']}");
                            break;
                        }
                    }
                }
            }
        }

        // Occasionally add new activities
        if (rand(1, 3) === 1) {
            $types = ['thinking', 'tool', 'complete'];
            $agents = ['Analyzer', 'Coder', 'Tester', 'Reviewer'];
            $messages = [
                'thinking' => ['Analyzing dependencies...', 'Evaluating code quality...', 'Planning refactor...'],
                'tool' => ['ReadFile: config.json', 'EditFile: index.php', 'Terminal: composer update'],
                'complete' => ['Task completed successfully', 'All tests passing', 'Optimization complete'],
            ];

            $type = $types[array_rand($types)];
            $agent = $agents[array_rand($agents)];
            $message = $messages[$type][array_rand($messages[$type])];

            $this->addActivity($type, $agent, $message);
        }
    }

    private function addActivity(string $type, string $agent, string $message): void
    {
        $this->activities[] = [
            'time' => time(),
            'type' => $type,
            'agent' => $agent,
            'message' => $message,
        ];

        if ($type === 'tool') {
            $this->totalToolCalls++;
        }

        // Keep only recent activities
        if (count($this->activities) > 50) {
            array_shift($this->activities);
        }
    }
}

// Main execution
$ui = new MinimalAgentUI;

// Set up global reference for signal handlers
$GLOBALS['ui'] = $ui;

// Register signal handlers
register_shutdown_function(function () {
    if (isset($GLOBALS['ui'])) {
        $GLOBALS['ui']->cleanup();
    }
});

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
