#!/usr/bin/env php
<?php

/**
 * Demo 7: Compact Mode for Small Terminals
 * Responsive layout that adapts to terminal size
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";

// Compact color scheme
const C_PRIMARY = "\033[36m";   // Cyan
const C_SUCCESS = "\033[32m";   // Green
const C_WARNING = "\033[33m";   // Yellow
const C_ERROR = "\033[31m";     // Red
const C_MUTED = "\033[90m";     // Gray

// Minimal backgrounds
const BG_BAR = "\033[48;5;236m";

function getTerminalSize(): array
{
    return [
        'width' => (int) exec('tput cols') ?: 80,
        'height' => (int) exec('tput lines') ?: 24,
    ];
}

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

function truncate(string $text, int $maxLen): string
{
    if (mb_strlen($text) <= $maxLen) {
        return $text;
    }

    return mb_substr($text, 0, $maxLen - 1) . '…';
}

function renderCompactMode(): void
{
    $size = getTerminalSize();
    $isUltraCompact = $size['width'] < 60 || $size['height'] < 20;
    $isCompact = $size['width'] < 100 || $size['height'] < 30;

    // Adjust layout based on terminal size
    $row = 1;

    // Ultra-compact header (single line)
    if ($isUltraCompact) {
        moveCursor($row++, 1);
        echo BG_BAR . C_PRIMARY . 'SW' . RESET . BG_BAR;
        echo ' ' . C_SUCCESS . '●' . RESET . BG_BAR;
        echo ' T:3 ' . C_WARNING . 'W:1' . RESET . BG_BAR;
        echo str_repeat(' ', max(0, $size['width'] - 15)) . RESET;
    } elseif ($isCompact) {
        // Compact header (two lines)
        moveCursor($row++, 1);
        echo BG_BAR . C_PRIMARY . ' SWARM' . RESET . BG_BAR;
        echo ' │ ' . C_SUCCESS . '● Ready' . RESET . BG_BAR;
        echo str_repeat(' ', max(0, $size['width'] - 20)) . RESET;

        moveCursor($row++, 1);
        echo C_MUTED . 'Tasks: 3 │ Warns: 1 │ Files: 12' . RESET;
    } else {
        // Normal header
        moveCursor($row++, 1);
        echo BG_BAR . ' ' . C_PRIMARY . BOLD . 'SWARM Terminal' . RESET . BG_BAR;
        echo ' │ ' . C_SUCCESS . '● Connected' . RESET . BG_BAR;
        echo ' │ Tasks: 3 │ Warnings: 1';
        echo str_repeat(' ', max(0, $size['width'] - 50)) . RESET;
    }

    $row++;

    // Activity list (adaptive)
    $activities = [
        ['type' => 'cmd', 'text' => 'npm install', 'time' => '2m'],
        ['type' => 'ok', 'text' => 'Dependencies installed', 'time' => '1m'],
        ['type' => 'warn', 'text' => 'Deprecated package found', 'time' => '30s'],
        ['type' => 'task', 'text' => 'Building components...', 'time' => 'now'],
    ];

    $maxActivities = $isUltraCompact ? 3 : ($isCompact ? 5 : 10);
    $maxTextLen = $isUltraCompact ? 20 : ($isCompact ? 40 : 60);

    foreach (array_slice($activities, 0, $maxActivities) as $activity) {
        moveCursor($row++, 1);

        // Ultra-compact: single character indicators
        if ($isUltraCompact) {
            $icon = match ($activity['type']) {
                'cmd' => '>',
                'ok' => '✓',
                'warn' => '!',
                'task' => '●',
                default => '-'
            };

            $color = match ($activity['type']) {
                'cmd' => C_PRIMARY,
                'ok' => C_SUCCESS,
                'warn' => C_WARNING,
                'task' => C_PRIMARY,
                default => C_MUTED
            };

            echo $color . $icon . RESET . ' ';
            echo truncate($activity['text'], $maxTextLen);
            echo C_MUTED . ' ' . $activity['time'] . RESET;
        } else {
            // Compact or normal mode
            $icon = match ($activity['type']) {
                'cmd' => '$',
                'ok' => '✓',
                'warn' => '⚠',
                'task' => '▶',
                default => '•'
            };

            $color = match ($activity['type']) {
                'cmd' => C_PRIMARY,
                'ok' => C_SUCCESS,
                'warn' => C_WARNING,
                'task' => C_PRIMARY,
                default => C_MUTED
            };

            echo C_MUTED . '[' . $activity['time'] . ']' . RESET . ' ';
            echo $color . $icon . RESET . ' ';
            echo truncate($activity['text'], $maxTextLen);
        }
    }

    // Compact task bar (if space allows)
    if (! $isUltraCompact) {
        $row++;
        moveCursor($row++, 1);
        echo C_MUTED . str_repeat('─', min(50, $size['width'] - 2)) . RESET;

        moveCursor($row++, 1);
        echo BOLD . 'Tasks:' . RESET;

        $tasks = [
            ['name' => 'Build', 'progress' => 75],
            ['name' => 'Test', 'progress' => 45],
            ['name' => 'Deploy', 'progress' => 0],
        ];

        foreach ($tasks as $task) {
            moveCursor($row++, 1);

            // Mini progress bar
            $barWidth = $isCompact ? 10 : 20;
            $filled = (int) (($task['progress'] / 100) * $barWidth);

            echo truncate($task['name'], 8) . ' ';

            for ($i = 0; $i < $barWidth; $i++) {
                if ($i < $filled) {
                    echo C_SUCCESS . '█' . RESET;
                } else {
                    echo C_MUTED . '░' . RESET;
                }
            }

            echo ' ' . $task['progress'] . '%';
        }
    }

    // Input area (always at bottom)
    $inputRow = $size['height'] - 1;

    if ($isUltraCompact) {
        moveCursor($inputRow, 1);
        echo '> ';
    } else {
        moveCursor($inputRow - 1, 1);
        echo C_MUTED . str_repeat('─', min(50, $size['width'] - 2)) . RESET;

        moveCursor($inputRow, 1);
        echo C_PRIMARY . 'swarm>' . RESET . ' ';
    }

    // Size indicator (top right)
    $sizeText = $size['width'] . 'x' . $size['height'];
    moveCursor(1, $size['width'] - mb_strlen($sizeText) - 1);
    echo C_MUTED . $sizeText . RESET;

    // Mode indicator
    $modeText = $isUltraCompact ? '[ULTRA]' : ($isCompact ? '[COMPACT]' : '[NORMAL]');
    moveCursor(2, $size['width'] - mb_strlen($modeText) - 1);
    echo C_WARNING . $modeText . RESET;
}

// Main display
echo CLEAR;

// Show different modes based on terminal size
while (true) {
    $size = getTerminalSize();

    echo CLEAR;

    // Title (if space)
    if ($size['height'] > 25) {
        moveCursor(1, 2);
        echo BOLD . 'Compact Mode Demo' . RESET;
        moveCursor(2, 2);
        echo 'Resize your terminal to see different layouts:';
        moveCursor(3, 4);
        echo '• < 60 cols or < 20 rows: Ultra-compact';
        moveCursor(4, 4);
        echo '• < 100 cols or < 30 rows: Compact';
        moveCursor(5, 4);
        echo '• Otherwise: Normal mode';
        moveCursor(7, 1);
    }

    renderCompactMode();

    // Exit hint (if space)
    if ($size['height'] > 20) {
        moveCursor($size['height'], $size['width'] - 20);
        echo C_MUTED . 'Ctrl+C to exit' . RESET;
    }

    sleep(1);
}
