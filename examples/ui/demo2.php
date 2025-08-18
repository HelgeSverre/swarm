#!/usr/bin/env php
<?php

/**
 * Demo 2: Modern Sidebar with Collapsible Sections
 * Shows rounded corners, tree structure, mini progress bars
 */

// ANSI codes
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";

// Colors
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const CYAN = "\033[36m";
const MAGENTA = "\033[35m";
const WHITE = "\033[37m";
const GRAY = "\033[90m";
const BRIGHT_CYAN = "\033[96m";

// Backgrounds
const BG_DARK = "\033[48;5;236m";
const BG_DARKER = "\033[48;5;234m";

// Box drawing - rounded
const BOX_TL_ROUND = '╭';
const BOX_TR_ROUND = '╮';
const BOX_BL_ROUND = '╰';
const BOX_BR_ROUND = '╯';
const BOX_H = '─';
const BOX_V = '│';
const BOX_V_HEAVY = '┃';

// Tree characters
const TREE_BRANCH = '├─';
const TREE_LAST = '└─';
const TREE_PIPE = '│ ';
const TREE_SPACE = '  ';

// Icons
const ICON_FOLDER_OPEN = '📂';
const ICON_FOLDER_CLOSED = '📁';
const ICON_FILE = '📄';
const ICON_CHECK = '✓';
const ICON_RUNNING = '▶';
const ICON_PENDING = '○';

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

function renderMiniProgressBar(float $percent, int $width = 10): string
{
    $filled = (int) (($percent / 100) * $width);
    $bar = '';

    // Use block characters for smooth progress
    $blocks = ['▏', '▎', '▍', '▌', '▋', '▊', '▉', '█'];

    for ($i = 0; $i < $width; $i++) {
        if ($i < $filled - 1) {
            $bar .= '█';
        } elseif ($i == $filled - 1) {
            $remainder = ($percent / 100 * $width) - $filled + 1;
            $blockIndex = min(7, (int) ($remainder * 8));
            $bar .= $blocks[$blockIndex];
        } else {
            $bar .= '░';
        }
    }

    return GREEN . $bar . RESET;
}

function renderModernSidebar(): void
{
    $col = 45; // Sidebar column position
    $width = 35;

    // Draw rounded box
    moveCursor(1, $col);
    echo GRAY . BOX_TL_ROUND . str_repeat(BOX_H, $width - 2) . BOX_TR_ROUND . RESET;

    for ($i = 2; $i <= 25; $i++) {
        moveCursor($i, $col);
        echo GRAY . BOX_V . RESET . str_repeat(' ', $width - 2) . GRAY . BOX_V . RESET;
    }

    moveCursor(26, $col);
    echo GRAY . BOX_BL_ROUND . str_repeat(BOX_H, $width - 2) . BOX_BR_ROUND . RESET;

    // Task Queue Section (Expanded)
    $row = 2;
    moveCursor($row++, $col + 2);
    echo BOLD . CYAN . '▼ Task Queue' . RESET . ' ' . BG_DARK . ' 3 active ' . RESET;

    moveCursor($row++, $col + 2);
    echo DIM . '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . RESET;

    // Task items with tree structure
    $tasks = [
        ['name' => 'Build components', 'status' => 'running', 'progress' => 75],
        ['name' => 'Run tests', 'status' => 'running', 'progress' => 45],
        ['name' => 'Deploy to staging', 'status' => 'pending', 'progress' => 0],
        ['name' => 'Update documentation', 'status' => 'completed', 'progress' => 100],
    ];

    foreach ($tasks as $i => $task) {
        $isLast = ($i === count($tasks) - 1);
        moveCursor($row++, $col + 2);

        // Tree structure
        echo GRAY . ($isLast ? TREE_LAST : TREE_BRANCH) . RESET;

        // Status icon
        $icon = match ($task['status']) {
            'completed' => GREEN . ICON_CHECK,
            'running' => YELLOW . ICON_RUNNING,
            'pending' => DIM . ICON_PENDING,
            default => ' '
        };

        echo " {$icon} " . RESET;
        echo mb_substr($task['name'], 0, 20);

        // Mini progress bar for running tasks
        if ($task['status'] === 'running') {
            moveCursor($row++, $col + 4);
            echo GRAY . ($isLast ? TREE_SPACE : TREE_PIPE) . RESET;
            echo '  ' . renderMiniProgressBar($task['progress'], 15);
            echo ' ' . DIM . $task['progress'] . '%' . RESET;
        }
    }

    $row++;
    moveCursor($row++, $col + 2);
    echo DIM . '─────────────────────────────────' . RESET;

    // Context Section (Collapsed)
    moveCursor($row++, $col + 2);
    echo BOLD . MAGENTA . '▶ Context' . RESET . ' ' . DIM . '(3 items)' . RESET;

    $row++;
    moveCursor($row++, $col + 2);
    echo DIM . '─────────────────────────────────' . RESET;

    // File Explorer (Expanded)
    moveCursor($row++, $col + 2);
    echo BOLD . YELLOW . '▼ Files' . RESET;

    // File tree
    $files = [
        ['type' => 'folder', 'name' => 'src/', 'expanded' => true],
        ['type' => 'file', 'name' => 'Agent.php', 'indent' => 1],
        ['type' => 'file', 'name' => 'Tools.php', 'indent' => 1],
        ['type' => 'folder', 'name' => 'config/', 'expanded' => false],
        ['type' => 'file', 'name' => 'README.md', 'indent' => 0],
    ];

    foreach ($files as $file) {
        moveCursor($row++, $col + 2);

        $indent = str_repeat('  ', $file['indent'] ?? 0);

        if ($file['type'] === 'folder') {
            $icon = ($file['expanded'] ?? false) ? ICON_FOLDER_OPEN : ICON_FOLDER_CLOSED;
            echo $indent . GRAY . TREE_BRANCH . RESET . " {$icon} " . CYAN . $file['name'] . RESET;
        } else {
            echo $indent . GRAY . TREE_BRANCH . RESET . ' ' . ICON_FILE . ' ' . $file['name'];
        }
    }

    // Actions section
    $row++;
    moveCursor($row++, $col + 2);
    echo DIM . '─────────────────────────────────' . RESET;
    moveCursor($row++, $col + 2);
    echo BRIGHT_CYAN . '[+] Add Task' . RESET . '  ' . DIM . '[↻] Refresh' . RESET;
}

// Clear and render
echo CLEAR;

// Main content area
moveCursor(2, 2);
echo BOLD . 'Modern Sidebar Demo' . RESET;
moveCursor(4, 2);
echo 'This demo showcases:';
moveCursor(5, 4);
echo '• Rounded corner boxes';
moveCursor(6, 4);
echo '• Collapsible sections with indicators';
moveCursor(7, 4);
echo '• Tree-like file structure';
moveCursor(8, 4);
echo '• Mini progress bars';
moveCursor(9, 4);
echo '• Status icons and colors';
moveCursor(10, 4);
echo '• Interactive-looking elements';

// Render the sidebar
renderModernSidebar();

// Footer
moveCursor(28, 2);
echo DIM . 'Press Ctrl+C to exit' . RESET;

// Keep running
while (true) {
    sleep(1);
}
