#!/usr/bin/env php
<?php

/**
 * Ultimate Terminal UI
 * Combines enhanced status bar, modern sidebar, activity feed, and smooth animations
 * Features from demos 1, 2, 4, and 10
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const UNDERLINE = "\033[4m";
const BLINK = "\033[5m";
const REVERSE = "\033[7m";
const CLEAR = "\033[2J\033[H";
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";

// Tokyo Night color scheme
const BG_DARK = "\033[48;5;234m";
const BG_DARKER = "\033[48;5;232m";
const BG_SIDEBAR = "\033[48;5;235m";
const BG_ACTIVE = "\033[48;5;237m";
const BG_HIGHLIGHT = "\033[48;5;238m";

const FG_WHITE = "\033[38;5;255m";
const FG_GRAY = "\033[38;5;245m";
const FG_BLUE = "\033[38;5;117m";
const FG_GREEN = "\033[38;5;120m";
const FG_YELLOW = "\033[38;5;221m";
const FG_RED = "\033[38;5;203m";
const FG_PURPLE = "\033[38;5;141m";
const FG_CYAN = "\033[38;5;87m";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

function clearLine(): void
{
    echo "\033[2K";
}

function getTerminalSize(): array
{
    $width = (int) exec('tput cols') ?: 120;
    $height = (int) exec('tput lines') ?: 40;

    return ['width' => $width, 'height' => $height];
}

class UltimateTerminalUI
{
    private int $terminalWidth;

    private int $terminalHeight;

    private int $sidebarWidth = 25;

    private int $mainAreaWidth;

    private int $frame = 0;

    private float $cpuUsage = 45.0;

    private float $memoryUsage = 62.0;

    private array $activityFeed = [];

    private array $sidebarSections = [];

    private int $selectedSection = 0;

    private array $tasks = [];

    private float $scrollPosition = 0;

    private array $animations = [];

    public function __construct()
    {
        $size = getTerminalSize();
        $this->terminalWidth = $size['width'];
        $this->terminalHeight = $size['height'];
        $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;

        $this->initializeSidebar();
        $this->initializeActivityFeed();
        $this->initializeTasks();
        $this->initializeAnimations();
    }

    public function render(): void
    {
        echo CLEAR . HIDE_CURSOR;

        // Update animations
        $this->updateAnimations();

        // Render all components
        $this->renderTopStatusBar();
        $this->renderSidebar();
        $this->renderMainContent();
        $this->renderBottomStatusBar();

        // Update frame counter
        $this->frame++;

        // Simulate data updates
        $this->simulateUpdates();
    }

    private function initializeSidebar(): void
    {
        $this->sidebarSections = [
            [
                'title' => '📊 Dashboard',
                'items' => ['Overview', 'Analytics', 'Reports'],
                'expanded' => true,
                'icon' => '📊',
            ],
            [
                'title' => '📁 Projects',
                'items' => ['swarm-ui', 'terminal-pro', 'cli-tools'],
                'expanded' => true,
                'icon' => '📁',
            ],
            [
                'title' => '🔧 Tools',
                'items' => ['Terminal', 'Debugger', 'Profiler'],
                'expanded' => false,
                'icon' => '🔧',
            ],
            [
                'title' => '⚙️ Settings',
                'items' => ['Preferences', 'Theme', 'Shortcuts'],
                'expanded' => false,
                'icon' => '⚙️',
            ],
        ];
    }

    private function initializeActivityFeed(): void
    {
        $this->activityFeed = [
            ['time' => time() - 30, 'type' => 'success', 'message' => 'Build completed successfully', 'icon' => '✅'],
            ['time' => time() - 120, 'type' => 'info', 'message' => 'New dependency installed', 'icon' => '📦'],
            ['time' => time() - 300, 'type' => 'warning', 'message' => 'Memory usage above 60%', 'icon' => '⚠️'],
            ['time' => time() - 600, 'type' => 'info', 'message' => 'Server started on port 8000', 'icon' => '🚀'],
            ['time' => time() - 900, 'type' => 'success', 'message' => 'All tests passed', 'icon' => '✓'],
        ];
    }

    private function initializeTasks(): void
    {
        $this->tasks = [
            ['name' => 'Code compilation', 'progress' => 75, 'status' => 'running'],
            ['name' => 'Unit tests', 'progress' => 100, 'status' => 'completed'],
            ['name' => 'Deploy to staging', 'progress' => 30, 'status' => 'running'],
            ['name' => 'Database migration', 'progress' => 0, 'status' => 'pending'],
        ];
    }

    private function initializeAnimations(): void
    {
        $this->animations = [
            'cpu' => ['current' => 45.0, 'target' => 45.0, 'velocity' => 0],
            'memory' => ['current' => 62.0, 'target' => 62.0, 'velocity' => 0],
            'sidebar' => ['current' => 0, 'target' => 0, 'velocity' => 0],
        ];
    }

    private function renderTopStatusBar(): void
    {
        // Gradient background effect
        moveCursor(1, 1);
        echo BG_DARKER;

        // Left section - System name with animated icon
        $icon = $this->frame % 20 < 10 ? '🚀' : '💫';
        echo FG_CYAN . BOLD . " {$icon} ULTIMATE UI " . RESET;

        // Center section - Live metrics with smooth animations
        $cpuBar = $this->renderMiniBar($this->animations['cpu']['current'], 10, FG_GREEN);
        $memBar = $this->renderMiniBar($this->animations['memory']['current'], 10, FG_YELLOW);

        $centerContent = "CPU {$cpuBar} " . sprintf('%3.0f%%', $this->animations['cpu']['current']) .
                        " │ MEM {$memBar} " . sprintf('%3.0f%%', $this->animations['memory']['current']);

        $leftLen = 16;
        $centerLen = mb_strlen(strip_tags($centerContent));
        $centerPos = ($this->terminalWidth - $centerLen) / 2;

        moveCursor(1, (int) $centerPos);
        echo BG_DARKER . FG_WHITE . $centerContent . RESET;

        // Right section - Time with seconds
        $time = date('H:i:s');
        $rightContent = "🕐 {$time} ";
        moveCursor(1, $this->terminalWidth - mb_strlen($rightContent) + 1);
        echo BG_DARKER . FG_GRAY . $rightContent . RESET;

        // Fill remaining space
        echo BG_DARKER . str_repeat(' ', 10) . RESET;
    }

    private function renderSidebar(): void
    {
        $startRow = 3;
        $height = $this->terminalHeight - 4;

        // Sidebar background with subtle animation
        for ($row = 0; $row < $height; $row++) {
            moveCursor($startRow + $row, 1);
            echo BG_SIDEBAR . str_repeat(' ', $this->sidebarWidth) . RESET;
        }

        // Sidebar header
        moveCursor($startRow, 2);
        echo BG_SIDEBAR . FG_WHITE . BOLD . ' NAVIGATION' . RESET;

        moveCursor($startRow + 1, 2);
        echo BG_SIDEBAR . FG_GRAY . str_repeat('─', $this->sidebarWidth - 3) . RESET;

        // Render sections with smooth hover effect
        $currentRow = $startRow + 3;
        foreach ($this->sidebarSections as $index => $section) {
            // Section header with animation
            moveCursor($currentRow, 2);

            $isSelected = $index === $this->selectedSection;
            if ($isSelected) {
                echo BG_ACTIVE;
            } else {
                echo BG_SIDEBAR;
            }

            $arrow = $section['expanded'] ? '▼' : '▶';
            echo FG_CYAN . " {$arrow} " . $section['icon'] . ' ' . FG_WHITE;
            echo $section['title'] . RESET;

            $currentRow++;

            // Items with smooth reveal
            if ($section['expanded']) {
                foreach ($section['items'] as $item) {
                    moveCursor($currentRow, 2);
                    echo BG_SIDEBAR . FG_GRAY . '    • ' . $item . RESET;
                    $currentRow++;
                }
            }

            $currentRow++;
        }

        // Sidebar footer with version
        moveCursor($this->terminalHeight - 2, 2);
        echo BG_SIDEBAR . FG_GRAY . DIM . ' v2.0.0 Premium' . RESET;
    }

    private function renderMainContent(): void
    {
        $startCol = $this->sidebarWidth + 2;
        $startRow = 3;
        $contentHeight = $this->terminalHeight - 4;

        // Main content area background
        for ($row = 0; $row < $contentHeight; $row++) {
            moveCursor($startRow + $row, $startCol);
            echo BG_DARK . str_repeat(' ', $this->mainAreaWidth) . RESET;
        }

        // Content header
        moveCursor($startRow, $startCol + 2);
        echo BG_DARK . FG_WHITE . BOLD . 'Dashboard Overview' . RESET;

        // Render different content sections
        $this->renderMetricsSection($startRow + 2, $startCol + 2);
        $this->renderActivitySection($startRow + 10, $startCol + 2);
        $this->renderTasksSection($startRow + 18, $startCol + 2);
    }

    private function renderMetricsSection(int $row, int $col): void
    {
        // Section title
        moveCursor($row, $col);
        echo BG_DARK . FG_CYAN . BOLD . '📈 System Metrics' . RESET;

        // CPU Usage with animated bar
        moveCursor($row + 2, $col);
        echo BG_DARK . FG_WHITE . 'CPU Usage:    ' . RESET;
        $this->renderProgressBar($row + 2, $col + 14, 30, $this->animations['cpu']['current'], FG_GREEN);

        // Memory Usage with animated bar
        moveCursor($row + 3, $col);
        echo BG_DARK . FG_WHITE . 'Memory Usage: ' . RESET;
        $this->renderProgressBar($row + 3, $col + 14, 30, $this->animations['memory']['current'], FG_YELLOW);

        // Network I/O with pulsing effect
        moveCursor($row + 4, $col);
        $networkIn = 234.5 + sin($this->frame / 10) * 50;
        $networkOut = 123.4 + cos($this->frame / 10) * 30;
        echo BG_DARK . FG_WHITE . sprintf('Network I/O:  ↓ %.1f KB/s  ↑ %.1f KB/s', $networkIn, $networkOut) . RESET;

        // Disk usage
        moveCursor($row + 5, $col);
        echo BG_DARK . FG_WHITE . 'Disk Usage:   ' . RESET;
        $this->renderProgressBar($row + 5, $col + 14, 30, 35, FG_PURPLE);
    }

    private function renderActivitySection(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BG_DARK . FG_CYAN . BOLD . '📋 Recent Activity' . RESET;

        $maxItems = 5;
        foreach (array_slice($this->activityFeed, 0, $maxItems) as $index => $activity) {
            moveCursor($row + 2 + $index, $col);

            // Time ago with fade effect
            $timeAgo = $this->getTimeAgo($activity['time']);
            $opacity = 255 - ($index * 20);

            // Activity icon and message
            echo BG_DARK . $activity['icon'] . ' ';

            // Color based on type
            $color = match ($activity['type']) {
                'success' => FG_GREEN,
                'warning' => FG_YELLOW,
                'error' => FG_RED,
                default => FG_WHITE
            };

            echo $color . $activity['message'] . RESET;
            echo BG_DARK . FG_GRAY . ' • ' . $timeAgo . RESET;
        }
    }

    private function renderTasksSection(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BG_DARK . FG_CYAN . BOLD . '⚡ Running Tasks' . RESET;

        foreach ($this->tasks as $index => $task) {
            moveCursor($row + 2 + $index * 2, $col);

            // Task name with status icon
            $icon = match ($task['status']) {
                'running' => '🔄',
                'completed' => '✅',
                'pending' => '⏳',
                default => '•'
            };

            echo BG_DARK . FG_WHITE . $icon . ' ' . $task['name'] . RESET;

            // Progress bar
            moveCursor($row + 2 + $index * 2 + 1, $col + 2);
            $this->renderProgressBar($row + 2 + $index * 2 + 1, $col + 2, 40, $task['progress'],
                $task['status'] === 'completed' ? FG_GREEN : FG_BLUE);
        }
    }

    private function renderBottomStatusBar(): void
    {
        moveCursor($this->terminalHeight, 1);
        echo BG_DARKER;

        // Mode indicator with pulse
        $pulse = $this->frame % 30 < 15 ? '●' : '○';
        echo FG_GREEN . " {$pulse} READY " . RESET . BG_DARKER;

        // Git branch
        echo FG_GRAY . '│ ' . FG_PURPLE . ' main ' . RESET . BG_DARKER;

        // Stats
        $fps = 60;
        $latency = 12 + sin($this->frame / 20) * 3;
        echo FG_GRAY . '│ FPS: ' . FG_WHITE . $fps . RESET . BG_DARKER;
        echo FG_GRAY . ' │ Latency: ' . FG_WHITE . sprintf('%.1fms', $latency) . RESET . BG_DARKER;

        // Right side - shortcuts
        $shortcuts = '[F1] Help │ [F2] Search │ [ESC] Exit';
        moveCursor($this->terminalHeight, $this->terminalWidth - mb_strlen($shortcuts));
        echo BG_DARKER . FG_GRAY . $shortcuts . RESET;

        // Fill remaining
        echo BG_DARKER . str_repeat(' ', 20) . RESET;
    }

    private function renderProgressBar(int $row, int $col, int $width, float $percent, string $color): void
    {
        moveCursor($row, $col);

        $filled = (int) ($percent / 100 * $width);
        $empty = $width - $filled;

        echo BG_DARK;

        // Filled part with gradient effect
        for ($i = 0; $i < $filled; $i++) {
            $char = '█';
            if ($i === $filled - 1 && $percent < 100) {
                // Animated edge
                $chars = ['▏', '▎', '▍', '▌', '▋', '▊', '▉', '█'];
                $subPixel = ($percent / 100 * $width) - $filled;
                $char = $chars[(int) ($subPixel * 8)];
            }
            echo $color . $char;
        }

        // Empty part
        echo FG_GRAY . str_repeat('░', $empty) . RESET;

        // Percentage
        echo BG_DARK . FG_WHITE . sprintf(' %3.0f%%', $percent) . RESET;
    }

    private function renderMiniBar(float $percent, int $width, string $color): string
    {
        $filled = (int) ($percent / 100 * $width);
        $bar = '';

        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                $bar .= $color . '▰' . RESET . BG_DARKER;
            } else {
                $bar .= FG_GRAY . '▱' . RESET . BG_DARKER;
            }
        }

        return $bar;
    }

    private function updateAnimations(): void
    {
        // Smooth animation for all values
        foreach ($this->animations as $key => &$anim) {
            $diff = $anim['target'] - $anim['current'];
            $anim['velocity'] = $anim['velocity'] * 0.8 + $diff * 0.2;
            $anim['current'] += $anim['velocity'];

            // Clamp values
            if ($key === 'cpu' || $key === 'memory') {
                $anim['current'] = max(0, min(100, $anim['current']));
            }
        }
    }

    private function simulateUpdates(): void
    {
        // Update CPU and memory with smooth transitions
        if ($this->frame % 30 === 0) {
            $this->animations['cpu']['target'] = 40 + rand(0, 30);
            $this->animations['memory']['target'] = 55 + rand(0, 25);
        }

        // Add new activity
        if ($this->frame % 100 === 0) {
            $activities = [
                ['type' => 'info', 'message' => 'Cache cleared', 'icon' => '🗑️'],
                ['type' => 'success', 'message' => 'Deployment successful', 'icon' => '🚀'],
                ['type' => 'warning', 'message' => 'High CPU usage detected', 'icon' => '⚠️'],
                ['type' => 'info', 'message' => 'Backup completed', 'icon' => '💾'],
            ];

            $newActivity = $activities[array_rand($activities)];
            $newActivity['time'] = time();
            array_unshift($this->activityFeed, $newActivity);
            $this->activityFeed = array_slice($this->activityFeed, 0, 10);
        }

        // Update task progress
        foreach ($this->tasks as &$task) {
            if ($task['status'] === 'running' && $task['progress'] < 100) {
                $task['progress'] = min(100, $task['progress'] + rand(1, 3));
                if ($task['progress'] >= 100) {
                    $task['status'] = 'completed';
                }
            }
        }

        // Cycle selected sidebar section
        if ($this->frame % 80 === 0) {
            $this->selectedSection = ($this->selectedSection + 1) % count($this->sidebarSections);
        }

        // Toggle sidebar expansion
        if ($this->frame % 120 === 0) {
            $index = rand(0, count($this->sidebarSections) - 1);
            $this->sidebarSections[$index]['expanded'] = ! $this->sidebarSections[$index]['expanded'];
        }
    }

    private function getTimeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }

        return floor($diff / 86400) . 'd ago';
    }
}

// Signal handler for clean exit
function signalHandler($signal)
{
    echo SHOW_CURSOR . CLEAR;
    exit(0);
}

// Register signal handlers
pcntl_signal(SIGINT, 'signalHandler');
pcntl_signal(SIGTERM, 'signalHandler');

// Main execution
$ui = new UltimateTerminalUI;

echo CLEAR . HIDE_CURSOR;

while (true) {
    $ui->render();
    usleep(50000); // 50ms for smooth 20 FPS
    pcntl_signal_dispatch();
}
