#!/usr/bin/env php
<?php

/**
 * Minimal Sidebar UI - Clean and Professional Terminal Interface
 *
 * Features:
 * - Static colors (no animations)
 * - Clean sidebar with task queue
 * - Activity feed with proper formatting
 * - Keyboard navigation
 * - Proper terminal management
 */

// ANSI color constants - static palette
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const UNDERLINE = "\033[4m";
const REVERSE = "\033[7m";

// Cursor control
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";
const CLEAR_SCREEN = "\033[2J";
const HOME = "\033[H";

// Screen buffer control
const ENTER_ALT_SCREEN = "\033[?1049h";
const EXIT_ALT_SCREEN = "\033[?1049l";

// Colors - muted professional palette
const FG_WHITE = "\033[37m";
const FG_BLACK = "\033[30m";
const FG_GRAY = "\033[90m";
const FG_BLUE = "\033[34m";
const FG_GREEN = "\033[32m";
const FG_YELLOW = "\033[33m";
const FG_RED = "\033[31m";
const FG_CYAN = "\033[36m";
const FG_MAGENTA = "\033[35m";

const BG_DEFAULT = "\033[49m";
const BG_DARK = "\033[40m";
const BG_BLUE = "\033[44m";
const BG_GRAY = "\033[100m";

// Box drawing characters
const BOX_H = '─';
const BOX_V = '│';
const BOX_TL = '┌';
const BOX_TR = '┐';
const BOX_BL = '└';
const BOX_BR = '┘';
const BOX_T = '┬';
const BOX_B = '┴';
const BOX_L = '├';
const BOX_R = '┤';
const BOX_CROSS = '┼';

function moveTo(int $row, int $col): string
{
    return "\033[{$row};{$col}H";
}

class MinimalSidebarUI
{
    private int $width = 120;

    private int $height = 40;

    private int $sidebarWidth = 35;

    private bool $sidebarVisible = true;

    private int $frame = 0;

    // UI State
    private array $activities = [];

    private array $tasks = [];

    private int $selectedTask = 0;

    private int $activityScroll = 0;

    private int $taskScroll = 0;

    // System state
    private array $metrics = [
        'total_operations' => 0,
        'success_rate' => 100,
        'active_agents' => 3,
        'avg_response_time' => 0.234,
    ];

    private bool $running = false;

    private string $inputBuffer = '';

    private string $currentMode = 'main'; // main, tasks, help

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->initializeDemoData();
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        pcntl_signal(SIGWINCH, [$this, 'handleResize']);
    }

    public function run(): void
    {
        $this->initializeTerminal();
        $this->running = true;

        while ($this->running) {
            $this->handleInput();
            $this->update();
            $this->render();

            pcntl_signal_dispatch();
            usleep(50000); // 50ms = 20 FPS
            $this->frame++;
        }

        $this->cleanup();
    }

    private function initializeTerminal(): void
    {
        echo ENTER_ALT_SCREEN;
        echo HIDE_CURSOR;
        system('stty -echo -icanon min 1 time 0 2>/dev/null');
        stream_set_blocking(STDIN, false);
    }

    private function cleanup(): void
    {
        echo SHOW_CURSOR;
        echo EXIT_ALT_SCREEN;
        system('stty sane');
    }

    private function updateTerminalSize(): void
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;
    }

    private function handleSignal(int $signal): void
    {
        if ($signal === SIGINT) {
            $this->running = false;
        }
    }

    private function handleResize(int $signal): void
    {
        $this->updateTerminalSize();
    }

    private function handleInput(): void
    {
        $input = fread(STDIN, 1024);
        if ($input === false || $input === '') {
            return;
        }

        $this->inputBuffer .= $input;

        while (mb_strlen($this->inputBuffer) > 0) {
            $char = $this->inputBuffer[0];
            $this->inputBuffer = mb_substr($this->inputBuffer, 1);

            // Handle escape sequences
            if ($char === "\033" && mb_strlen($this->inputBuffer) >= 2) {
                $seq = mb_substr($this->inputBuffer, 0, 2);
                $this->inputBuffer = mb_substr($this->inputBuffer, 2);

                if ($seq === '[A') { // Up arrow
                    if ($this->currentMode === 'tasks') {
                        $this->selectedTask = max(0, $this->selectedTask - 1);
                    } else {
                        $this->activityScroll = max(0, $this->activityScroll - 1);
                    }
                } elseif ($seq === '[B') { // Down arrow
                    if ($this->currentMode === 'tasks') {
                        $this->selectedTask = min(count($this->tasks) - 1, $this->selectedTask + 1);
                    } else {
                        $this->activityScroll = min(count($this->activities) - 1, $this->activityScroll + 1);
                    }
                }

                continue;
            }

            // Regular key handling
            switch ($char) {
                case 'q':
                case 'Q':
                    $this->running = false;
                    break;
                case 's':
                case 'S':
                    $this->sidebarVisible = ! $this->sidebarVisible;
                    break;
                case 't':
                case 'T':
                    $this->currentMode = $this->currentMode === 'tasks' ? 'main' : 'tasks';
                    break;
                case 'h':
                case 'H':
                case '?':
                    $this->currentMode = $this->currentMode === 'help' ? 'main' : 'help';
                    break;
                case 'r':
                case 'R':
                    $this->addRandomActivity();
                    break;
                case "\n":
                    if ($this->currentMode === 'tasks' && isset($this->tasks[$this->selectedTask])) {
                        $this->executeTask($this->selectedTask);
                    }
                    break;
            }
        }
    }

    private function update(): void
    {
        // Update metrics every second
        if ($this->frame % 20 === 0) {
            $this->updateMetrics();
        }

        // Add new activity every 2 seconds
        if ($this->frame % 40 === 0) {
            $this->addRandomActivity();
        }

        // Update task progress
        if ($this->frame % 30 === 0) {
            $this->updateTaskProgress();
        }
    }

    private function render(): void
    {
        echo CLEAR_SCREEN . HOME;

        $this->renderHeader();
        $this->renderMainContent();

        if ($this->sidebarVisible) {
            $this->renderSidebar();
        }

        $this->renderFooter();

        if ($this->currentMode === 'help') {
            $this->renderHelpOverlay();
        }
    }

    private function renderHeader(): void
    {
        echo moveTo(1, 1);
        echo BG_BLUE . FG_WHITE;

        $title = ' 🤖 AI Agent Monitor ';
        $metrics = sprintf('Ops: %d | Success: %.1f%% | Agents: %d ',
            $this->metrics['total_operations'],
            $this->metrics['success_rate'],
            $this->metrics['active_agents']
        );

        $padding = $this->width - mb_strlen($title) - mb_strlen($metrics);
        echo $title . str_repeat(' ', max(0, $padding)) . $metrics;
        echo RESET . "\n";

        // Separator line
        echo FG_GRAY . str_repeat(BOX_H, $this->width) . RESET . "\n";
    }

    private function renderMainContent(): void
    {
        $contentWidth = $this->sidebarVisible ? $this->width - $this->sidebarWidth - 1 : $this->width;
        $contentHeight = $this->height - 5; // Header + footer

        // Activity feed header
        echo moveTo(3, 1);
        echo BOLD . 'Activity Feed' . RESET;
        echo FG_GRAY . ' (' . count($this->activities) . ' items)' . RESET . "\n";

        // Activity items
        $startRow = 4;
        $visibleActivities = array_slice($this->activities, $this->activityScroll, $contentHeight - 2);

        foreach ($visibleActivities as $i => $activity) {
            echo moveTo($startRow + $i, 1);
            $this->renderActivity($activity, $contentWidth);
        }
    }

    private function renderActivity(array $activity, int $maxWidth): void
    {
        $time = date('H:i:s', $activity['time']);
        $timeStr = FG_GRAY . "[{$time}]" . RESET;

        // Icon based on type
        $icon = match ($activity['type']) {
            'tool' => FG_CYAN . '🔧',
            'thinking' => FG_MAGENTA . '💭',
            'response' => FG_GREEN . '✓',
            'error' => FG_RED . '✗',
            default => FG_GRAY . '•'
        };

        // Agent name
        $agent = FG_BLUE . $activity['agent'] . RESET;

        // Message (truncate if needed)
        $message = $activity['message'];
        $prefixLen = 11 + mb_strlen($activity['agent']) + 5; // Time + agent + spacing
        $availableWidth = $maxWidth - $prefixLen;

        if (mb_strlen($message) > $availableWidth) {
            $message = mb_substr($message, 0, $availableWidth - 3) . '...';
        }

        // Status indicator for tools
        $status = '';
        if ($activity['type'] === 'tool' && isset($activity['status'])) {
            $status = $activity['status'] === 'success'
                ? FG_GREEN . ' ✓' . RESET
                : FG_RED . ' ✗' . RESET;
        }

        echo "{$timeStr} {$icon} {$agent}: {$message}{$status}" . RESET;
    }

    private function renderSidebar(): void
    {
        $sidebarStart = $this->width - $this->sidebarWidth;

        // Draw vertical separator
        for ($row = 3; $row <= $this->height - 2; $row++) {
            echo moveTo($row, $sidebarStart);
            echo FG_GRAY . BOX_V . RESET;
        }

        // Sidebar header
        echo moveTo(3, $sidebarStart + 2);
        echo BOLD . 'Task Queue' . RESET;

        // Task statistics
        $pending = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'pending'));
        $running = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'running'));
        $completed = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));

        echo moveTo(4, $sidebarStart + 2);
        echo FG_GREEN . "✓ {$completed}" . RESET;
        echo FG_YELLOW . " ▶ {$running}" . RESET;
        echo FG_GRAY . " ○ {$pending}" . RESET;

        // Task list
        echo moveTo(6, $sidebarStart + 2);
        echo FG_GRAY . str_repeat(BOX_H, $this->sidebarWidth - 3) . RESET;

        $taskStartRow = 7;
        $maxTasks = $this->height - 10;
        $visibleTasks = array_slice($this->tasks, $this->taskScroll, $maxTasks);

        foreach ($visibleTasks as $i => $task) {
            $row = $taskStartRow + $i;
            echo moveTo($row, $sidebarStart + 2);

            $isSelected = $this->currentMode === 'tasks' && ($i + $this->taskScroll) === $this->selectedTask;
            if ($isSelected) {
                echo REVERSE;
            }

            $this->renderTaskLine($task, $this->sidebarWidth - 4);

            if ($isSelected) {
                echo RESET;
            }
        }

        // System info at bottom
        $infoRow = $this->height - 3;
        echo moveTo($infoRow, $sidebarStart + 2);
        echo FG_GRAY . str_repeat(BOX_H, $this->sidebarWidth - 3) . RESET;

        echo moveTo($infoRow + 1, $sidebarStart + 2);
        echo FG_GRAY . 'Avg Response: ' . RESET;
        echo sprintf('%.3fs', $this->metrics['avg_response_time']);
    }

    private function renderTaskLine(array $task, int $maxWidth): void
    {
        $icon = match ($task['status']) {
            'completed' => FG_GREEN . '✓',
            'running' => FG_YELLOW . '▶',
            'pending' => FG_GRAY . '○',
            'failed' => FG_RED . '✗',
            default => ' '
        };

        $title = $task['title'];
        if (mb_strlen($title) > $maxWidth - 4) {
            $title = mb_substr($title, 0, $maxWidth - 7) . '...';
        }

        echo "{$icon} " . RESET . $title;

        // Show progress for running tasks
        if ($task['status'] === 'running' && isset($task['progress'])) {
            $progress = $task['progress'];
            echo FG_GRAY . " {$progress}%" . RESET;
        }
    }

    private function renderFooter(): void
    {
        echo moveTo($this->height - 1, 1);
        echo FG_GRAY . str_repeat(BOX_H, $this->width) . RESET;

        echo moveTo($this->height, 1);

        $shortcuts = [
            '[Q]uit',
            '[S]idebar',
            '[T]asks',
            '[H]elp',
            '[R]efresh',
        ];

        echo FG_GRAY . implode('  ', $shortcuts) . RESET;

        // Mode indicator
        $modeStr = match ($this->currentMode) {
            'tasks' => 'TASKS MODE',
            'help' => 'HELP',
            default => 'MAIN'
        };

        $modePos = $this->width - mb_strlen($modeStr) - 2;
        echo moveTo($this->height, $modePos);
        echo FG_CYAN . "[{$modeStr}]" . RESET;
    }

    private function renderHelpOverlay(): void
    {
        $width = 60;
        $height = 20;
        $startCol = (int) (($this->width - $width) / 2);
        $startRow = (int) (($this->height - $height) / 2);

        // Draw box
        echo moveTo($startRow, $startCol);
        echo BG_DARK . FG_WHITE;
        echo BOX_TL . str_repeat(BOX_H, $width - 2) . BOX_TR;

        for ($i = 1; $i < $height - 1; $i++) {
            echo moveTo($startRow + $i, $startCol);
            echo BOX_V . str_repeat(' ', $width - 2) . BOX_V;
        }

        echo moveTo($startRow + $height - 1, $startCol);
        echo BOX_BL . str_repeat(BOX_H, $width - 2) . BOX_BR;

        // Help content
        $helpItems = [
            ['key' => 'Q', 'desc' => 'Quit application'],
            ['key' => 'S', 'desc' => 'Toggle sidebar'],
            ['key' => 'T', 'desc' => 'Switch to tasks mode'],
            ['key' => 'H/?', 'desc' => 'Show this help'],
            ['key' => 'R', 'desc' => 'Add random activity'],
            ['key' => '↑/↓', 'desc' => 'Navigate lists'],
            ['key' => 'Enter', 'desc' => 'Execute selected task'],
        ];

        echo moveTo($startRow + 2, $startCol + 3);
        echo BOLD . 'Keyboard Shortcuts' . RESET . BG_DARK . FG_WHITE;

        foreach ($helpItems as $i => $item) {
            echo moveTo($startRow + 4 + $i, $startCol + 3);
            echo FG_CYAN . mb_str_pad($item['key'], 10) . FG_WHITE . $item['desc'];
        }

        echo moveTo($startRow + $height - 2, $startCol + 3);
        echo FG_GRAY . 'Press any key to close' . RESET;
    }

    private function initializeDemoData(): void
    {
        $this->activities = [
            ['time' => time() - 5, 'type' => 'tool', 'agent' => 'Coder', 'message' => 'Reading file: src/Agent.php', 'status' => 'success'],
            ['time' => time() - 10, 'type' => 'thinking', 'agent' => 'Analyzer', 'message' => 'Analyzing code structure...'],
            ['time' => time() - 15, 'type' => 'response', 'agent' => 'Assistant', 'message' => 'Found 3 optimization opportunities'],
            ['time' => time() - 20, 'type' => 'tool', 'agent' => 'Tester', 'message' => 'Running test suite', 'status' => 'success'],
            ['time' => time() - 30, 'type' => 'error', 'agent' => 'Validator', 'message' => 'Type mismatch in function parameters'],
        ];

        $this->tasks = [
            ['title' => 'Implement authentication', 'status' => 'completed'],
            ['title' => 'Refactor database layer', 'status' => 'running', 'progress' => 65],
            ['title' => 'Write unit tests', 'status' => 'running', 'progress' => 30],
            ['title' => 'Update documentation', 'status' => 'pending'],
            ['title' => 'Performance optimization', 'status' => 'pending'],
            ['title' => 'Security audit', 'status' => 'pending'],
        ];
    }

    private function addRandomActivity(): void
    {
        $types = ['tool', 'thinking', 'response', 'error'];
        $agents = ['Coder', 'Analyzer', 'Tester', 'Assistant', 'Validator'];
        $messages = [
            'tool' => ['Reading file', 'Writing changes', 'Running command', 'Searching codebase'],
            'thinking' => ['Analyzing patterns', 'Planning approach', 'Evaluating options'],
            'response' => ['Task completed', 'Found solution', 'Generated code'],
            'error' => ['Syntax error', 'Test failed', 'Connection timeout'],
        ];

        $type = $types[array_rand($types)];
        $agent = $agents[array_rand($agents)];
        $message = $messages[$type][array_rand($messages[$type])];

        $activity = [
            'time' => time(),
            'type' => $type,
            'agent' => $agent,
            'message' => $message,
        ];

        if ($type === 'tool') {
            $activity['status'] = rand(0, 10) > 2 ? 'success' : 'failed';
        }

        array_unshift($this->activities, $activity);

        // Keep only last 100 activities
        if (count($this->activities) > 100) {
            array_pop($this->activities);
        }

        $this->metrics['total_operations']++;
    }

    private function updateTaskProgress(): void
    {
        foreach ($this->tasks as &$task) {
            if ($task['status'] === 'running' && isset($task['progress'])) {
                $task['progress'] = min(100, $task['progress'] + rand(5, 15));

                if ($task['progress'] >= 100) {
                    $task['status'] = 'completed';
                    unset($task['progress']);
                }
            }
        }
    }

    private function updateMetrics(): void
    {
        $this->metrics['avg_response_time'] = 0.1 + (rand(0, 400) / 1000);
        $successful = count(array_filter($this->activities, fn ($a) => ($a['status'] ?? '') === 'success' || $a['type'] === 'response'
        ));
        $total = count($this->activities);

        if ($total > 0) {
            $this->metrics['success_rate'] = ($successful / $total) * 100;
        }
    }

    private function executeTask(int $index): void
    {
        if (! isset($this->tasks[$index])) {
            return;
        }

        $task = &$this->tasks[$index];

        if ($task['status'] === 'pending') {
            $task['status'] = 'running';
            $task['progress'] = 0;

            $this->addActivity([
                'time' => time(),
                'type' => 'tool',
                'agent' => 'Executor',
                'message' => "Starting task: {$task['title']}",
                'status' => 'success',
            ]);
        }
    }

    private function addActivity(array $activity): void
    {
        array_unshift($this->activities, $activity);

        if (count($this->activities) > 100) {
            array_pop($this->activities);
        }
    }
}

// Main execution
$ui = new MinimalSidebarUI;

try {
    $ui->run();
} catch (Exception $e) {
    echo SHOW_CURSOR . EXIT_ALT_SCREEN;
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
