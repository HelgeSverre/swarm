#!/usr/bin/env php
<?php

/**
 * Demo 5: Animated Progress Indicators
 * Shows various progress bars, spinners, and loading animations
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";

// Colors
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const CYAN = "\033[36m";
const MAGENTA = "\033[35m";
const RED = "\033[31m";
const WHITE = "\033[37m";

// 256 color gradients
function gradient(int $step): string
{
    // Green to yellow to red gradient
    if ($step < 50) {
        return "\033[38;5;" . (46 + floor($step / 10)) . 'm';
    } elseif ($step < 80) {
        return "\033[38;5;" . (226 + floor(($step - 50) / 10)) . 'm';
    }

    return "\033[38;5;" . (196 + floor(($step - 80) / 5)) . 'm';
}

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class ProgressAnimations
{
    private array $spinners = [
        'dots' => ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
        'dots2' => ['⣾', '⣽', '⣻', '⢿', '⡿', '⣟', '⣯', '⣷'],
        'line' => ['―', '\\', '|', '/'],
        'circle' => ['◐', '◓', '◑', '◒'],
        'square' => ['◰', '◳', '◲', '◱'],
        'triangle' => ['◢', '◣', '◤', '◥'],
        'arrow' => ['←', '↖', '↑', '↗', '→', '↘', '↓', '↙'],
        'pulse' => ['⎯', '—', '−', '⁃', '‐', '⁃', '−', '—', '⎯'],
        'bounce' => ['⠁', '⠂', '⠄', '⡀', '⢀', '⠠', '⠐', '⠈'],
        'wave' => ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█', '▇', '▆', '▅', '▄', '▃', '▂'],
    ];

    public function smoothProgressBar(float $percent, int $width = 30, string $label = ''): string
    {
        $filled = $percent / 100 * $width;
        $fullBlocks = floor($filled);
        $remainder = $filled - $fullBlocks;

        // Smooth block characters
        $blocks = [' ', '▏', '▎', '▍', '▌', '▋', '▊', '▉', '█'];

        $bar = str_repeat('█', $fullBlocks);

        if ($remainder > 0 && $fullBlocks < $width) {
            $bar .= $blocks[floor($remainder * 8)];
            $fullBlocks++;
        }

        $bar .= str_repeat('░', max(0, $width - $fullBlocks));

        $color = gradient((int) $percent);

        return sprintf(
            '[%s%s%s] %s%3d%%%s %s',
            $color,
            $bar,
            RESET,
            $color,
            $percent,
            RESET,
            DIM . $label . RESET
        );
    }

    public function waveProgressBar(float $percent, int $width = 30, int $frame = 0): string
    {
        $filled = floor($percent / 100 * $width);
        $bar = '';

        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                // Wave effect on filled portion
                $waveIndex = ($i + $frame) % count($this->spinners['wave']);
                $bar .= GREEN . $this->spinners['wave'][$waveIndex] . RESET;
            } else {
                $bar .= DIM . '·' . RESET;
            }
        }

        return sprintf('[%s] %d%%', $bar, $percent);
    }

    public function segmentedBar(float $percent, int $segments = 10): string
    {
        $filledSegments = floor($percent / 100 * $segments);
        $bar = '';

        for ($i = 0; $i < $segments; $i++) {
            if ($i < $filledSegments) {
                $bar .= CYAN . '◼' . RESET;
            } else {
                $bar .= DIM . '◻' . RESET;
            }
            if ($i < $segments - 1) {
                $bar .= ' ';
            }
        }

        return $bar;
    }

    public function circularProgress(float $percent, int $frame = 0): string
    {
        $chars = ['○', '◔', '◑', '◕', '●'];
        $index = min(4, floor($percent / 25));

        // Add rotation effect
        $spinner = $this->spinners['circle'][$frame % count($this->spinners['circle'])];

        if ($percent < 100) {
            return YELLOW . $spinner . RESET . ' ' . $chars[$index] . sprintf(' %3d%%', $percent);
        }

        return GREEN . '✓' . RESET . ' ' . $chars[$index] . sprintf(' %3d%%', $percent);
    }

    public function getSpinner(string $type, int $frame): string
    {
        $frames = $this->spinners[$type] ?? $this->spinners['dots'];

        return $frames[$frame % count($frames)];
    }

    public function multiStepProgress(array $steps, int $currentStep): string
    {
        $output = '';

        foreach ($steps as $i => $step) {
            if ($i < $currentStep) {
                $output .= GREEN . '✓' . RESET;
            } elseif ($i == $currentStep) {
                $output .= YELLOW . '●' . RESET;
            } else {
                $output .= DIM . '○' . RESET;
            }

            if ($i < count($steps) - 1) {
                if ($i < $currentStep) {
                    $output .= GREEN . '━━' . RESET;
                } else {
                    $output .= DIM . '──' . RESET;
                }
            }
        }

        return $output;
    }
}

// Hide cursor for cleaner animation
echo HIDE_CURSOR;
echo CLEAR;

$animations = new ProgressAnimations;
$frame = 0;
$progress = 0;
$step = 0;

// Main animation loop
while (true) {
    // Title
    moveCursor(1, 2);
    echo BOLD . 'Animated Progress Indicators Demo' . RESET;

    // Standard progress bars
    moveCursor(3, 2);
    echo BOLD . 'Progress Bars:' . RESET;

    moveCursor(5, 2);
    echo 'Smooth:   ' . $animations->smoothProgressBar($progress, 30, 'Processing...');

    moveCursor(6, 2);
    echo 'Wave:     ' . $animations->waveProgressBar($progress, 30, $frame);

    moveCursor(7, 2);
    echo 'Segments: ' . $animations->segmentedBar($progress, 15);

    moveCursor(8, 2);
    echo 'Circular: ' . $animations->circularProgress($progress, $frame);

    // Spinners
    moveCursor(10, 2);
    echo BOLD . 'Loading Spinners:' . RESET;

    $row = 12;
    foreach (['dots', 'dots2', 'line', 'circle', 'square', 'arrow', 'pulse', 'bounce'] as $type) {
        moveCursor($row, 2);
        echo sprintf(
            '%-10s %s%s%s %sLoading...%s',
            ucfirst($type) . ':',
            CYAN,
            $animations->getSpinner($type, $frame),
            RESET,
            DIM,
            RESET
        );
        $row++;
    }

    // Multi-step progress
    moveCursor(21, 2);
    echo BOLD . 'Multi-Step Progress:' . RESET;

    $steps = ['Initialize', 'Connect', 'Authenticate', 'Load Data', 'Process', 'Complete'];
    moveCursor(23, 2);
    echo $animations->multiStepProgress($steps, $step);

    moveCursor(24, 2);
    echo DIM . 'Current: ' . RESET . $steps[$step] ?? 'Done';

    // Stacked progress bars (multiple operations)
    moveCursor(26, 2);
    echo BOLD . 'Parallel Operations:' . RESET;

    $operations = [
        ['name' => 'Download', 'progress' => min(100, $progress * 1.2), 'color' => BLUE],
        ['name' => 'Extract ', 'progress' => min(100, max(0, $progress - 10)), 'color' => CYAN],
        ['name' => 'Install ', 'progress' => min(100, max(0, $progress - 20)), 'color' => GREEN],
        ['name' => 'Cleanup ', 'progress' => min(100, max(0, $progress - 30)), 'color' => YELLOW],
    ];

    $row = 28;
    foreach ($operations as $op) {
        moveCursor($row, 2);
        echo sprintf('%-10s', $op['name']);

        $opProgress = $op['progress'];
        $width = 25;
        $filled = floor($opProgress / 100 * $width);

        echo $op['color'];
        echo str_repeat('█', $filled);
        echo RESET . DIM;
        echo str_repeat('░', $width - $filled);
        echo RESET;
        echo sprintf(' %3d%%', $opProgress);

        if ($opProgress >= 100) {
            echo GREEN . ' ✓' . RESET;
        }

        $row++;
    }

    // Footer
    moveCursor(34, 2);
    echo DIM . 'Press Ctrl+C to exit' . RESET;

    // Update counters
    $frame++;
    $progress += 0.5;
    if ($progress > 100) {
        $progress = 0;
    }

    if ($frame % 20 == 0) {
        $step = ($step + 1) % count($steps);
    }

    usleep(50000); // 50ms refresh rate
}

// Cleanup (won't reach here in demo, but good practice)
echo SHOW_CURSOR;
