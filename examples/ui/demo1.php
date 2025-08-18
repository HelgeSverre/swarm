#!/usr/bin/env php
<?php

/**
 * Demo 1: Enhanced Status Bar with Gradient Effect
 * Shows multiple background shades, animated spinner, and rich status information
 */

// ANSI escape codes
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";

// Colors
const WHITE = "\033[37m";
const BLACK = "\033[30m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const CYAN = "\033[36m";
const MAGENTA = "\033[35m";
const BRIGHT_WHITE = "\033[97m";

// Background gradients (using 256 colors)
const BG_GRADIENT_1 = "\033[48;5;234m"; // Darkest
const BG_GRADIENT_2 = "\033[48;5;235m";
const BG_GRADIENT_3 = "\033[48;5;236m";
const BG_GRADIENT_4 = "\033[48;5;237m";
const BG_GRADIENT_5 = "\033[48;5;238m"; // Lightest

// Spinners
const SPINNERS = [
    'dots' => ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
    'circle' => ['◐', '◓', '◑', '◒'],
    'pulse' => ['⣾', '⣽', '⣻', '⢿', '⡿', '⣟', '⣯', '⣷'],
];

function getTerminalWidth(): int
{
    return (int) exec('tput cols') ?: 80;
}

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

function renderEnhancedStatusBar(int $frame = 0): void
{
    $width = getTerminalWidth();
    $spinner = SPINNERS['pulse'][$frame % count(SPINNERS['pulse'])];

    // Calculate dynamic values
    $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 1);
    $timeElapsed = gmdate('H:i:s', (int) (time() - $_SERVER['REQUEST_TIME_FLOAT']));
    $cpuLoad = sys_getloadavg()[0];

    // First row - gradient background with main status
    moveCursor(1, 1);

    // Gradient segments
    echo BG_GRADIENT_1 . '  ';
    echo BG_GRADIENT_2 . ' ';
    echo BG_GRADIENT_3 . BRIGHT_WHITE . ' 🚀 SWARM ';
    echo BG_GRADIENT_4 . ' ';
    echo BG_GRADIENT_5 . ' ';

    // Status section with gradient
    echo BG_GRADIENT_4 . CYAN . $spinner . ' Processing' . RESET . BG_GRADIENT_4 . ' ';
    echo DIM . WHITE . '│' . RESET . BG_GRADIENT_4 . ' ';

    // Task indicator
    echo GREEN . '● ' . WHITE . 'Building components' . RESET . BG_GRADIENT_4;

    // Right-aligned info
    $rightContent = sprintf(
        '%sMEM: %s%.1fMB %s│ %sCPU: %s%.1f %s│ %s%s',
        DIM . WHITE,
        YELLOW,
        $memoryUsage,
        DIM . WHITE,
        DIM . WHITE,
        CYAN,
        $cpuLoad,
        DIM . WHITE,
        WHITE,
        $timeElapsed
    );

    $rightLength = mb_strlen(strip_tags($rightContent)) - 20; // Account for ANSI codes
    $padding = $width - 60 - $rightLength;

    echo BG_GRADIENT_3 . str_repeat(' ', max(0, $padding));
    echo $rightContent;
    echo BG_GRADIENT_2 . ' ';
    echo BG_GRADIENT_1 . '  ';
    echo RESET . "\n";

    // Second row - subtle progress bar
    moveCursor(2, 1);
    $progress = 65; // Example progress
    $barWidth = $width;
    $filled = (int) (($progress / 100) * $barWidth);

    echo BG_GRADIENT_1;
    for ($i = 0; $i < $barWidth; $i++) {
        if ($i < $filled) {
            echo "\033[48;5;" . (236 + min(4, (int) ($i / 10))) . 'm ';
        } else {
            echo BG_GRADIENT_1 . DIM . '·' . RESET . BG_GRADIENT_1;
        }
    }
    echo RESET;
}

// Clear screen
echo CLEAR;

// Animation loop
$frame = 0;
while (true) {
    renderEnhancedStatusBar($frame++);

    // Demo content below status bar
    moveCursor(4, 2);
    echo BOLD . 'Enhanced Status Bar Demo' . RESET . "\n";
    moveCursor(5, 2);
    echo DIM . 'Features:' . RESET . "\n";
    moveCursor(6, 4);
    echo "• Gradient background effects\n";
    moveCursor(7, 4);
    echo "• Animated spinner with multiple styles\n";
    moveCursor(8, 4);
    echo "• Real-time system metrics\n";
    moveCursor(9, 4);
    echo "• Smooth color transitions\n";
    moveCursor(10, 4);
    echo "• Progress indication\n";

    moveCursor(12, 2);
    echo DIM . 'Press Ctrl+C to exit' . RESET;

    usleep(100000); // 100ms refresh rate
}
