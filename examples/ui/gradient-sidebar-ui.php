#!/usr/bin/env php
<?php

/**
 * Gradient Sidebar UI - Subtle gradient backgrounds with smooth rendering
 *
 * Features:
 * - Static gradient backgrounds using 256-color palette
 * - Double buffering for smooth updates
 * - Inline status blocks at top
 * - Collapsible sidebar with state transition
 * - Professional color scheme
 */

// ANSI escape codes
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const ITALIC = "\033[3m";
const UNDERLINE = "\033[4m";

// Terminal control
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";
const CLEAR = "\033[2J";
const HOME = "\033[H";
const ENTER_ALT_SCREEN = "\033[?1049h";
const EXIT_ALT_SCREEN = "\033[?1049l";

// Box drawing
const BOX_H = '─';
const BOX_V = '│';
const BOX_TL = '╭';
const BOX_TR = '╮';
const BOX_BL = '╰';
const BOX_BR = '╯';
const BOX_DOT = '·';

// Gradient color ranges (256-color palette)
function getGradientBg(int $level): string
{
    // Gradient from dark (232) to slightly lighter (237)
    $colors = [232, 233, 234, 235, 236, 237];
    $index = min($level, count($colors) - 1);

    return "\033[48;5;{$colors[$index]}m";
}

function getGradientFg(int $level): string
{
    // Gradient from dim (240) to bright (255)
    $colors = [240, 243, 246, 249, 252, 255];
    $index = min($level, count($colors) - 1);

    return "\033[38;5;{$colors[$index]}m";
}

// Static accent colors
const ACCENT_BLUE = "\033[38;5;75m";
const ACCENT_GREEN = "\033[38;5;120m";
const ACCENT_YELLOW = "\033[38;5;221m";
const ACCENT_RED = "\033[38;5;203m";
const ACCENT_PURPLE = "\033[38;5;141m";
const ACCENT_CYAN = "\033[38;5;87m";

class GradientSidebarUI
{
    private int $width = 120;

    private int $height = 40;

    private int $sidebarWidth = 38;

    private bool $sidebarExpanded = true;

    private int $frame = 0;

    // Double buffering
    private array $currentBuffer = [];

    private array $nextBuffer = [];

    private string $outputBuffer = '';

    // UI State
    private array $activities = [];

    private array $tasks = [];

    private array $statusBlocks = [];

    private int $selectedTask = 0;

    private int $activityScroll = 0;

    // System metrics
    private array $metrics = [
        'operations' => 142,
        'success_rate' => 94.5,
        'active_tasks' => 3,
        'response_ms' => 127,
    ];

    private bool $running = false;

    private string $inputBuffer = '';

    private float $lastRenderTime = 0;

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->initializeBuffers();
        $this->initializeData();

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGWINCH, [$this, 'handleResize']);
        }
    }

    public function run(): void
    {
        $this->initializeTerminal();
        $this->running = true;

        while ($this->running) {
            $currentTime = microtime(true) * 1000;

            $this->handleInput();
            $this->update();

            // Throttled rendering at ~30 FPS
            if ($currentTime - $this->lastRenderTime >= 33) {
                $this->render();
                $this->lastRenderTime = $currentTime;
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            usleep(5000);
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
        echo RESET;
        system('stty sane');
    }

    private function updateTerminalSize(): void
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;
    }

    private function initializeBuffers(): void
    {
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

    private function handleSignal(int $signal): void
    {
        if ($signal === SIGINT) {
            $this->cleanup();
            exit(0);
        }
    }

    private function handleResize(int $signal): void
    {
        $this->updateTerminalSize();
        $this->initializeBuffers();
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

                if ($seq === '[A') { // Up
                    $this->selectedTask = max(0, $this->selectedTask - 1);
                } elseif ($seq === '[B') { // Down
                    $this->selectedTask = min(count($this->tasks) - 1, $this->selectedTask + 1);
                }

                continue;
            }

            switch ($char) {
                case 'q':
                case 'Q':
                    $this->running = false;
                    break;
                case 's':
                case 'S':
                    $this->sidebarExpanded = ! $this->sidebarExpanded;
                    break;
                case 'r':
                    $this->addRandomActivity();
                    break;
            }
        }
    }

    private function update(): void
    {
        if ($this->frame % 40 === 0) {
            $this->addRandomActivity();
            $this->updateMetrics();
        }

        if ($this->frame % 30 === 0) {
            $this->updateTaskProgress();
        }
    }

    private function render(): void
    {
        $this->clearNextBuffer();

        $this->renderHeader();
        $this->renderMainArea();
        $this->renderSidebar();
        $this->renderFooter();

        $this->performRender();

        // Swap buffers
        $temp = $this->currentBuffer;
        $this->currentBuffer = $this->nextBuffer;
        $this->nextBuffer = $temp;
    }

    private function clearNextBuffer(): void
    {
        for ($row = 1; $row <= $this->height; $row++) {
            // Apply gradient background to entire screen
            $gradientLevel = (int) (($row - 1) / $this->height * 5);
            $bgStyle = getGradientBg($gradientLevel);

            for ($col = 1; $col <= $this->width; $col++) {
                $this->nextBuffer[$row][$col] = ['char' => ' ', 'style' => $bgStyle];
            }
        }
    }

    private function performRender(): void
    {
        $this->outputBuffer = HOME;

        for ($row = 1; $row <= $this->height; $row++) {
            $this->outputBuffer .= "\033[{$row};1H";
            $currentStyle = '';

            for ($col = 1; $col <= $this->width; $col++) {
                $cell = $this->nextBuffer[$row][$col];

                if ($cell['style'] !== $currentStyle) {
                    $this->outputBuffer .= $cell['style'];
                    $currentStyle = $cell['style'];
                }

                $this->outputBuffer .= $cell['char'];
            }
        }

        echo $this->outputBuffer;
        $this->outputBuffer = '';
    }

    private function setCell(int $row, int $col, string $char, string $style = ''): void
    {
        if ($row >= 1 && $row <= $this->height && $col >= 1 && $col <= $this->width) {
            if ($style === '') {
                $gradientLevel = (int) (($row - 1) / $this->height * 5);
                $style = getGradientBg($gradientLevel);
            }
            $this->nextBuffer[$row][$col] = ['char' => $char, 'style' => $style];
        }
    }

    private function setCellString(int $row, int $col, string $text, string $style = ''): void
    {
        $length = mb_strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $this->setCell($row, $col + $i, mb_substr($text, $i, 1), $style);
        }
    }

    private function renderHeader(): void
    {
        // Gradient header bar
        $headerBg = getGradientBg(0);
        for ($col = 1; $col <= $this->width; $col++) {
            $this->setCell(1, $col, ' ', $headerBg);
        }

        $title = ' ◈ Gradient Agent UI';
        $this->setCellString(1, 2, $title, $headerBg . ACCENT_CYAN . BOLD);

        // Metrics on right
        $metrics = sprintf('%.1f%% | %dms | %s',
            $this->metrics['success_rate'],
            $this->metrics['response_ms'],
            date('H:i:s')
        );
        $metricsCol = $this->width - mb_strlen($metrics) - 1;
        $this->setCellString(1, $metricsCol, $metrics, $headerBg . getGradientFg(3));
    }

    private function renderMainArea(): void
    {
        $mainWidth = $this->sidebarExpanded ? $this->width - $this->sidebarWidth : $this->width - 3;

        // Activity feed title
        $this->setCellString(3, 2, 'Activity Stream', getGradientBg(1) . getGradientFg(5) . BOLD);

        // Render activities
        $row = 5;
        $visibleActivities = array_slice($this->activities, $this->activityScroll, $this->height - 8);

        foreach ($visibleActivities as $activity) {
            if ($row >= $this->height - 2) {
                break;
            }

            $this->renderActivity($activity, $row, 2, $mainWidth - 3);
            $row += 2;
        }
    }

    private function renderActivity(array $activity, int $row, int $col, int $maxWidth): void
    {
        $time = date('H:i:s', $activity['time']);
        $gradientLevel = (int) (($row - 1) / $this->height * 5);
        $bg = getGradientBg($gradientLevel);

        // Time stamp
        $this->setCellString($row, $col, "[{$time}]", $bg . getGradientFg(2));

        // Agent name with color
        $agentColor = match ($activity['agent']) {
            'Analyzer' => ACCENT_BLUE,
            'Coder' => ACCENT_GREEN,
            'Tester' => ACCENT_YELLOW,
            'Reviewer' => ACCENT_PURPLE,
            default => ACCENT_CYAN
        };
        $this->setCellString($row, $col + 11, $activity['agent'], $bg . $agentColor . BOLD);

        // Message on next line
        $message = $activity['message'];
        if (mb_strlen($message) > $maxWidth - 4) {
            $message = mb_substr($message, 0, $maxWidth - 7) . '...';
        }

        $messageColor = match ($activity['type']) {
            'success' => ACCENT_GREEN,
            'error' => ACCENT_RED,
            'warning' => ACCENT_YELLOW,
            default => getGradientFg(4)
        };

        $icon = match ($activity['type']) {
            'success' => '✓ ',
            'error' => '✗ ',
            'warning' => '! ',
            'tool' => '⚙ ',
            default => '• '
        };

        $this->setCellString($row + 1, $col + 2, $icon . $message,
            getGradientBg($gradientLevel + 1) . $messageColor);
    }

    private function renderSidebar(): void
    {
        if (! $this->sidebarExpanded) {
            // Collapsed state - just show indicator
            $col = $this->width - 2;
            for ($row = 2; $row <= $this->height - 1; $row++) {
                $this->setCell($row, $col, '▐', getGradientBg(2) . getGradientFg(1));
            }

            return;
        }

        $sidebarStart = $this->width - $this->sidebarWidth + 1;

        // Sidebar gradient background
        for ($row = 2; $row <= $this->height - 1; $row++) {
            $gradientLevel = min(2, (int) (($row - 2) / $this->height * 3));
            $bg = getGradientBg($gradientLevel);
            for ($col = $sidebarStart; $col <= $this->width; $col++) {
                $this->setCell($row, $col, ' ', $bg);
            }
        }

        // Vertical separator with gradient
        for ($row = 2; $row <= $this->height - 1; $row++) {
            $this->setCell($row, $sidebarStart - 1, BOX_V,
                getGradientBg(1) . getGradientFg(1));
        }

        $col = $sidebarStart + 1;
        $row = 3;

        // Status blocks at top
        $this->renderStatusBlocks($row, $col);
        $row += 5;

        // Task queue
        $this->setCellString($row++, $col, '◆ Task Queue',
            getGradientBg(1) . ACCENT_CYAN . BOLD);
        $row++;

        $maxTasks = min(count($this->tasks), 8);
        for ($i = 0; $i < $maxTasks; $i++) {
            if ($row >= $this->height - 3) {
                break;
            }

            $task = $this->tasks[$i];
            $isSelected = $i === $this->selectedTask;

            $bg = $isSelected
                ? "\033[48;5;238m"
                : getGradientBg((int) (($row - 2) / $this->height * 3));

            $icon = match ($task['status']) {
                'completed' => ACCENT_GREEN . '✓',
                'running' => ACCENT_YELLOW . '●',
                'pending' => getGradientFg(2) . '○',
                default => ' '
            };

            $this->setCellString($row, $col, $icon . ' ', $bg);

            $title = $task['title'];
            if (mb_strlen($title) > $this->sidebarWidth - 6) {
                $title = mb_substr($title, 0, $this->sidebarWidth - 9) . '...';
            }

            $this->setCellString($row, $col + 2, $title,
                $bg . ($isSelected ? getGradientFg(5) : getGradientFg(4)));

            // Progress bar for running tasks
            if ($task['status'] === 'running' && isset($task['progress'])) {
                $row++;
                $this->renderProgressBar($row, $col + 2, $task['progress'],
                    $this->sidebarWidth - 5);
            }

            $row++;
        }
    }

    private function renderStatusBlocks(int $row, int $col): void
    {
        // Block 1: Operations
        $this->setCellString($row, $col, '▪ Operations: ',
            getGradientBg(0) . getGradientFg(3));
        $this->setCellString($row, $col + 14, (string) $this->metrics['operations'],
            getGradientBg(0) . ACCENT_GREEN . BOLD);

        // Block 2: Success Rate
        $row++;
        $this->setCellString($row, $col, '▪ Success: ',
            getGradientBg(0) . getGradientFg(3));
        $rateColor = $this->metrics['success_rate'] > 90 ? ACCENT_GREEN : ACCENT_YELLOW;
        $this->setCellString($row, $col + 11, sprintf('%.1f%%', $this->metrics['success_rate']),
            getGradientBg(0) . $rateColor . BOLD);

        // Block 3: Active Tasks
        $row++;
        $this->setCellString($row, $col, '▪ Active: ',
            getGradientBg(0) . getGradientFg(3));
        $this->setCellString($row, $col + 10, (string) $this->metrics['active_tasks'],
            getGradientBg(0) . ACCENT_CYAN . BOLD);
    }

    private function renderProgressBar(int $row, int $col, float $progress, int $width): void
    {
        $filled = (int) ($progress / 100 * $width);
        $bg = getGradientBg(2);

        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                $this->setCell($row, $col + $i, '█', $bg . ACCENT_GREEN);
            } else {
                $this->setCell($row, $col + $i, '░', $bg . getGradientFg(1));
            }
        }

        $percentText = sprintf(' %d%%', (int) $progress);
        $this->setCellString($row, $col + $width, $percentText, $bg . getGradientFg(3));
    }

    private function renderFooter(): void
    {
        $footerBg = getGradientBg(5);
        for ($col = 1; $col <= $this->width; $col++) {
            $this->setCell($this->height, $col, ' ', $footerBg);
        }

        $shortcuts = '[Q]uit  [S]idebar  [R]efresh  [↑↓]Navigate';
        $this->setCellString($this->height, 2, $shortcuts, $footerBg . getGradientFg(2));

        $mode = $this->sidebarExpanded ? 'EXPANDED' : 'COLLAPSED';
        $modeCol = $this->width - mb_strlen($mode) - 1;
        $this->setCellString($this->height, $modeCol, $mode, $footerBg . ACCENT_CYAN);
    }

    private function initializeData(): void
    {
        $this->activities = [
            ['time' => time() - 5, 'type' => 'success', 'agent' => 'Coder',
                'message' => 'Implemented authentication module'],
            ['time' => time() - 12, 'type' => 'tool', 'agent' => 'Analyzer',
                'message' => 'Analyzing code complexity...'],
            ['time' => time() - 20, 'type' => 'warning', 'agent' => 'Tester',
                'message' => 'Found 2 potential issues in test coverage'],
            ['time' => time() - 35, 'type' => 'success', 'agent' => 'Reviewer',
                'message' => 'Code review completed'],
        ];

        $this->tasks = [
            ['title' => 'Implement user dashboard', 'status' => 'running', 'progress' => 65],
            ['title' => 'Write API documentation', 'status' => 'running', 'progress' => 30],
            ['title' => 'Setup CI/CD pipeline', 'status' => 'completed'],
            ['title' => 'Database optimization', 'status' => 'pending'],
            ['title' => 'Security audit', 'status' => 'pending'],
            ['title' => 'Performance testing', 'status' => 'pending'],
        ];
    }

    private function addRandomActivity(): void
    {
        $types = ['success', 'tool', 'warning', 'error'];
        $agents = ['Coder', 'Analyzer', 'Tester', 'Reviewer'];
        $messages = [
            'Completed task successfully',
            'Running analysis...',
            'Detected potential issue',
            'Processing request',
        ];

        array_unshift($this->activities, [
            'time' => time(),
            'type' => $types[array_rand($types)],
            'agent' => $agents[array_rand($agents)],
            'message' => $messages[array_rand($messages)],
        ]);

        if (count($this->activities) > 50) {
            array_pop($this->activities);
        }
    }

    private function updateMetrics(): void
    {
        $this->metrics['operations'] += rand(1, 5);
        $this->metrics['success_rate'] = min(100, max(80,
            $this->metrics['success_rate'] + rand(-2, 3)));
        $this->metrics['response_ms'] = rand(50, 200);
        $this->metrics['active_tasks'] = count(array_filter($this->tasks,
            fn ($t) => $t['status'] === 'running'));
    }

    private function updateTaskProgress(): void
    {
        foreach ($this->tasks as &$task) {
            if ($task['status'] === 'running' && isset($task['progress'])) {
                $task['progress'] = min(100, $task['progress'] + rand(2, 8));
                if ($task['progress'] >= 100) {
                    $task['status'] = 'completed';
                    unset($task['progress']);
                }
            }
        }
    }
}

// Main execution
$ui = new GradientSidebarUI;

try {
    $ui->run();
} catch (Exception $e) {
    echo SHOW_CURSOR . EXIT_ALT_SCREEN . RESET;
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
