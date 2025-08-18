#!/usr/bin/env php
<?php

/**
 * Demo 8: Advanced Box Drawing with Shadows
 * Double-line borders, shadow effects, nested boxes
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";

// Colors
const WHITE = "\033[37m";
const GRAY = "\033[90m";
const DARK_GRAY = "\033[38;5;238m";
const CYAN = "\033[36m";
const YELLOW = "\033[33m";
const GREEN = "\033[32m";

// Box drawing characters
const BOX_SINGLE = ['┌', '─', '┐', '│', '└', '┘', '├', '┤', '┬', '┴', '┼'];
const BOX_DOUBLE = ['╔', '═', '╗', '║', '╚', '╝', '╠', '╣', '╦', '╩', '╬'];
const BOX_ROUND = ['╭', '─', '╮', '│', '╰', '╯'];
const BOX_HEAVY = ['┏', '━', '┓', '┃', '┗', '┛', '┣', '┫', '┳', '┻', '╋'];

// Shadow characters (using darker colors)
const SHADOW_CHAR = '░';

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

function drawBox(
    int $row,
    int $col,
    int $width,
    int $height,
    string $title = '',
    string $style = 'single',
    bool $shadow = false,
    string $color = WHITE
): void {
    // Select box characters
    $chars = match ($style) {
        'double' => BOX_DOUBLE,
        'round' => BOX_ROUND,
        'heavy' => BOX_HEAVY,
        default => BOX_SINGLE
    };

    // Draw shadow first if enabled
    if ($shadow) {
        for ($i = 1; $i < $height; $i++) {
            moveCursor($row + $i, $col + $width);
            echo DARK_GRAY . SHADOW_CHAR . SHADOW_CHAR . RESET;
        }
        moveCursor($row + $height, $col + 2);
        echo DARK_GRAY . str_repeat(SHADOW_CHAR, $width) . RESET;
    }

    // Top border
    moveCursor($row, $col);
    echo $color . $chars[0]; // Top-left corner

    if ($title) {
        $titleLen = mb_strlen($title);
        $padding = max(0, ($width - $titleLen - 4) / 2);
        echo str_repeat($chars[1], (int) $padding);
        echo ' ' . BOLD . $title . RESET . $color . ' ';
        echo str_repeat($chars[1], $width - (int) $padding - $titleLen - 4);
    } else {
        echo str_repeat($chars[1], $width - 2);
    }

    echo $chars[2] . RESET; // Top-right corner

    // Sides
    for ($i = 1; $i < $height - 1; $i++) {
        moveCursor($row + $i, $col);
        echo $color . $chars[3] . RESET; // Left border

        moveCursor($row + $i, $col + $width - 1);
        echo $color . $chars[3] . RESET; // Right border
    }

    // Bottom border
    moveCursor($row + $height - 1, $col);
    echo $color . $chars[4]; // Bottom-left corner
    echo str_repeat($chars[1], $width - 2);
    echo $chars[5] . RESET; // Bottom-right corner
}

function drawNestedBoxes(): void
{
    // Main container with shadow
    drawBox(2, 2, 60, 20, 'Main Container', 'double', true, CYAN);

    // Nested box 1
    drawBox(4, 5, 25, 8, 'Panel A', 'single', false, YELLOW);

    // Content in Panel A
    moveCursor(6, 7);
    echo '• Feature 1: ' . GREEN . 'Active' . RESET;
    moveCursor(7, 7);
    echo '• Feature 2: ' . DIM . 'Disabled' . RESET;
    moveCursor(8, 7);
    echo '• Feature 3: ' . YELLOW . 'Pending' . RESET;

    // Nested box 2
    drawBox(4, 32, 25, 8, 'Panel B', 'round', false, GREEN);

    // Progress bars in Panel B
    moveCursor(6, 34);
    echo 'CPU:  ';
    for ($i = 0; $i < 15; $i++) {
        echo ($i < 10) ? GREEN . '█' . RESET : DIM . '░' . RESET;
    }

    moveCursor(7, 34);
    echo 'MEM:  ';
    for ($i = 0; $i < 15; $i++) {
        echo ($i < 12) ? YELLOW . '█' . RESET : DIM . '░' . RESET;
    }

    moveCursor(8, 34);
    echo 'DISK: ';
    for ($i = 0; $i < 15; $i++) {
        echo ($i < 5) ? CYAN . '█' . RESET : DIM . '░' . RESET;
    }

    // Nested box 3 - Heavy style
    drawBox(13, 5, 52, 7, 'Console Output', 'heavy', false, GRAY);

    // Console content
    $consoleLines = [
        '[INFO]  System initialized successfully',
        '[WARN]  Cache size exceeding limit',
        '[DEBUG] Connection established to server',
        '[ERROR] Failed to load configuration file',
    ];

    $row = 15;
    foreach ($consoleLines as $line) {
        moveCursor($row++, 7);

        if (str_contains($line, '[INFO]')) {
            echo CYAN . $line . RESET;
        } elseif (str_contains($line, '[WARN]')) {
            echo YELLOW . $line . RESET;
        } elseif (str_contains($line, '[ERROR]')) {
            echo "\033[91m" . $line . RESET; // Bright red
        } else {
            echo DIM . $line . RESET;
        }
    }
}

function draw3DEffect(): void
{
    $startRow = 2;
    $startCol = 65;

    // Layer 3 (back) - darkest
    drawBox($startRow + 4, $startCol + 4, 20, 8, '', 'single', false, DARK_GRAY);

    // Layer 2 (middle)
    drawBox($startRow + 2, $startCol + 2, 20, 8, '', 'single', false, GRAY);

    // Layer 1 (front) - brightest
    drawBox($startRow, $startCol, 20, 8, '3D Effect', 'double', false, WHITE);

    // Content
    moveCursor($startRow + 3, $startCol + 3);
    echo 'Layered boxes';
    moveCursor($startRow + 4, $startCol + 3);
    echo 'create depth';
    moveCursor($startRow + 5, $startCol + 3);
    echo 'illusion';
}

function drawConnectedBoxes(): void
{
    $row = 24;
    $col = 2;

    // Box 1
    moveCursor($row, $col);
    echo CYAN . '╭─ Input ──╮' . RESET;
    moveCursor($row + 1, $col);
    echo CYAN . '│ Data.txt │' . RESET;
    moveCursor($row + 2, $col);
    echo CYAN . '╰──────────╯' . RESET;

    // Connector
    moveCursor($row + 1, $col + 12);
    echo GRAY . '───▶' . RESET;

    // Box 2
    moveCursor($row, $col + 16);
    echo YELLOW . '╭─ Process ─╮' . RESET;
    moveCursor($row + 1, $col + 16);
    echo YELLOW . '│ Transform │' . RESET;
    moveCursor($row + 2, $col + 16);
    echo YELLOW . '╰───────────╯' . RESET;

    // Connector
    moveCursor($row + 1, $col + 29);
    echo GRAY . '───▶' . RESET;

    // Box 3
    moveCursor($row, $col + 33);
    echo GREEN . '╭─ Output ──╮' . RESET;
    moveCursor($row + 1, $col + 33);
    echo GREEN . '│ Result.db │' . RESET;
    moveCursor($row + 2, $col + 33);
    echo GREEN . '╰───────────╯' . RESET;
}

// Clear screen
echo CLEAR;

// Title
moveCursor(1, 2);
echo BOLD . 'Advanced Box Drawing Demo' . RESET;

// Draw demonstrations
drawNestedBoxes();
draw3DEffect();
drawConnectedBoxes();

// Legend
moveCursor(28, 2);
echo DIM . 'Styles: Single │ Double │ Round │ Heavy │ With shadows' . RESET;

moveCursor(29, 2);
echo DIM . 'Press Ctrl+C to exit' . RESET;

// Keep running
while (true) {
    sleep(1);
}
