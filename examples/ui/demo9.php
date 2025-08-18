#!/usr/bin/env php
<?php

/**
 * Demo 9: Golden Ratio Split Pane
 * Shows 61.8% / 38.2% split for optimal visual balance
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";

// Colors
const CYAN = "\033[36m";
const YELLOW = "\033[33m";
const GREEN = "\033[32m";
const MAGENTA = "\033[35m";
const BLUE = "\033[34m";
const GRAY = "\033[90m";
const WHITE = "\033[37m";

// Backgrounds
const BG_MAIN = "\033[48;5;234m";
const BG_SIDEBAR = "\033[48;5;236m";
const BG_ACCENT = "\033[48;5;238m";

// Golden ratio
const GOLDEN_RATIO = 1.618033988749895;
const GOLDEN_PERCENTAGE = 0.618; // Main pane gets 61.8%

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

function getTerminalDimensions(): array
{
    return [
        'width' => (int) exec('tput cols') ?: 80,
        'height' => (int) exec('tput lines') ?: 24,
    ];
}

function calculateGoldenSplit(int $totalWidth): array
{
    $mainWidth = (int) ($totalWidth * GOLDEN_PERCENTAGE);
    $sidebarWidth = $totalWidth - $mainWidth - 1; // -1 for divider

    return [
        'main' => $mainWidth,
        'sidebar' => $sidebarWidth,
        'ratio' => round($mainWidth / $sidebarWidth, 2),
    ];
}

function renderGoldenRatioUI(): void
{
    $dims = getTerminalDimensions();
    $split = calculateGoldenSplit($dims['width']);

    // Fill backgrounds
    for ($row = 1; $row <= $dims['height']; $row++) {
        // Main area background
        moveCursor($row, 1);
        echo BG_MAIN . str_repeat(' ', $split['main']) . RESET;

        // Divider
        moveCursor($row, $split['main'] + 1);
        echo "\033[48;5;240m" . GRAY . '┃' . RESET;

        // Sidebar background
        moveCursor($row, $split['main'] + 2);
        echo BG_SIDEBAR . str_repeat(' ', $split['sidebar']) . RESET;
    }

    // Header showing the ratio
    moveCursor(1, 2);
    echo BG_ACCENT . WHITE . ' Golden Ratio Layout ' . RESET;
    echo BG_MAIN . ' ';
    echo YELLOW . 'φ = ' . GOLDEN_RATIO . RESET . BG_MAIN;
    echo ' │ ';
    echo CYAN . $split['main'] . 'px (' . round(GOLDEN_PERCENTAGE * 100, 1) . '%)' . RESET . BG_MAIN;
    echo ' : ';
    echo MAGENTA . $split['sidebar'] . 'px (' . round((1 - GOLDEN_PERCENTAGE) * 100, 1) . '%)' . RESET;

    // Main content area
    renderMainContent($split['main']);

    // Sidebar content
    renderSidebar($split['main'] + 2, $split['sidebar']);

    // Visual ratio indicator
    renderRatioVisualization($dims['height'] - 3, $dims['width']);

    // Footer
    moveCursor($dims['height'], 2);
    echo BG_MAIN . DIM . WHITE . 'Main Area (61.8%)' . RESET;
    moveCursor($dims['height'], $split['main'] + 3);
    echo BG_SIDEBAR . DIM . WHITE . 'Sidebar (38.2%)' . RESET;
}

function renderMainContent(int $width): void
{
    $col = 2;
    $row = 3;

    // Title
    moveCursor($row++, $col);
    echo BG_MAIN . BOLD . WHITE . 'Main Content Area' . RESET . BG_MAIN;
    moveCursor($row++, $col);
    echo DIM . WHITE . 'Optimally sized for primary focus' . RESET . BG_MAIN;

    $row++;

    // Fibonacci visualization
    moveCursor($row++, $col);
    echo GREEN . 'Fibonacci Sequence:' . RESET . BG_MAIN;

    $fib = [1, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144];
    $fibRow = $row++;

    foreach ($fib as $i => $num) {
        if ($col + ($i * 5) > $width - 5) {
            break;
        }
        moveCursor($fibRow, $col + ($i * 5));

        // Highlight golden ratio pairs
        if (in_array($i, [4, 5]) || in_array($i, [6, 7])) {
            echo YELLOW . $num . RESET . BG_MAIN;
        } else {
            echo CYAN . $num . RESET . BG_MAIN;
        }
    }

    $row++;

    // Content blocks showing golden ratio
    $contentWidth = min(50, $width - 4);
    $blockMain = (int) ($contentWidth * GOLDEN_PERCENTAGE);
    $blockSide = $contentWidth - $blockMain;

    moveCursor($row++, $col);
    echo WHITE . 'Content Distribution:' . RESET . BG_MAIN;

    moveCursor($row++, $col);
    echo GREEN . str_repeat('█', $blockMain) . RESET . BG_MAIN;
    echo MAGENTA . str_repeat('█', $blockSide) . RESET . BG_MAIN;

    $row++;

    // Text content
    moveCursor($row++, $col);
    echo WHITE . 'Why Golden Ratio?' . RESET . BG_MAIN;

    $reasons = [
        '• Naturally pleasing to the eye',
        '• Found throughout nature and art',
        '• Creates visual harmony',
        '• Optimal for content hierarchy',
        '• Reduces cognitive load',
    ];

    foreach ($reasons as $reason) {
        moveCursor($row++, $col);
        echo DIM . WHITE . $reason . RESET . BG_MAIN;
    }

    // Code example
    $row++;
    moveCursor($row++, $col);
    echo YELLOW . '// Calculate golden ratio split' . RESET . BG_MAIN;
    moveCursor($row++, $col);
    echo CYAN . '$main' . WHITE . ' = ' . GREEN . '$total' . WHITE . ' * ' . YELLOW . '0.618' . WHITE . ';' . RESET . BG_MAIN;
    moveCursor($row++, $col);
    echo CYAN . '$sidebar' . WHITE . ' = ' . GREEN . '$total' . WHITE . ' - ' . CYAN . '$main' . WHITE . ';' . RESET . BG_MAIN;
}

function renderSidebar(int $col, int $width): void
{
    $row = 3;

    // Title
    moveCursor($row++, $col);
    echo BG_SIDEBAR . BOLD . WHITE . 'Sidebar' . RESET . BG_SIDEBAR;
    moveCursor($row++, $col);
    echo DIM . WHITE . 'Supporting content' . RESET . BG_SIDEBAR;

    $row++;

    // Ratio breakdown
    moveCursor($row++, $col);
    echo YELLOW . 'Ratios:' . RESET . BG_SIDEBAR;

    $ratios = [
        ['name' => 'Golden', 'value' => '1.618', 'bar' => 16],
        ['name' => 'Silver', 'value' => '1.414', 'bar' => 14],
        ['name' => 'Bronze', 'value' => '1.333', 'bar' => 13],
    ];

    foreach ($ratios as $ratio) {
        moveCursor($row++, $col);
        echo WHITE . $ratio['name'] . ':' . RESET . BG_SIDEBAR;

        moveCursor($row, $col);
        $barWidth = min($ratio['bar'], $width - 10);
        for ($i = 0; $i < $barWidth; $i++) {
            echo ($ratio['name'] === 'Golden' ? YELLOW : GRAY) . '▪' . RESET . BG_SIDEBAR;
        }
        echo ' ' . CYAN . $ratio['value'] . RESET . BG_SIDEBAR;
        $row++;
    }

    $row++;

    // Applications
    moveCursor($row++, $col);
    echo GREEN . 'Used in:' . RESET . BG_SIDEBAR;

    $apps = ['UI Design', 'Architecture', 'Photography', 'Typography', 'Logo Design'];
    foreach ($apps as $app) {
        if ($row > 20) {
            break;
        }
        moveCursor($row++, $col);
        echo DIM . WHITE . '• ' . $app . RESET . BG_SIDEBAR;
    }

    // Mathematical representation
    $row = 18;
    moveCursor($row++, $col);
    echo MAGENTA . 'Math:' . RESET . BG_SIDEBAR;
    moveCursor($row++, $col);
    echo WHITE . 'φ = (1+√5)/2' . RESET . BG_SIDEBAR;
    moveCursor($row++, $col);
    echo DIM . WHITE . 'a/b = (a+b)/a' . RESET . BG_SIDEBAR;
}

function renderRatioVisualization(int $row, int $width): void
{
    moveCursor($row, 2);
    echo WHITE . 'Visual: ' . RESET;

    $barWidth = min(60, $width - 10);
    $goldenPoint = (int) ($barWidth * GOLDEN_PERCENTAGE);

    for ($i = 0; $i < $barWidth; $i++) {
        if ($i < $goldenPoint) {
            echo CYAN . '█' . RESET;
        } elseif ($i == $goldenPoint) {
            echo YELLOW . '┃' . RESET;
        } else {
            echo MAGENTA . '█' . RESET;
        }
    }

    moveCursor($row, 10 + $goldenPoint);
    echo YELLOW . '▲' . RESET;
}

// Main execution
echo CLEAR;
renderGoldenRatioUI();

// Keep running
while (true) {
    sleep(1);
}
