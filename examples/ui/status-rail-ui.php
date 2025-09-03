#!/usr/bin/env php
<?php

/**
 * Status Rail UI - Narrow status rail with expandable details
 *
 * Features:
 * - Ultra-narrow status rail (15% width)
 * - Compact indicators and sparklines
 * - Expandable detail panel
 * - Maximum space for main content
 * - Mini activity chart
 */

// ANSI codes
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const ITALIC = "\033[3m";
const UNDERLINE = "\033[4m";
const REVERSE = "\033[7m";

// Terminal
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";
const CLEAR = "\033[2J";
const HOME = "\033[H";
const ALT_ON = "\033[?1049h";
const ALT_OFF = "\033[?1049l";

// Minimal colors
const WHITE = "\033[97m";
const GRAY = "\033[90m";
const BLACK = "\033[30m";
const RED = "\033[31m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const CYAN = "\033[36m";
const MAGENTA = "\033[35m";

// Backgrounds
const BG_BLACK = "\033[40m";
const BG_GRAY = "\033[100m";
const BG_WHITE = "\033[107m";

// Sparkline characters - define as class constant instead
// const SPARK_CHARS = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];

// Compact indicators
const IND_SUCCESS = '●';
const IND_WARNING = '▲';
const IND_ERROR = '✗';
const IND_ACTIVE = '◉';
const IND_PENDING = '○';

class StatusRailUI
{
    // Sparkline characters as class constant
    private const SPARK_CHARS = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];

    private const MAX_HISTORY = 20;

    private int $width;

    private int $height;

    private int $railWidth;

    private bool $railExpanded = false;

    private int $frame = 0;

    // UI State
    private array $activities = [];

    private array $metrics = [];

    private array $sparklineData = [];

    private array $statusItems = [];

    private int $selectedStatus = 0;

    // Performance data for sparkline
    private array $performanceHistory = [];

    private bool $running = false;

    private string $inputBuffer = '';

    // Statistics
    private array $stats = [
        'total_ops' => 0,
        'success' => 0,
        'errors' => 0,
        'avg_time' => 0,
    ];

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->calculateLayout();
        $this->initializeData();

        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        pcntl_signal(SIGWINCH, [$this, 'handleResize']);
    }

    private function setup(): void
    {
        echo ALT_ON . CLEAR . HOME . HIDE_CURSOR;
        system('stty -echo -icanon min 1 time 0 2>/dev/null');
        stream_set_blocking(STDIN, false);
    }

    public function run(): void
    {
        $this->setup();
        $this->running = true;

        while ($this->running) {
            $this->handleInput();
            $this->update();
            $this->render();

            pcntl_signal_dispatch();
            usleep(33333); // ~30 FPS
            $this->frame++;
        }

        $this->cleanup();
    }

    private function cleanup(): void
    {
        echo SHOW_CURSOR . ALT_OFF . RESET;
        system('stty sane');
    }

    private function updateTerminalSize(): void
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;
    }

    private function calculateLayout(): void
    {
        // Rail is narrow by default, expands when needed
        $this->railWidth = $this->railExpanded ? 30 : 18;
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
        $this->calculateLayout();
    }

    private function handleInput(): void
    {
        $input = fread(STDIN, 1024);
        if (! $input) {
            return;
        }

        foreach (mb_str_split($input) as $char) {
            switch ($char) {
                case 'q':
                case 'Q':
                    $this->running = false;
                    break;
                case 'e':
                case 'E':
                    $this->railExpanded = ! $this->railExpanded;
                    $this->calculateLayout();
                    break;
                case "\033": // Escape sequences
                    if (mb_strlen($input) >= 3) {
                        $seq = mb_substr($input, 1, 2);
                        if ($seq === '[A') { // Up
                            $this->selectedStatus = max(0, $this->selectedStatus - 1);
                        } elseif ($seq === '[B') { // Down
                            $this->selectedStatus = min(count($this->statusItems) - 1,
                                $this->selectedStatus + 1);
                        }
                    }
                    break;
                case 'r':
                    $this->addRandomActivity();
                    break;
            }
        }
    }

    private function update(): void
    {
        // Update sparkline data
        if ($this->frame % 10 === 0) {
            $this->updateSparkline();
        }

        // Add activity
        if ($this->frame % 50 === 0) {
            $this->addRandomActivity();
        }

        // Update metrics
        if ($this->frame % 20 === 0) {
            $this->updateMetrics();
        }
    }

    private function render(): void
    {
        echo HOME;

        $this->renderHeader();
        $this->renderMainContent();
        $this->renderStatusRail();
        $this->renderFooter();
    }

    private function renderHeader(): void
    {
        $this->moveTo(1, 1);
        echo BG_BLACK . WHITE;

        $title = ' ▣ Status Rail UI';
        echo BOLD . $title . RESET . BG_BLACK;

        // Stats in center
        $stats = sprintf('Ops: %d | Success: %d | Errors: %d',
            $this->stats['total_ops'],
            $this->stats['success'],
            $this->stats['errors']
        );

        $centerPos = ($this->width - mb_strlen($stats)) / 2;
        $this->moveTo(1, (int) $centerPos);
        echo BG_BLACK . CYAN . $stats . RESET;

        // Time
        $time = date('H:i:s');
        $this->moveTo(1, $this->width - mb_strlen($time));
        echo BG_BLACK . GRAY . $time . RESET;

        // Line
        $this->moveTo(2, 1);
        echo GRAY . str_repeat('─', $this->width) . RESET;
    }

    private function renderMainContent(): void
    {
        $mainWidth = $this->width - $this->railWidth - 1;
        $row = 3;

        // Main content area
        $this->moveTo($row++, 2);
        echo WHITE . BOLD . 'Main Content Area' . RESET;

        $this->moveTo($row++, 2);
        echo GRAY . '(Maximum space for primary content)' . RESET;

        $row++;

        // Activity list
        $this->moveTo($row++, 2);
        echo UNDERLINE . 'Recent Activities' . RESET;
        $row++;

        $maxActivities = $this->height - 8;
        $visibleActivities = array_slice($this->activities, 0, $maxActivities);

        foreach ($visibleActivities as $activity) {
            if ($row >= $this->height - 2) {
                break;
            }

            $this->moveTo($row++, 3);
            $this->renderActivity($activity, $mainWidth - 4);
        }
    }

    private function renderActivity(array $activity, int $maxWidth): void
    {
        $time = date('H:i:s', $activity['time']);
        echo GRAY . "[{$time}] " . RESET;

        // Type indicator
        $indicator = match ($activity['type']) {
            'success' => GREEN . IND_SUCCESS,
            'warning' => YELLOW . IND_WARNING,
            'error' => RED . IND_ERROR,
            default => GRAY . IND_PENDING
        };

        echo $indicator . RESET . ' ';

        // Message
        $message = $activity['message'];
        if (mb_strlen($message) > $maxWidth - 15) {
            $message = mb_substr($message, 0, $maxWidth - 18) . '...';
        }

        echo WHITE . $message . RESET;
    }

    private function renderStatusRail(): void
    {
        $railStart = $this->width - $this->railWidth + 1;

        // Rail background
        for ($row = 3; $row <= $this->height - 2; $row++) {
            $this->moveTo($row, $railStart - 1);
            echo GRAY . '│' . RESET;

            // Clear rail area
            for ($col = $railStart; $col <= $this->width; $col++) {
                $this->moveTo($row, $col);
                echo ' ';
            }
        }

        $row = 3;
        $col = $railStart;

        // Rail header
        $this->moveTo($row++, $col);
        $expandIcon = $this->railExpanded ? '◀' : '▶';
        echo CYAN . $expandIcon . ' Status' . RESET;

        $row++;

        // Sparkline chart
        $this->moveTo($row++, $col);
        echo GRAY . 'Activity:' . RESET;

        $this->moveTo($row++, $col);
        $this->renderSparkline($this->performanceHistory, $this->railWidth - 2);

        $row++;

        // Compact status items
        $this->moveTo($row++, $col);
        echo GRAY . '━' . str_repeat('─', $this->railWidth - 3) . RESET;

        $row++;

        // Status list
        $maxItems = min(count($this->statusItems), $this->height - $row - 3);

        for ($i = 0; $i < $maxItems; $i++) {
            $item = $this->statusItems[$i];
            $isSelected = $i === $this->selectedStatus;

            $this->moveTo($row++, $col);

            if ($isSelected) {
                echo REVERSE;
            }

            $this->renderStatusItem($item, $this->railWidth - 2, $isSelected);

            if ($isSelected) {
                echo RESET;
            }
        }

        // Bottom stats if expanded
        if ($this->railExpanded && $row < $this->height - 3) {
            $row = $this->height - 5;

            $this->moveTo($row++, $col);
            echo GRAY . '━' . str_repeat('─', $this->railWidth - 3) . RESET;

            $row++;
            $this->moveTo($row++, $col);
            echo GRAY . 'Rate: ' . RESET;
            echo $this->getSuccessRateBar(8);

            $this->moveTo($row++, $col);
            echo GRAY . 'Load: ' . RESET;
            echo $this->getLoadBar(8);
        }
    }

    private function renderSparkline(array $data, int $width): void
    {
        if (empty($data)) {
            echo GRAY . str_repeat('─', $width) . RESET;

            return;
        }

        // Normalize data to 0-7 range for spark characters
        $max = max($data) ?: 1;
        $normalized = array_map(fn ($v) => (int) ($v / $max * 7), $data);

        // Take last $width values
        $display = array_slice($normalized, -$width);

        foreach ($display as $value) {
            $char = self::SPARK_CHARS[min(7, max(0, $value))];

            // Color based on value
            if ($value > 5) {
                echo GREEN . $char;
            } elseif ($value > 2) {
                echo YELLOW . $char;
            } else {
                echo CYAN . $char;
            }
        }

        echo RESET;
    }

    private function renderStatusItem(array $item, int $width, bool $selected): void
    {
        // Icon
        $icon = match ($item['status']) {
            'active' => GREEN . IND_ACTIVE,
            'pending' => GRAY . IND_PENDING,
            'error' => RED . IND_ERROR,
            default => WHITE . '·'
        };

        echo $icon . RESET;

        if ($selected) {
            echo REVERSE;
        }

        // Label (compact)
        $label = ' ' . $item['label'];
        if (! $this->railExpanded) {
            // Ultra compact - just show first few chars
            $label = ' ' . mb_substr($item['label'], 0, 3);
        } elseif (mb_strlen($label) > $width - 4) {
            $label = mb_substr($label, 0, $width - 7) . '...';
        }

        echo $label;

        // Value on right if expanded
        if ($this->railExpanded && isset($item['value'])) {
            $valueStr = (string) $item['value'];
            $padding = $width - mb_strlen($label) - mb_strlen($valueStr) - 1;
            if ($padding > 0) {
                echo str_repeat(' ', $padding);
            }
            echo BOLD . $valueStr . RESET;
        }

        // Fill rest of line if selected
        if ($selected) {
            $currentLen = mb_strlen($label) + (isset($item['value']) ? mb_strlen($item['value']) : 0) + 1;
            $padding = $width - $currentLen;
            if ($padding > 0) {
                echo str_repeat(' ', $padding);
            }
        }
    }

    private function getSuccessRateBar(int $width): string
    {
        $rate = $this->stats['total_ops'] > 0
            ? ($this->stats['success'] / $this->stats['total_ops'])
            : 0;

        $filled = (int) ($rate * $width);
        $bar = '';

        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                $bar .= GREEN . '▰';
            } else {
                $bar .= GRAY . '▱';
            }
        }

        return $bar . RESET . sprintf(' %.0f%%', $rate * 100);
    }

    private function getLoadBar(int $width): string
    {
        // Simulate load
        $load = sin($this->frame / 20) * 0.5 + 0.5;
        $filled = (int) ($load * $width);
        $bar = '';

        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                $color = $load > 0.7 ? RED : ($load > 0.4 ? YELLOW : CYAN);
                $bar .= $color . '▰';
            } else {
                $bar .= GRAY . '▱';
            }
        }

        return $bar . RESET . sprintf(' %.0f%%', $load * 100);
    }

    private function renderFooter(): void
    {
        $this->moveTo($this->height - 1, 1);
        echo GRAY . str_repeat('─', $this->width) . RESET;

        $this->moveTo($this->height, 1);

        // Shortcuts
        $shortcuts = '[E]xpand  [↑↓]Select  [R]efresh  [Q]uit';
        echo GRAY . $shortcuts . RESET;

        // Mode indicator
        $mode = $this->railExpanded ? 'EXPANDED' : 'COMPACT';
        $modeText = "Rail: {$mode}";
        $this->moveTo($this->height, $this->width - mb_strlen($modeText));
        echo CYAN . $modeText . RESET;
    }

    private function moveTo(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    private function updateSparkline(): void
    {
        // Add random value to history
        $value = rand(1, 100);
        $this->performanceHistory[] = $value;

        // Keep only last MAX_HISTORY values
        if (count($this->performanceHistory) > self::MAX_HISTORY) {
            array_shift($this->performanceHistory);
        }
    }

    private function updateMetrics(): void
    {
        $this->stats['total_ops']++;

        if (rand(0, 100) > 10) {
            $this->stats['success']++;
        } else {
            $this->stats['errors']++;
        }

        $this->stats['avg_time'] = rand(50, 500);

        // Update status items
        $this->statusItems = [
            ['label' => 'Queue', 'status' => 'active', 'value' => rand(5, 20)],
            ['label' => 'Workers', 'status' => 'active', 'value' => rand(2, 8)],
            ['label' => 'Memory', 'status' => rand(0, 100) > 80 ? 'error' : 'active',
                'value' => rand(60, 95) . '%'],
            ['label' => 'CPU', 'status' => rand(0, 100) > 70 ? 'error' : 'active',
                'value' => rand(20, 80) . '%'],
            ['label' => 'Network', 'status' => 'active', 'value' => rand(1, 100) . 'ms'],
            ['label' => 'Cache', 'status' => 'pending', 'value' => rand(70, 99) . '%'],
        ];
    }

    private function addRandomActivity(): void
    {
        $types = ['success', 'warning', 'error', 'info'];
        $messages = [
            'Task completed successfully',
            'Processing request',
            'Validation passed',
            'Cache updated',
            'Connection established',
            'Data synchronized',
        ];

        array_unshift($this->activities, [
            'time' => time(),
            'type' => $types[array_rand($types)],
            'message' => $messages[array_rand($messages)],
        ]);

        if (count($this->activities) > 100) {
            array_pop($this->activities);
        }
    }

    private function initializeData(): void
    {
        // Initialize with some data
        for ($i = 0; $i < self::MAX_HISTORY; $i++) {
            $this->performanceHistory[] = rand(10, 90);
        }

        // Add initial activities
        for ($i = 0; $i < 10; $i++) {
            $this->addRandomActivity();
        }

        // Initial metrics
        $this->updateMetrics();
    }
}

// Run the UI
$ui = new StatusRailUI;

try {
    $ui->run();
} catch (Exception $e) {
    echo SHOW_CURSOR . ALT_OFF . RESET;
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
