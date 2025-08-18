#!/usr/bin/env php
<?php

/**
 * Demo 15: Terminal Charts and Graphs
 * Line charts, bar charts, pie charts, and real-time data visualization
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";

// Chart colors
const C_AXIS = "\033[38;5;240m";
const C_DATA1 = "\033[38;5;117m";
const C_DATA2 = "\033[38;5;221m";
const C_DATA3 = "\033[38;5;203m";
const C_DATA4 = "\033[38;5;120m";
const C_LABEL = "\033[38;5;250m";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class ChartRenderer
{
    private int $frame = 0;

    private array $lineData = [];

    private array $barData = [];

    public function __construct()
    {
        $this->generateInitialData();
    }

    public function render(): void
    {
        echo CLEAR;

        // Title
        moveCursor(1, 2);
        echo BOLD . 'Terminal Charts & Graphs Demo' . RESET;

        // Render different chart types
        $this->renderLineChart(3, 2, 50, 15);
        $this->renderBarChart(3, 55, 30, 15);
        $this->renderPieChart(3, 90, 25, 15);
        $this->renderSparklines(20, 2);
        $this->renderHeatmap(25, 2);
        $this->renderGauges(20, 55);

        // Update data
        $this->updateData();
        $this->frame++;
    }

    private function generateInitialData(): void
    {
        // Generate sine wave data for line chart
        for ($i = 0; $i < 50; $i++) {
            $this->lineData[] = [
                'series1' => 50 + sin($i / 5) * 30 + rand(-5, 5),
                'series2' => 50 + cos($i / 5) * 25 + rand(-5, 5),
            ];
        }

        // Bar chart data
        $this->barData = [
            'Q1' => ['sales' => 120, 'costs' => 80, 'profit' => 40],
            'Q2' => ['sales' => 150, 'costs' => 90, 'profit' => 60],
            'Q3' => ['sales' => 180, 'costs' => 100, 'profit' => 80],
            'Q4' => ['sales' => 160, 'costs' => 95, 'profit' => 65],
        ];
    }

    private function renderLineChart(int $row, int $col, int $width, int $height): void
    {
        // Title
        moveCursor($row, $col + (int) ($width / 2) - 5);
        echo BOLD . 'Line Chart' . RESET;

        // Y-axis
        for ($y = 0; $y < $height; $y++) {
            moveCursor($row + $y + 2, $col);

            if ($y === 0) {
                echo C_AXIS . '100┤' . RESET;
            } elseif ($y === $height / 2) {
                echo C_AXIS . ' 50┤' . RESET;
            } elseif ($y === $height - 1) {
                echo C_AXIS . '  0┴' . RESET;
            } else {
                echo C_AXIS . '   │' . RESET;
            }
        }

        // X-axis
        moveCursor($row + $height + 1, $col + 4);
        echo C_AXIS . str_repeat('─', $width - 4) . RESET;

        // Plot data
        $dataPoints = array_slice($this->lineData, -($width - 5));

        foreach ($dataPoints as $i => $point) {
            $x = $col + 5 + $i;

            // Series 1
            $y1 = $row + 2 + (int) ((100 - $point['series1']) / 100 * ($height - 1));
            moveCursor($y1, $x);
            echo C_DATA1 . '●' . RESET;

            // Series 2
            $y2 = $row + 2 + (int) ((100 - $point['series2']) / 100 * ($height - 1));
            moveCursor($y2, $x);
            echo C_DATA2 . '◆' . RESET;

            // Connect points with lines (simplified)
            if ($i > 0) {
                $prevY1 = $row + 2 + (int) ((100 - $dataPoints[$i - 1]['series1']) / 100 * ($height - 1));
                $this->drawLine($prevY1, $x - 1, $y1, $x, C_DATA1);

                $prevY2 = $row + 2 + (int) ((100 - $dataPoints[$i - 1]['series2']) / 100 * ($height - 1));
                $this->drawLine($prevY2, $x - 1, $y2, $x, C_DATA2);
            }
        }

        // Legend
        moveCursor($row + $height + 3, $col + 5);
        echo C_DATA1 . '● Series 1' . RESET . '  ' . C_DATA2 . '◆ Series 2' . RESET;
    }

    private function renderBarChart(int $row, int $col, int $width, int $height): void
    {
        // Title
        moveCursor($row, $col + (int) ($width / 2) - 5);
        echo BOLD . 'Bar Chart' . RESET;

        // Y-axis
        for ($y = 0; $y < $height; $y++) {
            moveCursor($row + $y + 2, $col);
            echo C_AXIS . '│' . RESET;
        }

        // X-axis
        moveCursor($row + $height + 1, $col);
        echo C_AXIS . '└' . str_repeat('─', $width - 1) . RESET;

        // Bars
        $barWidth = 5;
        $spacing = 2;
        $x = $col + 2;

        foreach ($this->barData as $label => $values) {
            // Sales bar
            $barHeight1 = (int) (($values['sales'] / 200) * $height);
            for ($y = 0; $y < $barHeight1; $y++) {
                moveCursor($row + $height - $y, $x);
                echo C_DATA1 . '█' . RESET;
            }

            // Costs bar
            $barHeight2 = (int) (($values['costs'] / 200) * $height);
            for ($y = 0; $y < $barHeight2; $y++) {
                moveCursor($row + $height - $y, $x + 1);
                echo C_DATA3 . '█' . RESET;
            }

            // Profit bar
            $barHeight3 = (int) (($values['profit'] / 200) * $height);
            for ($y = 0; $y < $barHeight3; $y++) {
                moveCursor($row + $height - $y, $x + 2);
                echo C_DATA4 . '█' . RESET;
            }

            // Label
            moveCursor($row + $height + 2, $x);
            echo C_LABEL . $label . RESET;

            $x += $barWidth + $spacing;
        }

        // Legend
        moveCursor($row + $height + 4, $col + 2);
        echo C_DATA1 . '█ Sales' . RESET . ' ';
        echo C_DATA3 . '█ Costs' . RESET . ' ';
        echo C_DATA4 . '█ Profit' . RESET;
    }

    private function renderPieChart(int $row, int $col, int $width, int $height): void
    {
        // Title
        moveCursor($row, $col + (int) ($width / 2) - 5);
        echo BOLD . 'Pie Chart' . RESET;

        $data = [
            ['label' => 'Product A', 'value' => 35, 'color' => C_DATA1],
            ['label' => 'Product B', 'value' => 25, 'color' => C_DATA2],
            ['label' => 'Product C', 'value' => 20, 'color' => C_DATA3],
            ['label' => 'Product D', 'value' => 20, 'color' => C_DATA4],
        ];

        // Simple ASCII pie chart
        $centerY = $row + (int) ($height / 2);
        $centerX = $col + (int) ($width / 2);
        $radius = min($width, $height) / 3;

        // Draw circle segments
        $chars = ['◴', '◷', '◶', '◵'];
        $segments = ['╱', '│', '╲', '─'];

        for ($angle = 0; $angle < 360; $angle += 10) {
            $rad = deg2rad($angle);
            $y = $centerY + sin($rad) * $radius;
            $x = $centerX + cos($rad) * $radius * 2; // Aspect ratio correction

            moveCursor((int) $y, (int) $x);

            // Determine which segment
            $segmentIndex = (int) ($angle / 90);
            echo $data[$segmentIndex]['color'] . '●' . RESET;
        }

        // Center
        moveCursor($centerY, $centerX - 2);
        echo BOLD . '100%' . RESET;

        // Legend
        $legendRow = $row + $height + 2;
        foreach ($data as $i => $item) {
            moveCursor($legendRow + $i, $col);
            echo $item['color'] . '■' . RESET . ' ';
            echo C_LABEL . $item['label'] . ': ' . $item['value'] . '%' . RESET;
        }
    }

    private function renderSparklines(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BOLD . 'Sparklines' . RESET;

        $sparkChars = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];

        $datasets = [
            'CPU' => array_map(fn () => rand(0, 100), range(1, 30)),
            'Memory' => array_map(fn () => rand(40, 90), range(1, 30)),
            'Network' => array_map(fn () => rand(10, 70), range(1, 30)),
        ];

        $sparkRow = $row + 2;
        foreach ($datasets as $label => $data) {
            moveCursor($sparkRow, $col);
            echo C_LABEL . mb_str_pad($label, 8) . RESET;

            foreach ($data as $value) {
                $index = min(7, (int) ($value / 100 * 8));
                echo C_DATA1 . $sparkChars[$index] . RESET;
            }

            echo ' ' . C_LABEL . sprintf('%3d', end($data)) . '%' . RESET;
            $sparkRow++;
        }
    }

    private function renderHeatmap(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BOLD . 'Heatmap' . RESET;

        $heatChars = [' ', '░', '▒', '▓', '█'];
        $heatColors = [
            "\033[38;5;234m",
            "\033[38;5;22m",
            "\033[38;5;28m",
            "\033[38;5;34m",
            "\033[38;5;40m",
            "\033[38;5;46m",
            "\033[38;5;82m",
            "\033[38;5;118m",
            "\033[38;5;154m",
            "\033[38;5;190m",
            "\033[38;5;226m",
            "\033[38;5;220m",
            "\033[38;5;214m",
            "\033[38;5;208m",
            "\033[38;5;202m",
            "\033[38;5;196m",
        ];

        // Generate heatmap data
        for ($y = 0; $y < 8; $y++) {
            moveCursor($row + 2 + $y, $col);

            for ($x = 0; $x < 40; $x++) {
                $value = 50 + sin(($x + $this->frame) / 5) * 30 + cos(($y + $this->frame) / 3) * 20;
                $colorIndex = min(15, max(0, (int) ($value / 100 * 16)));

                echo $heatColors[$colorIndex] . '█' . RESET;
            }
        }
    }

    private function renderGauges(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BOLD . 'Gauges' . RESET;

        $gauges = [
            ['label' => 'Performance', 'value' => 75 + sin($this->frame / 10) * 20],
            ['label' => 'Efficiency', 'value' => 85 + cos($this->frame / 12) * 15],
            ['label' => 'Utilization', 'value' => 60 + sin($this->frame / 8) * 25],
        ];

        $gaugeCol = $col;
        foreach ($gauges as $gauge) {
            $this->renderSingleGauge($row + 2, $gaugeCol, $gauge['label'], $gauge['value']);
            $gaugeCol += 20;
        }
    }

    private function renderSingleGauge(int $row, int $col, string $label, float $value): void
    {
        // Gauge arc
        $chars = ['╱', '─', '╲'];
        $positions = [
            [-1, -2], [-1, 0], [-1, 2],
            [0, -3], [0, 3],
            [1, -2], [1, 0], [1, 2],
        ];

        foreach ($positions as $pos) {
            moveCursor($row + $pos[0] + 2, $col + $pos[1] + 8);

            $color = $value > 80 ? C_DATA4 : ($value > 50 ? C_DATA2 : C_DATA3);
            echo $color . '●' . RESET;
        }

        // Value
        moveCursor($row + 2, $col + 6);
        echo BOLD . sprintf('%3.0f%%', $value) . RESET;

        // Label
        moveCursor($row + 4, $col + 4);
        echo C_LABEL . $label . RESET;
    }

    private function drawLine(int $y1, int $x1, int $y2, int $x2, string $color): void
    {
        // Simplified line drawing
        if ($y1 === $y2) {
            moveCursor($y1, min($x1, $x2));
            echo $color . '─' . RESET;
        } elseif ($y1 < $y2) {
            moveCursor($y1, $x1);
            echo $color . '╲' . RESET;
        } else {
            moveCursor($y2, $x1);
            echo $color . '╱' . RESET;
        }
    }

    private function updateData(): void
    {
        // Shift line chart data
        array_shift($this->lineData);
        $this->lineData[] = [
            'series1' => 50 + sin($this->frame / 5) * 30 + rand(-5, 5),
            'series2' => 50 + cos($this->frame / 5) * 25 + rand(-5, 5),
        ];

        // Update bar chart data
        foreach ($this->barData as &$quarter) {
            $quarter['sales'] = max(50, min(200, $quarter['sales'] + rand(-10, 10)));
            $quarter['costs'] = max(30, min(150, $quarter['costs'] + rand(-5, 5)));
            $quarter['profit'] = $quarter['sales'] - $quarter['costs'];
        }
    }
}

// Main execution
$charts = new ChartRenderer;

while (true) {
    $charts->render();
    usleep(200000); // 200ms refresh for smoother animations
}
