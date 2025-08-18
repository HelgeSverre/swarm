#!/usr/bin/env php
<?php

/**
 * Demo 17: Tab Interface
 * Multi-tab interface with animated transitions
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const REVERSE = "\033[7m";
const CLEAR = "\033[2J\033[H";

// Tab colors
const C_ACTIVE_TAB = "\033[48;5;238m\033[38;5;255m";
const C_INACTIVE_TAB = "\033[48;5;234m\033[38;5;245m";
const C_TAB_BORDER = "\033[38;5;240m";
const C_CONTENT = "\033[38;5;250m";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class TabInterface
{
    private array $tabs = [];

    private int $activeTab = 0;

    private int $frame = 0;

    private array $tabContent = [];

    public function __construct()
    {
        $this->initializeTabs();
    }

    public function render(): void
    {
        echo CLEAR;

        // Render tab bar
        $this->renderTabBar();

        // Render active tab content with animation
        $this->renderTabContent();

        // Render status bar
        $this->renderStatusBar();

        // Simulate tab switching
        if ($this->frame % 60 === 0) {
            $this->activeTab = ($this->activeTab + 1) % count($this->tabs);
        }

        $this->frame++;
    }

    private function initializeTabs(): void
    {
        $this->tabs = [
            ['id' => 'overview', 'title' => '📊 Overview', 'icon' => '📊'],
            ['id' => 'editor', 'title' => '✏️ Editor', 'icon' => '✏️'],
            ['id' => 'terminal', 'title' => '💻 Terminal', 'icon' => '💻'],
            ['id' => 'settings', 'title' => '⚙️ Settings', 'icon' => '⚙️'],
            ['id' => 'help', 'title' => '❓ Help', 'icon' => '❓'],
        ];

        $this->tabContent = [
            'overview' => $this->getOverviewContent(),
            'editor' => $this->getEditorContent(),
            'terminal' => $this->getTerminalContent(),
            'settings' => $this->getSettingsContent(),
            'help' => $this->getHelpContent(),
        ];
    }

    private function renderTabBar(): void
    {
        $width = 100;
        $tabWidth = (int) ($width / count($this->tabs));

        // Tab bar background
        moveCursor(1, 1);
        echo "\033[48;5;234m" . str_repeat(' ', $width) . RESET;
        moveCursor(2, 1);
        echo "\033[48;5;234m" . str_repeat(' ', $width) . RESET;
        moveCursor(3, 1);
        echo "\033[48;5;234m" . str_repeat(' ', $width) . RESET;

        // Render each tab
        $col = 1;
        foreach ($this->tabs as $i => $tab) {
            $isActive = $i === $this->activeTab;

            // Tab top border
            moveCursor(1, $col);
            if ($isActive) {
                echo C_TAB_BORDER . '╭' . str_repeat('─', $tabWidth - 2) . '╮' . RESET;
            }

            // Tab content
            moveCursor(2, $col);
            if ($isActive) {
                echo C_ACTIVE_TAB;
                echo '│ ' . BOLD . mb_str_pad($tab['title'], $tabWidth - 4, ' ', STR_PAD_BOTH) . ' │';
                echo RESET;
            } else {
                echo C_INACTIVE_TAB;
                echo '  ' . mb_str_pad($tab['title'], $tabWidth - 4, ' ', STR_PAD_BOTH) . '  ';
                echo RESET;
            }

            // Tab bottom (connects to content area)
            moveCursor(3, $col);
            if ($isActive) {
                echo C_TAB_BORDER . '│' . C_ACTIVE_TAB . str_repeat(' ', $tabWidth - 2) . C_TAB_BORDER . '│' . RESET;
            } else {
                echo C_TAB_BORDER . '─' . str_repeat('─', $tabWidth - 2) . '─' . RESET;
            }

            $col += $tabWidth;
        }

        // Content area top border (except under active tab)
        moveCursor(4, 1);
        $col = 1;
        foreach ($this->tabs as $i => $tab) {
            if ($i === $this->activeTab) {
                echo C_TAB_BORDER . '│' . RESET;
                echo str_repeat(' ', $tabWidth - 2);
                echo C_TAB_BORDER . '│' . RESET;
            } else {
                echo C_TAB_BORDER . str_repeat('─', $tabWidth) . RESET;
            }
            $col += $tabWidth;
        }
    }

    private function renderTabContent(): void
    {
        $contentArea = ['row' => 5, 'height' => 20, 'width' => 98];
        $activeTabId = $this->tabs[$this->activeTab]['id'];
        $content = $this->tabContent[$activeTabId];

        // Content border
        for ($row = 0; $row < $contentArea['height']; $row++) {
            moveCursor($contentArea['row'] + $row, 1);
            echo C_TAB_BORDER . '│' . RESET;

            // Content
            if ($row < count($content)) {
                echo ' ' . C_CONTENT . mb_str_pad($content[$row], $contentArea['width'] - 2) . RESET;
            } else {
                echo str_repeat(' ', $contentArea['width']);
            }

            moveCursor($contentArea['row'] + $row, 100);
            echo C_TAB_BORDER . '│' . RESET;
        }

        // Bottom border
        moveCursor($contentArea['row'] + $contentArea['height'], 1);
        echo C_TAB_BORDER . '└' . str_repeat('─', 98) . '┘' . RESET;
    }

    private function renderStatusBar(): void
    {
        moveCursor(27, 1);
        echo "\033[48;5;236m" . str_repeat(' ', 100) . RESET;

        moveCursor(27, 2);
        echo "\033[48;5;236m";
        echo C_CONTENT . 'Tab ' . ($this->activeTab + 1) . '/' . count($this->tabs);
        echo ' │ ' . $this->tabs[$this->activeTab]['title'];
        echo ' │ Use ← → to switch tabs';
        echo str_repeat(' ', 50);
        echo RESET;
    }

    private function getOverviewContent(): array
    {
        return [
            BOLD . 'System Overview' . RESET,
            '',
            '═══════════════════════════════════════════════════════════════',
            '',
            '📈 Performance Metrics:',
            '  • CPU Usage:     ████████░░░░░░░░  45%',
            '  • Memory:        ██████████████░░  78%',
            '  • Disk:          ████░░░░░░░░░░░░  23%',
            '  • Network I/O:   ██████░░░░░░░░░░  34%',
            '',
            '📊 Statistics:',
            '  • Uptime:        24 days, 3 hours',
            '  • Processes:     142 running',
            '  • Load Average:  0.75, 0.82, 0.91',
            '',
            '🔔 Recent Activity:',
            '  [12:34] System backup completed',
            "  [12:30] User 'admin' logged in",
            "  [12:15] Service 'nginx' restarted",
        ];
    }

    private function getEditorContent(): array
    {
        $code = <<<'CODE'
<?php
class TabInterface {
    private array $tabs = [];
    private int $activeTab = 0;
    
    public function switchTab(int $index): void {
        if ($index >= 0 && $index < count($this->tabs)) {
            $this->activeTab = $index;
            $this->render();
        }
    }
    
    public function addTab(string $title, array $content): void {
        $this->tabs[] = [
            'title' => $title,
            'content' => $content,
            'created' => time()
        ];
    }
}
CODE;

        $lines = [
            BOLD . 'Code Editor' . RESET,
            'File: TabInterface.php │ PHP │ UTF-8',
            '═══════════════════════════════════════════════════════════════',
        ];

        foreach (explode("\n", $code) as $i => $line) {
            $lineNum = sprintf('%3d ', $i + 1);
            $lines[] = DIM . $lineNum . RESET . $this->highlightSyntax($line);
        }

        return $lines;
    }

    private function getTerminalContent(): array
    {
        return [
            BOLD . 'Terminal Output' . RESET,
            '',
            '$ ls -la',
            'total 48',
            'drwxr-xr-x  6 user  staff   192 Nov 10 12:30 .',
            'drwxr-xr-x  8 user  staff   256 Nov 10 11:00 ..',
            '-rw-r--r--  1 user  staff  1234 Nov 10 12:30 demo.php',
            '-rw-r--r--  1 user  staff  5678 Nov 10 12:25 config.json',
            'drwxr-xr-x  3 user  staff    96 Nov 10 12:00 src/',
            '',
            '$ php demo.php',
            "\033[32m✓\033[0m Application started successfully",
            "\033[33m⚠\033[0m Warning: Config file using defaults",
            'Processing... ████████████░░░░░░░░ 60%',
            '',
            '$ tail -f application.log',
            '[2024-11-10 12:30:15] INFO: Request received',
            '[2024-11-10 12:30:16] DEBUG: Processing data',
            '[2024-11-10 12:30:17] INFO: Response sent',
        ];
    }

    private function getSettingsContent(): array
    {
        return [
            BOLD . 'Settings' . RESET,
            '',
            '═══════════════════════════════════════════════════════════════',
            '',
            '⚙️ General Settings:',
            '  [✓] Enable auto-save',
            '  [✓] Show line numbers',
            '  [ ] Enable word wrap',
            '  [✓] Syntax highlighting',
            '',
            '🎨 Theme Settings:',
            '  Theme:        (•) Dark  ( ) Light  ( ) Auto',
            '  Font Size:    14px [－][＋]',
            '  Tab Width:    4 spaces',
            '',
            '🔧 Advanced:',
            '  Terminal:     /bin/bash',
            '  Encoding:     UTF-8',
            '  Line Ending:  LF',
        ];
    }

    private function getHelpContent(): array
    {
        return [
            BOLD . 'Help & Documentation' . RESET,
            '',
            '═══════════════════════════════════════════════════════════════',
            '',
            '📚 Quick Start Guide:',
            '',
            '1. Navigation:',
            '   • Use ← → arrow keys to switch between tabs',
            '   • Press number keys (1-5) to jump to specific tab',
            '   • Tab key cycles through tabs',
            '',
            '2. Keyboard Shortcuts:',
            '   • Ctrl+T    New tab',
            '   • Ctrl+W    Close tab',
            '   • Ctrl+Tab  Next tab',
            '   • Alt+[1-9] Switch to tab N',
            '',
            '3. Support:',
            '   • Documentation: https://example.com/docs',
            '   • Report issues: https://example.com/issues',
        ];
    }

    private function highlightSyntax(string $code): string
    {
        // PHP syntax highlighting
        $keywords = ['class', 'private', 'public', 'function', 'array', 'int', 'void', 'if', 'return', 'time'];

        foreach ($keywords as $keyword) {
            $code = preg_replace(
                '/\b(' . $keyword . ')\b/',
                "\033[38;5;141m$1\033[38;5;250m",
                $code
            );
        }

        // Variables
        $code = preg_replace('/(\$\w+)/', "\033[38;5;117m$1\033[38;5;250m", $code);

        // Strings
        $code = preg_replace("/('([^']*)')/", "\033[38;5;221m$1\033[38;5;250m", $code);

        return $code;
    }
}

// Main execution
$tabInterface = new TabInterface;

while (true) {
    $tabInterface->render();
    usleep(100000); // 100ms refresh
}
