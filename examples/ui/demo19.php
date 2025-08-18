#!/usr/bin/env php
<?php

/**
 * Demo 19: Command Palette
 * VS Code-style command palette with fuzzy search and keyboard navigation
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const REVERSE = "\033[7m";
const CLEAR = "\033[2J\033[H";
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";

// Palette colors
const C_BACKGROUND = "\033[48;5;236m";
const C_SELECTED = "\033[48;5;238m";
const C_MATCH = "\033[38;5;117m";
const C_TEXT = "\033[38;5;250m";
const C_CATEGORY = "\033[38;5;141m";
const C_SHORTCUT = "\033[38;5;221m";
const C_DESCRIPTION = "\033[38;5;245m";
const C_INPUT = "\033[38;5;255m";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class CommandPalette
{
    private array $commands = [];

    private array $filteredCommands = [];

    private string $searchQuery = '';

    private int $selectedIndex = 0;

    private int $scrollOffset = 0;

    private int $maxVisible = 12;

    private bool $isOpen = false;

    private int $frame = 0;

    private array $recentCommands = [];

    private array $searchHistory = [];

    public function __construct()
    {
        $this->initializeCommands();
        $this->filteredCommands = $this->commands;
    }

    public function render(): void
    {
        echo CLEAR;

        // Background
        $this->renderBackground();

        // Toggle palette open/closed for demo
        if ($this->frame % 100 === 0) {
            $this->isOpen = ! $this->isOpen;
            if ($this->isOpen) {
                $this->searchQuery = '';
                $this->selectedIndex = 0;
                $this->scrollOffset = 0;
                $this->filterCommands();
            }
        }

        if ($this->isOpen) {
            $this->renderPalette();

            // Simulate typing
            if ($this->frame % 5 === 0 && $this->frame % 100 < 50) {
                $searchTerms = ['save', 'file', 'go to', 'terminal', 'settings'];
                $term = $searchTerms[(int) ($this->frame / 100) % count($searchTerms)];
                if (mb_strlen($this->searchQuery) < mb_strlen($term)) {
                    $this->searchQuery = mb_substr($term, 0, mb_strlen($this->searchQuery) + 1);
                    $this->filterCommands();
                }
            }

            // Simulate navigation
            if ($this->frame % 20 === 0) {
                $this->selectedIndex = ($this->selectedIndex + 1) % count($this->filteredCommands);
                $this->adjustScroll();
            }
        } else {
            $this->renderClosedState();
        }

        $this->frame++;
    }

    private function initializeCommands(): void
    {
        $this->commands = [
            // File operations
            ['category' => 'File', 'name' => 'New File', 'shortcut' => 'Ctrl+N', 'description' => 'Create a new file', 'icon' => '📄'],
            ['category' => 'File', 'name' => 'Open File...', 'shortcut' => 'Ctrl+O', 'description' => 'Open an existing file', 'icon' => '📂'],
            ['category' => 'File', 'name' => 'Save', 'shortcut' => 'Ctrl+S', 'description' => 'Save current file', 'icon' => '💾'],
            ['category' => 'File', 'name' => 'Save As...', 'shortcut' => 'Ctrl+Shift+S', 'description' => 'Save with a new name', 'icon' => '💾'],
            ['category' => 'File', 'name' => 'Close File', 'shortcut' => 'Ctrl+W', 'description' => 'Close current file', 'icon' => '❌'],

            // Edit operations
            ['category' => 'Edit', 'name' => 'Undo', 'shortcut' => 'Ctrl+Z', 'description' => 'Undo last action', 'icon' => '↶'],
            ['category' => 'Edit', 'name' => 'Redo', 'shortcut' => 'Ctrl+Y', 'description' => 'Redo last undone action', 'icon' => '↷'],
            ['category' => 'Edit', 'name' => 'Cut', 'shortcut' => 'Ctrl+X', 'description' => 'Cut selection', 'icon' => '✂️'],
            ['category' => 'Edit', 'name' => 'Copy', 'shortcut' => 'Ctrl+C', 'description' => 'Copy selection', 'icon' => '📋'],
            ['category' => 'Edit', 'name' => 'Paste', 'shortcut' => 'Ctrl+V', 'description' => 'Paste from clipboard', 'icon' => '📋'],
            ['category' => 'Edit', 'name' => 'Find', 'shortcut' => 'Ctrl+F', 'description' => 'Find in file', 'icon' => '🔍'],
            ['category' => 'Edit', 'name' => 'Replace', 'shortcut' => 'Ctrl+H', 'description' => 'Find and replace', 'icon' => '🔄'],

            // View operations
            ['category' => 'View', 'name' => 'Toggle Sidebar', 'shortcut' => 'Ctrl+B', 'description' => 'Show/hide sidebar', 'icon' => '📐'],
            ['category' => 'View', 'name' => 'Toggle Terminal', 'shortcut' => 'Ctrl+`', 'description' => 'Show/hide terminal', 'icon' => '💻'],
            ['category' => 'View', 'name' => 'Zoom In', 'shortcut' => 'Ctrl++', 'description' => 'Increase zoom level', 'icon' => '🔍'],
            ['category' => 'View', 'name' => 'Zoom Out', 'shortcut' => 'Ctrl+-', 'description' => 'Decrease zoom level', 'icon' => '🔍'],
            ['category' => 'View', 'name' => 'Reset Zoom', 'shortcut' => 'Ctrl+0', 'description' => 'Reset to default zoom', 'icon' => '🔍'],
            ['category' => 'View', 'name' => 'Toggle Full Screen', 'shortcut' => 'F11', 'description' => 'Enter/exit full screen', 'icon' => '🖥️'],

            // Navigation
            ['category' => 'Go', 'name' => 'Go to Line...', 'shortcut' => 'Ctrl+G', 'description' => 'Jump to specific line', 'icon' => '➡️'],
            ['category' => 'Go', 'name' => 'Go to Symbol...', 'shortcut' => 'Ctrl+Shift+O', 'description' => 'Navigate to symbol', 'icon' => '🎯'],
            ['category' => 'Go', 'name' => 'Go to Definition', 'shortcut' => 'F12', 'description' => 'Jump to definition', 'icon' => '📍'],
            ['category' => 'Go', 'name' => 'Go Back', 'shortcut' => 'Alt+←', 'description' => 'Navigate back', 'icon' => '⬅️'],
            ['category' => 'Go', 'name' => 'Go Forward', 'shortcut' => 'Alt+→', 'description' => 'Navigate forward', 'icon' => '➡️'],

            // Terminal
            ['category' => 'Terminal', 'name' => 'New Terminal', 'shortcut' => 'Ctrl+Shift+`', 'description' => 'Create new terminal', 'icon' => '💻'],
            ['category' => 'Terminal', 'name' => 'Run Task...', 'shortcut' => 'Ctrl+Shift+B', 'description' => 'Execute build task', 'icon' => '▶️'],
            ['category' => 'Terminal', 'name' => 'Kill Terminal', 'shortcut' => 'Ctrl+Shift+X', 'description' => 'Terminate current terminal', 'icon' => '⛔'],

            // Settings
            ['category' => 'Preferences', 'name' => 'Settings', 'shortcut' => 'Ctrl+,', 'description' => 'Open settings', 'icon' => '⚙️'],
            ['category' => 'Preferences', 'name' => 'Keyboard Shortcuts', 'shortcut' => 'Ctrl+K S', 'description' => 'Configure shortcuts', 'icon' => '⌨️'],
            ['category' => 'Preferences', 'name' => 'Color Theme', 'shortcut' => 'Ctrl+K T', 'description' => 'Change color theme', 'icon' => '🎨'],
            ['category' => 'Preferences', 'name' => 'Extensions', 'shortcut' => 'Ctrl+Shift+X', 'description' => 'Manage extensions', 'icon' => '🧩'],
        ];

        // Add some recently used commands
        $this->recentCommands = ['Save', 'Find', 'Go to Line...', 'Toggle Terminal'];
    }

    private function renderBackground(): void
    {
        // Main UI background
        for ($row = 1; $row <= 30; $row++) {
            moveCursor($row, 1);
            echo DIM . str_repeat('·', 100) . RESET;
        }

        // Sample editor content
        moveCursor(5, 10);
        echo C_TEXT . 'class CommandPalette {' . RESET;
        moveCursor(6, 10);
        echo C_TEXT . '    private array $commands = [];' . RESET;
        moveCursor(7, 10);
        echo C_TEXT . "    private string \$searchQuery = '';" . RESET;
        moveCursor(8, 10);
        echo C_TEXT . '' . RESET;
        moveCursor(9, 10);
        echo C_TEXT . '    public function search($query) {' . RESET;
        moveCursor(10, 10);
        echo C_TEXT . '        // Fuzzy search implementation' . RESET;
        moveCursor(11, 10);
        echo C_TEXT . '    }' . RESET;
        moveCursor(12, 10);
        echo C_TEXT . '}' . RESET;
    }

    private function renderPalette(): void
    {
        $width = 60;
        $height = $this->maxVisible + 4;
        $startCol = 20;
        $startRow = 8;

        // Shadow effect
        for ($row = 1; $row < $height; $row++) {
            moveCursor($startRow + $row, $startCol + 2);
            echo "\033[48;5;234m" . str_repeat(' ', $width) . RESET;
        }

        // Palette background
        for ($row = 0; $row < $height; $row++) {
            moveCursor($startRow + $row, $startCol);
            echo C_BACKGROUND . str_repeat(' ', $width) . RESET;
        }

        // Border
        $this->renderBorder($startRow, $startCol, $width, $height);

        // Search input
        moveCursor($startRow + 1, $startCol + 2);
        echo C_INPUT . BOLD . '>' . RESET . ' ';
        echo C_INPUT . $this->searchQuery . RESET;

        // Cursor animation
        if ($this->frame % 10 < 5) {
            echo C_INPUT . '│' . RESET;
        }

        // Results count
        moveCursor($startRow + 1, $startCol + $width - 15);
        echo C_DESCRIPTION . count($this->filteredCommands) . ' results' . RESET;

        // Separator
        moveCursor($startRow + 2, $startCol);
        echo C_BACKGROUND . '├' . str_repeat('─', $width - 2) . '┤' . RESET;

        // Commands list
        $this->renderCommandsList($startRow + 3, $startCol + 2, $width - 4);

        // Scroll indicator
        if (count($this->filteredCommands) > $this->maxVisible) {
            $this->renderScrollIndicator($startRow + 3, $startCol + $width - 2);
        }
    }

    private function renderCommandsList(int $row, int $col, int $width): void
    {
        $visibleCommands = array_slice($this->filteredCommands, $this->scrollOffset, $this->maxVisible);

        foreach ($visibleCommands as $i => $command) {
            $isSelected = ($i + $this->scrollOffset) === $this->selectedIndex;

            moveCursor($row + $i, $col - 1);

            if ($isSelected) {
                echo C_SELECTED . str_repeat(' ', $width + 2) . RESET;
                moveCursor($row + $i, $col);
            }

            // Icon
            echo $command['icon'] . ' ';

            // Category
            echo C_CATEGORY . '[' . $command['category'] . ']' . RESET . ' ';

            // Command name with highlighting
            $this->renderHighlightedText($command['name'], $this->searchQuery, $isSelected);

            // Shortcut
            $shortcutCol = $col + $width - mb_strlen($command['shortcut']) - 2;
            moveCursor($row + $i, $shortcutCol);
            echo C_SHORTCUT . $command['shortcut'] . RESET;

            // Description (only for selected)
            if ($isSelected && ! empty($command['description'])) {
                moveCursor($row + $i + 1, $col + 3);
                echo C_DESCRIPTION . $command['description'] . RESET;
            }
        }
    }

    private function renderHighlightedText(string $text, string $query, bool $isSelected): void
    {
        if (empty($query)) {
            echo C_TEXT . $text . RESET;

            return;
        }

        $lowerText = mb_strtolower($text);
        $lowerQuery = mb_strtolower($query);
        $queryChars = mb_str_split($lowerQuery);
        $queryIndex = 0;

        for ($i = 0; $i < mb_strlen($text); $i++) {
            $char = $text[$i];

            if ($queryIndex < count($queryChars) &&
                mb_strtolower($char) === $queryChars[$queryIndex]) {
                echo ($isSelected ? BOLD : '') . C_MATCH . $char . RESET;
                $queryIndex++;
            } else {
                echo C_TEXT . $char . RESET;
            }
        }
    }

    private function renderBorder(int $row, int $col, int $width, int $height): void
    {
        // Top border
        moveCursor($row, $col);
        echo C_BACKGROUND . '╭' . str_repeat('─', $width - 2) . '╮' . RESET;

        // Side borders
        for ($i = 1; $i < $height - 1; $i++) {
            moveCursor($row + $i, $col);
            echo C_BACKGROUND . '│' . RESET;
            moveCursor($row + $i, $col + $width - 1);
            echo C_BACKGROUND . '│' . RESET;
        }

        // Bottom border
        moveCursor($row + $height - 1, $col);
        echo C_BACKGROUND . '╰' . str_repeat('─', $width - 2) . '╯' . RESET;
    }

    private function renderScrollIndicator(int $row, int $col): void
    {
        $totalHeight = $this->maxVisible;
        $scrollbarHeight = max(1, (int) (($this->maxVisible / count($this->filteredCommands)) * $totalHeight));
        $scrollbarPos = (int) (($this->scrollOffset / (count($this->filteredCommands) - $this->maxVisible)) * ($totalHeight - $scrollbarHeight));

        for ($i = 0; $i < $totalHeight; $i++) {
            moveCursor($row + $i, $col);

            if ($i >= $scrollbarPos && $i < $scrollbarPos + $scrollbarHeight) {
                echo "\033[38;5;245m" . '█' . RESET;
            } else {
                echo DIM . '│' . RESET;
            }
        }
    }

    private function renderClosedState(): void
    {
        // Show hint to open palette
        moveCursor(15, 35);
        echo C_BACKGROUND . '                              ' . RESET;
        moveCursor(16, 35);
        echo C_BACKGROUND . '  Press ' . C_SHORTCUT . 'Ctrl+Shift+P' . C_TEXT . ' to open  ' . RESET;
        moveCursor(17, 35);
        echo C_BACKGROUND . '     Command Palette          ' . RESET;
        moveCursor(18, 35);
        echo C_BACKGROUND . '                              ' . RESET;

        // Recent commands hint
        if (! empty($this->recentCommands)) {
            moveCursor(20, 30);
            echo C_DESCRIPTION . 'Recent: ' . implode(', ', array_slice($this->recentCommands, 0, 3)) . RESET;
        }
    }

    private function filterCommands(): void
    {
        if (empty($this->searchQuery)) {
            $this->filteredCommands = $this->commands;

            return;
        }

        $this->filteredCommands = [];
        $query = mb_strtolower($this->searchQuery);

        foreach ($this->commands as $command) {
            // Fuzzy match on name, category, and description
            $searchText = mb_strtolower($command['name'] . ' ' . $command['category'] . ' ' . $command['description']);

            if ($this->fuzzyMatch($query, $searchText)) {
                $this->filteredCommands[] = $command;
            }
        }

        // Reset selection if needed
        if ($this->selectedIndex >= count($this->filteredCommands)) {
            $this->selectedIndex = 0;
        }
    }

    private function fuzzyMatch(string $query, string $text): bool
    {
        $queryChars = mb_str_split($query);
        $lastPos = -1;

        foreach ($queryChars as $char) {
            $pos = mb_strpos($text, $char, $lastPos + 1);
            if ($pos === false) {
                return false;
            }
            $lastPos = $pos;
        }

        return true;
    }

    private function adjustScroll(): void
    {
        if ($this->selectedIndex < $this->scrollOffset) {
            $this->scrollOffset = $this->selectedIndex;
        } elseif ($this->selectedIndex >= $this->scrollOffset + $this->maxVisible) {
            $this->scrollOffset = $this->selectedIndex - $this->maxVisible + 1;
        }
    }
}

// Main execution
echo HIDE_CURSOR;

$palette = new CommandPalette;

while (true) {
    $palette->render();
    usleep(100000); // 100ms refresh
}
