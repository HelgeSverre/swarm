#!/usr/bin/env php
<?php

/**
 * Demo 10: Full Featured Sleek Terminal UI
 * Combines all improvements into one comprehensive demo
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const ITALIC = "\033[3m";
const UNDERLINE = "\033[4m";
const REVERSE = "\033[7m";
const CLEAR = "\033[2J\033[H";
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";

// Tokyo Night inspired palette
const TN_BG = "\033[48;5;234m";
const TN_BG_DARK = "\033[48;5;232m";
const TN_BG_HIGHLIGHT = "\033[48;5;236m";
const TN_FG = "\033[38;5;251m";
const TN_BLUE = "\033[38;5;111m";
const TN_CYAN = "\033[38;5;87m";
const TN_GREEN = "\033[38;5;115m";
const TN_MAGENTA = "\033[38;5;176m";
const TN_RED = "\033[38;5;203m";
const TN_YELLOW = "\033[38;5;221m";
const TN_ORANGE = "\033[38;5;215m";
const TN_GRAY = "\033[38;5;245m";

// Box characters
const BOX_ROUND = ['╭', '─', '╮', '│', '╰', '╯'];

// Spinners
const SPINNERS = [
    'dots' => ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
    'pulse' => ['⣾', '⣽', '⣻', '⢿', '⡿', '⣟', '⣯', '⣷'],
];

class FullFeaturedUI
{
    private int $width;

    private int $height;

    private int $mainWidth;

    private int $sidebarWidth;

    private int $frame = 0;

    private array $tasks = [];

    private array $activities = [];

    private int $selectedTask = 0;

    private float $cpuUsage = 0;

    private float $memUsage = 0;

    public function __construct()
    {
        $this->updateDimensions();
        $this->initializeData();
    }

    public function render(): void
    {
        // Clear and setup
        echo CLEAR . HIDE_CURSOR;

        // Background fill
        $this->fillBackground();

        // Main components
        $this->renderStatusBar();
        $this->renderMainArea();
        $this->renderSidebar();
        $this->renderFooter();

        // Update animation frame
        $this->frame++;
        $this->updateMetrics();
    }

    private function updateDimensions(): void
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;

        // Golden ratio split
        $this->mainWidth = (int) ($this->width * 0.618);
        $this->sidebarWidth = $this->width - $this->mainWidth - 1;
    }

    private function initializeData(): void
    {
        $this->tasks = [
            ['id' => 1, 'name' => 'Initialize project structure', 'status' => 'completed', 'progress' => 100],
            ['id' => 2, 'name' => 'Implement core features', 'status' => 'running', 'progress' => 75],
            ['id' => 3, 'name' => 'Write unit tests', 'status' => 'running', 'progress' => 45],
            ['id' => 4, 'name' => 'Setup CI/CD pipeline', 'status' => 'pending', 'progress' => 0],
            ['id' => 5, 'name' => 'Deploy to production', 'status' => 'pending', 'progress' => 0],
        ];

        $this->activities = [
            ['type' => 'command', 'content' => 'git commit -m "feat: add terminal UI"', 'time' => time() - 120],
            ['type' => 'success', 'content' => 'All tests passed (42/42)', 'time' => time() - 60],
            ['type' => 'tool', 'content' => 'WriteFile → src/UI/Terminal.php', 'time' => time() - 30],
            ['type' => 'processing', 'content' => 'Analyzing code structure...', 'time' => time()],
        ];
    }

    private function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    private function fillBackground(): void
    {
        for ($row = 1; $row <= $this->height; $row++) {
            $this->moveCursor($row, 1);
            echo TN_BG . str_repeat(' ', $this->width) . RESET;
        }
    }

    private function renderStatusBar(): void
    {
        // Gradient status bar
        $this->moveCursor(1, 1);
        echo TN_BG_DARK . str_repeat(' ', $this->width) . RESET;

        $this->moveCursor(1, 2);

        // Logo and status
        $spinner = SPINNERS['pulse'][$this->frame % count(SPINNERS['pulse'])];
        echo TN_BG_DARK . TN_MAGENTA . '⟡ ' . TN_FG . BOLD . 'SWARM' . RESET;
        echo TN_BG_DARK . TN_GRAY . ' │ ' . RESET;
        echo TN_BG_DARK . TN_CYAN . $spinner . ' Processing' . RESET;
        echo TN_BG_DARK . TN_GRAY . ' │ ' . RESET;

        // Current task
        $currentTask = $this->tasks[1]['name'] ?? 'Ready';
        echo TN_BG_DARK . TN_GREEN . '● ' . TN_FG . mb_substr($currentTask, 0, 30) . RESET;

        // Right-aligned metrics
        $metrics = sprintf(
            'CPU: %.1f%% │ MEM: %.1f%% │ %s',
            $this->cpuUsage,
            $this->memUsage,
            date('H:i:s')
        );

        $this->moveCursor(1, $this->width - mb_strlen($metrics) - 2);
        echo TN_BG_DARK . TN_YELLOW . $metrics . RESET;

        // Progress bar (line 2)
        $this->renderProgressBar();
    }

    private function renderProgressBar(): void
    {
        $this->moveCursor(2, 1);

        $overallProgress = 45; // Calculate from tasks
        $barWidth = $this->width;

        for ($i = 0; $i < $barWidth; $i++) {
            $percent = ($i / $barWidth) * 100;

            if ($percent < $overallProgress) {
                // Gradient effect
                $intensity = 232 + min(8, (int) ($i / 10));
                echo "\033[48;5;{$intensity}m ";
            } else {
                echo TN_BG_DARK . ' ';
            }
        }
        echo RESET;
    }

    private function renderMainArea(): void
    {
        // Draw border
        $this->drawBox(3, 1, $this->mainWidth, $this->height - 4, 'Activity Feed');

        // Render activities with relative times
        $row = 5;
        foreach ($this->activities as $activity) {
            if ($row > $this->height - 8) {
                break;
            }

            $this->moveCursor($row, 3);

            // Time
            $relTime = $this->getRelativeTime($activity['time']);
            echo TN_GRAY . '[' . $relTime . ']' . RESET;

            // Icon and content
            $this->moveCursor($row, 15);
            [$icon, $color] = $this->getActivityStyle($activity['type']);
            echo $color . $icon . RESET . ' ' . TN_FG . $activity['content'] . RESET;

            $row++;
        }

        // Code preview section
        $this->renderCodePreview($row + 1);
    }

    private function renderCodePreview(int $startRow): void
    {
        if ($startRow > $this->height - 10) {
            return;
        }

        $this->moveCursor($startRow, 3);
        echo TN_YELLOW . '// Recent changes' . RESET;

        $code = [
            'public function ' . TN_BLUE . 'renderUI' . TN_FG . '(): void {',
            '    $this->' . TN_CYAN . 'clearScreen' . TN_FG . '();',
            '    $this->' . TN_CYAN . 'drawComponents' . TN_FG . '();',
            '    ' . TN_MAGENTA . 'return ' . TN_GREEN . 'true' . TN_FG . ';',
            '}',
        ];

        foreach ($code as $i => $line) {
            if ($startRow + $i + 1 > $this->height - 6) {
                break;
            }
            $this->moveCursor($startRow + $i + 1, 3);
            echo TN_BG_HIGHLIGHT . TN_GRAY . sprintf('%3d', $i + 1) . RESET;
            echo TN_BG . ' ' . $line . RESET;
        }
    }

    private function renderSidebar(): void
    {
        $col = $this->mainWidth + 2;

        // Draw border
        $this->drawBox(3, $col, $this->sidebarWidth, $this->height - 4, 'Tasks & Context');

        // Task list with tree structure
        $row = 5;
        $this->moveCursor($row++, $col + 2);
        echo TN_CYAN . BOLD . '▼ Tasks' . RESET . ' ' . TN_GREEN . count($this->tasks) . ' items' . RESET;

        foreach ($this->tasks as $i => $task) {
            if ($row > 15) {
                break;
            }

            $this->moveCursor($row++, $col + 2);

            // Tree structure
            $isLast = ($i === count($this->tasks) - 1);
            echo TN_GRAY . ($isLast ? '└─' : '├─') . RESET;

            // Status icon
            $icon = match ($task['status']) {
                'completed' => TN_GREEN . '✓',
                'running' => TN_YELLOW . '▶',
                'pending' => TN_GRAY . '○',
                default => ' '
            };

            echo ' ' . $icon . RESET . ' ';
            echo $i === $this->selectedTask ? REVERSE : '';
            echo mb_substr($task['name'], 0, $this->sidebarWidth - 10);
            echo RESET;

            // Progress bar for running tasks
            if ($task['status'] === 'running') {
                $this->moveCursor($row++, $col + 4);
                echo TN_GRAY . ($isLast ? '  ' : '│ ') . RESET;
                echo ' ' . $this->miniProgressBar($task['progress'], 15);
            }
        }

        // System metrics
        $this->renderMetrics($row + 2, $col);
    }

    private function renderMetrics(int $row, int $col): void
    {
        if ($row > $this->height - 8) {
            return;
        }

        $this->moveCursor($row++, $col + 2);
        echo TN_MAGENTA . BOLD . '▼ Metrics' . RESET;

        $metrics = [
            ['label' => 'CPU', 'value' => $this->cpuUsage, 'color' => TN_CYAN],
            ['label' => 'Memory', 'value' => $this->memUsage, 'color' => TN_YELLOW],
            ['label' => 'Disk', 'value' => 35.2, 'color' => TN_GREEN],
        ];

        foreach ($metrics as $metric) {
            if ($row > $this->height - 5) {
                break;
            }

            $this->moveCursor($row++, $col + 2);
            echo TN_FG . sprintf('%-7s', $metric['label']) . RESET;
            echo $this->miniProgressBar($metric['value'], 12);
            echo ' ' . $metric['color'] . sprintf('%5.1f%%', $metric['value']) . RESET;
        }
    }

    private function renderFooter(): void
    {
        $this->moveCursor($this->height, 1);
        echo TN_BG_DARK . ' ';
        echo TN_GRAY . 'Tab: Navigate │ Enter: Select │ ';
        echo TN_CYAN . '^S: Save │ ';
        echo TN_YELLOW . '^R: Refresh │ ';
        echo TN_RED . '^C: Exit';
        echo str_repeat(' ', $this->width - 50) . RESET;
    }

    private function drawBox(int $row, int $col, int $width, int $height, string $title = ''): void
    {
        // Top border
        $this->moveCursor($row, $col);
        echo TN_GRAY . BOX_ROUND[0];

        if ($title) {
            echo '─ ' . TN_CYAN . $title . TN_GRAY . ' ';
            echo str_repeat(BOX_ROUND[1], $width - mb_strlen($title) - 6);
        } else {
            echo str_repeat(BOX_ROUND[1], $width - 2);
        }
        echo BOX_ROUND[2] . RESET;

        // Sides
        for ($i = 1; $i < $height - 1; $i++) {
            $this->moveCursor($row + $i, $col);
            echo TN_GRAY . BOX_ROUND[3] . RESET;

            $this->moveCursor($row + $i, $col + $width - 1);
            echo TN_GRAY . BOX_ROUND[3] . RESET;
        }

        // Bottom border
        $this->moveCursor($row + $height - 1, $col);
        echo TN_GRAY . BOX_ROUND[4] . str_repeat(BOX_ROUND[1], $width - 2) . BOX_ROUND[5] . RESET;
    }

    private function miniProgressBar(float $percent, int $width): string
    {
        $filled = (int) (($percent / 100) * $width);
        $bar = '';

        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                $bar .= TN_GREEN . '█' . RESET;
            } else {
                $bar .= TN_GRAY . '░' . RESET;
            }
        }

        return $bar;
    }

    private function getRelativeTime(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'now';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . 'm';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . 'h';
        }

        return floor($diff / 86400) . 'd';
    }

    private function getActivityStyle(string $type): array
    {
        return match ($type) {
            'command' => ['$', TN_BLUE],
            'success' => ['✓', TN_GREEN],
            'tool' => ['>', TN_CYAN],
            'processing' => ['◐', TN_YELLOW],
            'error' => ['✗', TN_RED],
            default => ['•', TN_GRAY]
        };
    }

    private function updateMetrics(): void
    {
        // Simulate changing metrics
        $this->cpuUsage = 20 + sin($this->frame / 10) * 15;
        $this->memUsage = 45 + cos($this->frame / 15) * 10;

        // Cycle selected task
        if ($this->frame % 30 === 0) {
            $this->selectedTask = ($this->selectedTask + 1) % count($this->tasks);
        }

        // Update task progress
        foreach ($this->tasks as &$task) {
            if ($task['status'] === 'running' && $task['progress'] < 100) {
                $task['progress'] = min(100, $task['progress'] + 0.5);
            }
        }
    }
}

// Main execution
$ui = new FullFeaturedUI;

echo CLEAR . HIDE_CURSOR;

// Animation loop
while (true) {
    $ui->render();
    usleep(100000); // 100ms refresh rate
}

// Cleanup (won't reach in demo)
echo SHOW_CURSOR . CLEAR;
