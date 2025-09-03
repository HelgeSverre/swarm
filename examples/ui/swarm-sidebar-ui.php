#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/WidthCalculator.php';

/**
 * Swarm Sidebar UI - Terminal Interface with Activity Feed and Task Queue
 *
 * Features:
 * - Full-width header with swarm branding
 * - Clean sidebar with task queue
 * - Activity feed with proper formatting
 * - Keyboard navigation
 * - Proper terminal management
 * - Emoji-aware width calculations for consistent alignment
 */

// ANSI color constants
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const ITALIC = "\033[3m";
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

// Colors - matching terminal_ui_mockup_3.php palette
const BLACK = "\033[30m";
const RED = "\033[31m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const MAGENTA = "\033[35m";
const CYAN = "\033[36m";
const WHITE = "\033[37m";
const GRAY = "\033[90m";
const BRIGHT_CYAN = "\033[96m";

const BG_DEFAULT = "\033[49m";
const BG_DARK = "\033[48;5;236m"; // Dark gray background from mockup

// Box drawing characters
const BOX_H = '─';
const BOX_V = '│';
const BOX_V_HEAVY = '┃';
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

class SwarmSidebarUI
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
        'status' => 'ready',
        'current_task' => 'Monitoring system',
        'current_step' => 0,
        'total_steps' => 0,
        'total_operations' => 0,
        'success_rate' => 100,
        'active_agents' => 3,
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
        // Full-width header with swarm branding (like terminal_ui_mockup_3)
        echo moveTo(1, 1);

        // Build header content components
        $prefix = ' 💮 swarm ' . BOX_V . ' ';

        $taskText = WidthCalculator::truncate($this->metrics['current_task'], 30);
        $taskSection = '● ' . $taskText . ' ';

        $status = $this->metrics['status'];
        if ($this->metrics['total_steps'] > 0) {
            $status .= " ({$this->metrics['current_step']}/{$this->metrics['total_steps']})";
        }

        // Calculate padding for full width: prefix + taskSection + separator + status
        $prefixWidth = WidthCalculator::width($prefix);
        $taskWidth = WidthCalculator::width($taskSection);
        $separatorWidth = WidthCalculator::width(BOX_V . ' ');
        $statusWidth = WidthCalculator::width($status);
        $totalWidth = $prefixWidth + $taskWidth + $separatorWidth + $statusWidth;
        $paddingNeeded = max(0, $this->width - $totalWidth);
        $padding = str_repeat(' ', $paddingNeeded);

        // Build styled content with colors
        $styledContent = $prefix . GREEN . $taskSection . RESET . BOX_V . ' ' . YELLOW . $status . RESET;

        // Render entire header line with proper background
        echo BG_DARK . $styledContent . $padding . RESET . "\n";

        // Separator line
        echo GRAY . str_repeat(BOX_H, $this->width) . RESET . "\n";
    }

    private function renderMainContent(): void
    {
        $contentWidth = $this->sidebarVisible ? $this->width - $this->sidebarWidth - 1 : $this->width;
        $contentHeight = $this->height - 5; // Header + footer

        // Activity feed header
        echo moveTo(4, 2);
        echo BOLD . 'Activity Feed' . RESET;
        echo GRAY . ' (' . count($this->activities) . ' items)' . RESET . "\n";

        // Activity items with proper indentation
        $startRow = 5;
        $visibleActivities = array_slice($this->activities, $this->activityScroll, $contentHeight - 3);

        foreach ($visibleActivities as $i => $activity) {
            echo moveTo($startRow + $i, 2);
            $this->renderActivity($activity, $contentWidth - 2);
        }
    }

    private function renderActivity(array $activity, int $maxWidth): void
    {
        $time = date('H:i:s', $activity['time']);
        $timeText = "[{$time}]";

        // Icon based on type (without colors for width calculation)
        $iconText = match ($activity['type']) {
            'tool' => '🔧',
            'thinking' => '💭',
            'response' => '✓',
            'error' => '✗',
            'command' => '$',
            'status' => '✓',
            default => '•'
        };

        // Agent name (without colors for width calculation)
        $agentText = $activity['agent'];

        // Calculate available width for message using visual content only
        $timeWidth = WidthCalculator::width($timeText);
        $iconWidth = WidthCalculator::width($iconText);
        $agentWidth = WidthCalculator::width($agentText);
        $staticWidth = $timeWidth + $iconWidth + $agentWidth + 4; // spacing and colon
        $availableWidth = $maxWidth - $staticWidth;

        // Status indicator for tools
        $statusText = '';
        if ($activity['type'] === 'tool' && isset($activity['status'])) {
            $statusText = $activity['status'] === 'success' ? ' ✓' : ' ✗';
            $availableWidth -= WidthCalculator::width($statusText);
        }

        // Truncate message with emoji awareness
        $message = WidthCalculator::truncate($activity['message'], max(0, $availableWidth));

        // Apply colors for rendering
        $timeStr = DIM . $timeText . RESET;
        $icon = match ($activity['type']) {
            'tool' => CYAN . $iconText,
            'thinking' => MAGENTA . $iconText,
            'response' => GREEN . $iconText,
            'error' => RED . $iconText,
            'command' => BLUE . $iconText,
            'status' => GREEN . $iconText,
            default => GRAY . $iconText
        };
        $agent = CYAN . $agentText . RESET;
        $status = $statusText ? ($activity['status'] === 'success' ? GREEN . $statusText . RESET : RED . $statusText . RESET) : '';

        echo "{$timeStr} {$icon} {$agent}: {$message}{$status}" . RESET;
    }

    private function renderSidebar(): void
    {
        $sidebarStart = $this->width - $this->sidebarWidth;

        // Draw vertical separator (heavy line like mockup)
        for ($row = 3; $row <= $this->height - 2; $row++) {
            echo moveTo($row, $sidebarStart);
            echo DIM . BOX_V_HEAVY . RESET;
        }

        // Sidebar header
        echo moveTo(3, $sidebarStart + 2);
        echo BOLD . UNDERLINE . 'Task Queue' . RESET;

        // Task statistics
        $pending = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'pending'));
        $running = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'running'));
        $completed = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));

        echo moveTo(4, $sidebarStart + 2);
        echo GREEN . "{$running} running" . RESET . ', ';
        echo DIM . "{$pending} pending" . RESET;

        // Task list
        echo moveTo(6, $sidebarStart + 2);
        echo GRAY . str_repeat(BOX_H, $this->sidebarWidth - 3) . RESET;

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
        echo GRAY . str_repeat(BOX_H, $this->sidebarWidth - 3) . RESET;

        echo moveTo($infoRow + 1, $sidebarStart + 2);
        echo GRAY . 'Ops: ' . RESET . $this->metrics['total_operations'];
        echo GRAY . ' | Success: ' . RESET;
        echo sprintf('%.1f%%', $this->metrics['success_rate']);
    }

    private function renderTaskLine(array $task, int $maxWidth): void
    {
        // Icon without colors for width calculation
        $iconText = match ($task['status']) {
            'completed' => '✓',
            'running' => '▶',
            'pending' => '○',
            'failed' => '✗',
            default => ' '
        };

        // Calculate available width for title using visual content only
        $iconWidth = WidthCalculator::width($iconText);
        $availableWidth = $maxWidth - $iconWidth - 1; // icon + space

        // Show progress for running tasks
        $progressText = '';
        if ($task['status'] === 'running' && isset($task['progress'])) {
            $progressText = " {$task['progress']}%";
            $availableWidth -= WidthCalculator::width($progressText);
        }

        // Truncate title with emoji awareness
        $title = WidthCalculator::truncate($task['title'], max(0, $availableWidth));

        // Apply colors for rendering
        $icon = match ($task['status']) {
            'completed' => GREEN . $iconText,
            'running' => YELLOW . $iconText,
            'pending' => DIM . $iconText,
            'failed' => RED . $iconText,
            default => $iconText
        };
        $styledProgress = $progressText ? DIM . $progressText . RESET : '';

        echo "{$icon} " . RESET . $title . $styledProgress;
    }

    private function renderFooter(): void
    {
        echo moveTo($this->height - 1, 1);
        echo GRAY . str_repeat(BOX_H, $this->width) . RESET;

        echo moveTo($this->height, 1);

        $shortcuts = [
            '[Q]uit',
            '[S]idebar',
            '[T]asks',
            '[H]elp',
            '[R]efresh',
        ];

        echo GRAY . implode('  ', $shortcuts) . RESET;

        // Mode indicator
        $modeStr = match ($this->currentMode) {
            'tasks' => 'TASKS MODE',
            'help' => 'HELP',
            default => 'MAIN'
        };

        $modePos = $this->width - WidthCalculator::width("[{$modeStr}]");
        echo moveTo($this->height, $modePos);
        echo CYAN . "[{$modeStr}]" . RESET;
    }

    private function renderHelpOverlay(): void
    {
        $width = 60;
        $height = 20;
        $startCol = (int) (($this->width - $width) / 2);
        $startRow = (int) (($this->height - $height) / 2);

        // Draw box with dark background
        echo moveTo($startRow, $startCol);
        echo BG_DARK . WHITE;
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
        echo BOLD . 'Keyboard Shortcuts' . RESET . BG_DARK . WHITE;

        foreach ($helpItems as $i => $item) {
            echo moveTo($startRow + 4 + $i, $startCol + 3);
            echo CYAN . mb_str_pad($item['key'], 10) . WHITE . $item['desc'];
        }

        echo moveTo($startRow + $height - 2, $startCol + 3);
        echo GRAY . 'Press any key to close' . RESET;
    }

    private function truncate(string $text, int $length): string
    {
        return WidthCalculator::truncate($text, $length);
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

        // Set current task from running tasks
        $runningTasks = array_filter($this->tasks, fn ($t) => $t['status'] === 'running');
        if (! empty($runningTasks)) {
            $task = reset($runningTasks);
            $this->metrics['current_task'] = $task['title'];
            $this->metrics['status'] = 'working';
            $this->metrics['current_step'] = 2;
            $this->metrics['total_steps'] = 4;
        }
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
$ui = new SwarmSidebarUI;

try {
    $ui->run();
} catch (Exception $e) {
    echo SHOW_CURSOR . EXIT_ALT_SCREEN;
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
