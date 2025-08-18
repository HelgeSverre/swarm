#!/usr/bin/env php
<?php

/**
 * Demo 3: Improved Color Scheme (Tokyo Night inspired)
 * Cohesive color palette with semantic meaning
 */

// Tokyo Night Color Palette
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const ITALIC = "\033[3m";
const CLEAR = "\033[2J\033[H";

// Tokyo Night inspired colors (256 color mode)
const TN_BG = "\033[48;5;234m";           // #1a1b26 - Background
const TN_BG_DARK = "\033[48;5;232m";      // #16161e - Darker bg
const TN_BG_HIGHLIGHT = "\033[48;5;236m"; // #292e42 - Highlight

const TN_FG = "\033[38;5;251m";           // #c0caf5 - Foreground
const TN_FG_DARK = "\033[38;5;245m";      // #565f89 - Comments
const TN_FG_GUTTER = "\033[38;5;240m";    // #3b4261 - Line numbers

const TN_BLUE = "\033[38;5;111m";         // #7aa2f7 - Functions
const TN_CYAN = "\033[38;5;87m";          // #7dcfff - Special
const TN_GREEN = "\033[38;5;115m";        // #9ece6a - Strings
const TN_MAGENTA = "\033[38;5;176m";      // #bb9af7 - Keywords
const TN_RED = "\033[38;5;203m";          // #f7768e - Errors
const TN_YELLOW = "\033[38;5;221m";       // #e0af68 - Warnings
const TN_ORANGE = "\033[38;5;215m";       // #ff9e64 - Numbers
const TN_PURPLE = "\033[38;5;141m";       // #9d7cd8 - Statements

// Semantic color assignments
const COLOR_SUCCESS = TN_GREEN;
const COLOR_ERROR = TN_RED;
const COLOR_WARNING = TN_YELLOW;
const COLOR_INFO = TN_BLUE;
const COLOR_ACCENT = TN_MAGENTA;
const COLOR_HIGHLIGHT = TN_CYAN;
const COLOR_MUTED = TN_FG_DARK;
const COLOR_COMMENT = TN_FG_GUTTER;

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

function renderTokyoNightUI(): void
{
    $width = (int) exec('tput cols') ?: 80;
    $height = (int) exec('tput lines') ?: 24;

    // Background fill
    for ($i = 1; $i <= $height; $i++) {
        moveCursor($i, 1);
        echo TN_BG . str_repeat(' ', $width) . RESET;
    }

    // Header with gradient effect
    moveCursor(1, 1);
    echo TN_BG_DARK . str_repeat(' ', $width) . RESET;
    moveCursor(2, 1);
    echo TN_BG_DARK . str_repeat(' ', $width) . RESET;

    moveCursor(1, 3);
    echo TN_BG_DARK . TN_MAGENTA . '⟡ ' . TN_FG . BOLD . 'SWARM' . RESET;
    echo TN_BG_DARK . COLOR_COMMENT . ' │ ' . RESET;
    echo TN_BG_DARK . COLOR_SUCCESS . '● ' . TN_FG . 'Connected' . RESET;
    echo TN_BG_DARK . COLOR_COMMENT . ' │ ' . RESET;
    echo TN_BG_DARK . COLOR_INFO . '◆ ' . TN_FG . 'Ready' . RESET;

    // Code editor section
    moveCursor(4, 3);
    echo TN_FG . BOLD . '// Code Analysis' . RESET;

    // Syntax highlighted code example
    $code = [
        ['line' => 5, 'content' => 'class ' . TN_BLUE . 'AgentController' . TN_FG . ' {'],
        ['line' => 6, 'content' => '    ' . TN_PURPLE . 'public function ' . TN_BLUE . 'process' . TN_FG . '(' . TN_ORANGE . 'Request ' . TN_FG . '$request) {'],
        ['line' => 7, 'content' => '        ' . COLOR_COMMENT . '// Initialize agent with context' . RESET],
        ['line' => 8, 'content' => '        ' . TN_FG . '$agent = ' . TN_PURPLE . 'new ' . TN_BLUE . 'CodingAgent' . TN_FG . '();'],
        ['line' => 9, 'content' => '        ' . TN_FG . '$agent->' . TN_CYAN . 'setModel' . TN_FG . '(' . TN_GREEN . '"gpt-4"' . TN_FG . ');'],
        ['line' => 10, 'content' => '        '],
        ['line' => 11, 'content' => '        ' . TN_PURPLE . 'return ' . TN_FG . '$agent->' . TN_CYAN . 'execute' . TN_FG . '($request);'],
        ['line' => 12, 'content' => '    }'],
        ['line' => 13, 'content' => '}'],
    ];

    foreach ($code as $line) {
        moveCursor($line['line'], 1);
        echo TN_BG_HIGHLIGHT . TN_FG_GUTTER . mb_str_pad($line['line'] - 4, 3, ' ', STR_PAD_LEFT) . RESET;
        echo TN_BG . ' ' . $line['content'] . RESET;
    }

    // Status messages with semantic colors
    moveCursor(15, 3);
    echo COLOR_SUCCESS . '✓ ' . TN_FG . 'Tests passed: ' . TN_GREEN . '42/42' . RESET;

    moveCursor(16, 3);
    echo COLOR_WARNING . '⚠ ' . TN_FG . 'Warning: ' . TN_YELLOW . 'Unused variable on line 8' . RESET;

    moveCursor(17, 3);
    echo COLOR_ERROR . '✗ ' . TN_FG . 'Error: ' . TN_RED . 'Type mismatch detected' . RESET;

    moveCursor(18, 3);
    echo COLOR_INFO . 'ℹ ' . TN_FG . 'Info: ' . TN_BLUE . '3 suggestions available' . RESET;

    // Task list with proper coloring
    moveCursor(20, 3);
    echo TN_PURPLE . BOLD . 'Tasks:' . RESET;

    $tasks = [
        ['status' => 'done', 'text' => 'Initialize project'],
        ['status' => 'active', 'text' => 'Implement core features'],
        ['status' => 'pending', 'text' => 'Write documentation'],
        ['status' => 'error', 'text' => 'Deploy to production'],
    ];

    $row = 21;
    foreach ($tasks as $task) {
        moveCursor($row++, 5);

        $icon = match ($task['status']) {
            'done' => COLOR_SUCCESS . '✓',
            'active' => COLOR_HIGHLIGHT . '▶',
            'pending' => COLOR_MUTED . '○',
            'error' => COLOR_ERROR . '✗',
            default => ' '
        };

        $textColor = match ($task['status']) {
            'done' => COLOR_MUTED,
            'active' => TN_FG,
            'pending' => COLOR_MUTED,
            'error' => COLOR_ERROR,
            default => TN_FG
        };

        echo $icon . ' ' . $textColor . $task['text'] . RESET;
    }

    // Color palette showcase
    moveCursor(20, 40);
    echo TN_FG . 'Color Palette:' . RESET;

    $palette = [
        ['name' => 'Blue', 'color' => TN_BLUE, 'use' => 'Functions'],
        ['name' => 'Cyan', 'color' => TN_CYAN, 'use' => 'Methods'],
        ['name' => 'Green', 'color' => TN_GREEN, 'use' => 'Success'],
        ['name' => 'Magenta', 'color' => TN_MAGENTA, 'use' => 'Keywords'],
        ['name' => 'Red', 'color' => TN_RED, 'use' => 'Errors'],
        ['name' => 'Yellow', 'color' => TN_YELLOW, 'use' => 'Warnings'],
        ['name' => 'Purple', 'color' => TN_PURPLE, 'use' => 'Statements'],
        ['name' => 'Orange', 'color' => TN_ORANGE, 'use' => 'Numbers'],
    ];

    $row = 21;
    foreach ($palette as $p) {
        moveCursor($row++, 40);
        echo $p['color'] . '██ ' . TN_FG . $p['name'] . COLOR_COMMENT . ' → ' . $p['use'] . RESET;
    }

    // Footer
    moveCursor($height - 1, 3);
    echo TN_BG_DARK . COLOR_COMMENT . ' Tokyo Night Theme Demo │ Semantic Colors │ High Contrast ' . str_repeat(' ', $width - 60) . RESET;
}

// Clear and render
echo CLEAR;
renderTokyoNightUI();

// Footer info
$height = (int) exec('tput lines') ?: 24;
moveCursor($height, 3);
echo COLOR_COMMENT . 'Press Ctrl+C to exit' . RESET;

// Keep running
while (true) {
    sleep(1);
}
