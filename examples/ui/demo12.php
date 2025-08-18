#!/usr/bin/env php
<?php

/**
 * Demo 12: Dashboard Grid Layout
 * Responsive grid system with widgets and live data
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";

// Dashboard colors
const C_WIDGET_BG = "\033[48;5;236m";
const C_HEADER_BG = "\033[48;5;238m";
const C_VALUE = "\033[38;5;117m";
const C_LABEL = "\033[38;5;250m";
const C_INCREASE = "\033[38;5;120m";
const C_DECREASE = "\033[38;5;203m";
const C_NEUTRAL = "\033[38;5;245m";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class DashboardGrid
{
    private int $width;

    private int $height;

    private array $widgets = [];

    private array $data = [];

    private int $frame = 0;

    public function __construct()
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;
        $this->initializeWidgets();
        $this->generateData();
    }

    public function render(): void
    {
        echo CLEAR;

        // Header
        $this->renderHeader();

        // Render each widget
        foreach ($this->widgets as $widget) {
            $this->renderWidget($widget);
        }

        // Update data
        $this->updateData();
        $this->frame++;
    }

    private function initializeWidgets(): void
    {
        // Calculate grid dimensions (4 columns, 3 rows)
        $colWidth = (int) (($this->width - 5) / 4);
        $rowHeight = (int) (($this->height - 4) / 3);

        $this->widgets = [
            // Row 1
            ['type' => 'metric', 'title' => 'Revenue', 'col' => 0, 'row' => 0, 'width' => 1, 'height' => 1],
            ['type' => 'metric', 'title' => 'Users', 'col' => 1, 'row' => 0, 'width' => 1, 'height' => 1],
            ['type' => 'metric', 'title' => 'Orders', 'col' => 2, 'row' => 0, 'width' => 1, 'height' => 1],
            ['type' => 'metric', 'title' => 'Uptime', 'col' => 3, 'row' => 0, 'width' => 1, 'height' => 1],

            // Row 2
            ['type' => 'chart', 'title' => 'Performance', 'col' => 0, 'row' => 1, 'width' => 2, 'height' => 1],
            ['type' => 'list', 'title' => 'Recent Activity', 'col' => 2, 'row' => 1, 'width' => 2, 'height' => 1],

            // Row 3
            ['type' => 'progress', 'title' => 'System Health', 'col' => 0, 'row' => 2, 'width' => 1, 'height' => 1],
            ['type' => 'sparkline', 'title' => 'Network I/O', 'col' => 1, 'row' => 2, 'width' => 2, 'height' => 1],
            ['type' => 'status', 'title' => 'Services', 'col' => 3, 'row' => 2, 'width' => 1, 'height' => 1],
        ];
    }

    private function generateData(): void
    {
        $this->data = [
            'revenue' => ['value' => 125430, 'change' => 12.5, 'trend' => 'up'],
            'users' => ['value' => 8420, 'change' => -2.3, 'trend' => 'down'],
            'orders' => ['value' => 342, 'change' => 5.7, 'trend' => 'up'],
            'uptime' => ['value' => 99.98, 'change' => 0.02, 'trend' => 'up'],
            'performance' => array_map(fn () => rand(20, 100), range(1, 20)),
            'network' => array_map(fn () => rand(10, 90), range(1, 30)),
            'services' => [
                ['name' => 'API', 'status' => 'online', 'latency' => 42],
                ['name' => 'Database', 'status' => 'online', 'latency' => 8],
                ['name' => 'Cache', 'status' => 'warning', 'latency' => 156],
                ['name' => 'Queue', 'status' => 'online', 'latency' => 23],
            ],
            'activities' => [
                ['time' => '12:34', 'action' => 'User registration', 'status' => 'success'],
                ['time' => '12:33', 'action' => 'Order placed #1234', 'status' => 'success'],
                ['time' => '12:31', 'action' => 'Payment processed', 'status' => 'success'],
                ['time' => '12:30', 'action' => 'Cache cleared', 'status' => 'warning'],
                ['time' => '12:28', 'action' => 'Backup completed', 'status' => 'success'],
            ],
        ];
    }

    private function renderHeader(): void
    {
        moveCursor(1, 1);
        echo C_HEADER_BG . str_repeat(' ', $this->width) . RESET;
        moveCursor(1, 2);
        echo C_HEADER_BG . BOLD . C_LABEL . ' ⊞ DASHBOARD' . RESET;
        echo C_HEADER_BG . C_NEUTRAL . ' │ Real-time Metrics │ ' . date('H:i:s') . str_repeat(' ', $this->width - 40) . RESET;
    }

    private function renderWidget(array $widget): void
    {
        $gridWidth = (int) (($this->width - 5) / 4);
        $gridHeight = (int) (($this->height - 4) / 3);

        $x = 2 + ($widget['col'] * ($gridWidth + 1));
        $y = 3 + ($widget['row'] * ($gridHeight + 1));
        $w = ($widget['width'] * $gridWidth) + ($widget['width'] - 1);
        $h = ($widget['height'] * $gridHeight) + ($widget['height'] - 1);

        // Draw widget border
        $this->drawWidgetBox($y, $x, $w, $h, $widget['title']);

        // Render widget content
        switch ($widget['type']) {
            case 'metric':
                $this->renderMetricWidget($y + 2, $x + 2, $w - 4, $h - 3, $widget['title']);
                break;
            case 'chart':
                $this->renderChartWidget($y + 2, $x + 2, $w - 4, $h - 3);
                break;
            case 'list':
                $this->renderListWidget($y + 2, $x + 2, $w - 4, $h - 3);
                break;
            case 'progress':
                $this->renderProgressWidget($y + 2, $x + 2, $w - 4, $h - 3);
                break;
            case 'sparkline':
                $this->renderSparklineWidget($y + 2, $x + 2, $w - 4, $h - 3);
                break;
            case 'status':
                $this->renderStatusWidget($y + 2, $x + 2, $w - 4, $h - 3);
                break;
        }
    }

    private function drawWidgetBox(int $y, int $x, int $w, int $h, string $title): void
    {
        // Header
        moveCursor($y, $x);
        echo C_WIDGET_BG . '╭' . str_repeat('─', $w - 2) . '╮' . RESET;

        moveCursor($y + 1, $x);
        echo C_WIDGET_BG . '│ ' . BOLD . C_LABEL . mb_str_pad($title, $w - 4) . RESET . C_WIDGET_BG . ' │' . RESET;

        moveCursor($y + 2, $x);
        echo C_WIDGET_BG . '├' . str_repeat('─', $w - 2) . '┤' . RESET;

        // Body
        for ($i = 3; $i < $h - 1; $i++) {
            moveCursor($y + $i, $x);
            echo C_WIDGET_BG . '│' . str_repeat(' ', $w - 2) . '│' . RESET;
        }

        // Footer
        moveCursor($y + $h - 1, $x);
        echo C_WIDGET_BG . '╰' . str_repeat('─', $w - 2) . '╯' . RESET;
    }

    private function renderMetricWidget(int $y, int $x, int $w, int $h, string $metric): void
    {
        $key = mb_strtolower($metric);
        $data = $this->data[$key] ?? ['value' => 0, 'change' => 0, 'trend' => 'neutral'];

        // Value
        moveCursor($y, $x);
        $value = is_float($data['value']) ?
            number_format($data['value'], 2) . '%' :
            number_format($data['value']);
        echo C_VALUE . BOLD . $value . RESET;

        // Change indicator
        moveCursor($y + 1, $x);
        $changeColor = $data['trend'] === 'up' ? C_INCREASE : C_DECREASE;
        $arrow = $data['trend'] === 'up' ? '↑' : '↓';
        echo $changeColor . $arrow . ' ' . abs($data['change']) . '%' . RESET;

        // Mini sparkline
        if ($h > 3) {
            moveCursor($y + 3, $x);
            $spark = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
            for ($i = 0; $i < min($w, 10); $i++) {
                $val = sin($this->frame / 10 + $i) * 3.5 + 3.5;
                echo C_VALUE . $spark[(int) $val] . RESET;
            }
        }
    }

    private function renderChartWidget(int $y, int $x, int $w, int $h): void
    {
        $data = $this->data['performance'];
        $maxVal = max($data);

        for ($row = 0; $row < min($h, 8); $row++) {
            moveCursor($y + $row, $x);
            $threshold = $maxVal * (1 - $row / 8);

            foreach ($data as $i => $val) {
                if ($i >= $w) {
                    break;
                }

                if ($val >= $threshold) {
                    echo C_VALUE . '█' . RESET;
                } else {
                    echo C_NEUTRAL . '·' . RESET;
                }
            }
        }
    }

    private function renderListWidget(int $y, int $x, int $w, int $h): void
    {
        foreach ($this->data['activities'] as $i => $activity) {
            if ($i >= $h) {
                break;
            }

            moveCursor($y + $i, $x);
            echo C_NEUTRAL . $activity['time'] . RESET . ' ';

            $status = $activity['status'] === 'success' ? C_INCREASE . '✓' : C_DECREASE . '⚠';
            echo $status . RESET . ' ';

            $text = mb_substr($activity['action'], 0, $w - 10);
            echo C_LABEL . $text . RESET;
        }
    }

    private function renderProgressWidget(int $y, int $x, int $w, int $h): void
    {
        $items = [
            ['label' => 'CPU', 'value' => 45 + sin($this->frame / 20) * 20],
            ['label' => 'Memory', 'value' => 67 + cos($this->frame / 25) * 15],
            ['label' => 'Disk', 'value' => 82],
            ['label' => 'Network', 'value' => 23 + sin($this->frame / 15) * 10],
        ];

        foreach ($items as $i => $item) {
            if ($i * 2 >= $h) {
                break;
            }

            moveCursor($y + ($i * 2), $x);
            echo C_LABEL . mb_str_pad($item['label'], 8) . RESET;

            $barWidth = min($w - 15, 20);
            $filled = (int) (($item['value'] / 100) * $barWidth);

            for ($j = 0; $j < $barWidth; $j++) {
                if ($j < $filled) {
                    $color = $item['value'] > 80 ? C_DECREASE : ($item['value'] > 60 ? C_NEUTRAL : C_INCREASE);
                    echo $color . '█' . RESET;
                } else {
                    echo DIM . '░' . RESET;
                }
            }

            echo sprintf(' %3d%%', (int) $item['value']);
        }
    }

    private function renderSparklineWidget(int $y, int $x, int $w, int $h): void
    {
        $data = $this->data['network'];
        $spark = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];

        // Render sparkline
        for ($row = 0; $row < min($h - 2, 3); $row++) {
            moveCursor($y + $row, $x);

            foreach ($data as $i => $val) {
                if ($i >= $w) {
                    break;
                }

                $normalized = ($val / 100) * 7;
                $char = $spark[(int) $normalized];

                $color = $val > 70 ? C_DECREASE : ($val > 40 ? C_NEUTRAL : C_INCREASE);
                echo $color . $char . RESET;
            }
        }

        // Stats
        moveCursor($y + $h - 2, $x);
        echo C_LABEL . 'In: ' . C_INCREASE . '↑ 42 MB/s' . RESET;
        moveCursor($y + $h - 1, $x);
        echo C_LABEL . 'Out: ' . C_VALUE . '↓ 18 MB/s' . RESET;
    }

    private function renderStatusWidget(int $y, int $x, int $w, int $h): void
    {
        foreach ($this->data['services'] as $i => $service) {
            if ($i >= $h) {
                break;
            }

            moveCursor($y + $i, $x);

            $statusIcon = match ($service['status']) {
                'online' => C_INCREASE . '●',
                'warning' => C_NEUTRAL . '◐',
                'offline' => C_DECREASE . '○',
                default => '·'
            };

            echo $statusIcon . RESET . ' ';
            echo C_LABEL . mb_str_pad($service['name'], 10) . RESET;
            echo C_NEUTRAL . $service['latency'] . 'ms' . RESET;
        }
    }

    private function updateData(): void
    {
        // Simulate data updates
        if ($this->frame % 10 === 0) {
            $this->data['revenue']['value'] += rand(-1000, 2000);
            $this->data['users']['value'] += rand(-10, 20);
            $this->data['orders']['value'] += rand(-5, 10);

            // Shift performance data
            array_shift($this->data['performance']);
            $this->data['performance'][] = rand(20, 100);

            // Shift network data
            array_shift($this->data['network']);
            $this->data['network'][] = rand(10, 90);
        }
    }
}

// Main execution
$dashboard = new DashboardGrid;

while (true) {
    $dashboard->render();
    usleep(100000); // 100ms refresh
}
