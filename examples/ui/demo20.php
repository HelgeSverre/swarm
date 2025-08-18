#!/usr/bin/env php
<?php

/**
 * Demo 20: Split Terminal Windows
 * Multiple panes with resizable splits and focused interactions
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const REVERSE = "\033[7m";
const CLEAR = "\033[2J\033[H";
const HIDE_CURSOR = "\033[?25l";

// Window colors
const C_BORDER_ACTIVE = "\033[38;5;117m";
const C_BORDER_INACTIVE = "\033[38;5;240m";
const C_TITLE_ACTIVE = "\033[48;5;238m\033[38;5;255m";
const C_TITLE_INACTIVE = "\033[48;5;234m\033[38;5;245m";
const C_CONTENT = "\033[38;5;250m";
const C_SPLIT_HANDLE = "\033[38;5;245m";
const C_STATUS = "\033[48;5;236m\033[38;5;250m";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class SplitTerminal
{
    private array $panes = [];

    private int $activePane = 0;

    private int $frame = 0;

    private array $layouts = ['horizontal', 'vertical', 'quad', 'triple'];

    private string $currentLayout = 'quad';

    private array $splitPositions = [];

    private bool $isResizing = false;

    private int $terminalWidth = 120;

    private int $terminalHeight = 35;

    public function __construct()
    {
        $this->initializePanes();
        $this->calculateSplits();
    }

    public function render(): void
    {
        echo CLEAR;

        // Render each pane based on layout
        foreach ($this->splitPositions as $i => $pos) {
            if ($i < count($this->panes)) {
                $this->renderPane($this->panes[$i], $pos, $i === $this->activePane);
            }
        }

        // Render split handles
        $this->renderSplitHandles();

        // Render status bar
        $this->renderStatusBar();

        // Simulate interactions
        $this->simulateInteractions();

        $this->frame++;
    }

    private function initializePanes(): void
    {
        $this->panes = [
            [
                'id' => 1,
                'title' => 'Editor',
                'content' => $this->getEditorContent(),
                'type' => 'editor',
                'scrollPos' => 0,
            ],
            [
                'id' => 2,
                'title' => 'Terminal',
                'content' => $this->getTerminalContent(),
                'type' => 'terminal',
                'scrollPos' => 0,
            ],
            [
                'id' => 3,
                'title' => 'Output',
                'content' => $this->getOutputContent(),
                'type' => 'output',
                'scrollPos' => 0,
            ],
            [
                'id' => 4,
                'title' => 'File Explorer',
                'content' => $this->getFileExplorerContent(),
                'type' => 'explorer',
                'scrollPos' => 0,
            ],
        ];
    }

    private function calculateSplits(): void
    {
        switch ($this->currentLayout) {
            case 'horizontal':
                $this->splitPositions = [
                    ['row' => 2, 'col' => 2, 'width' => 116, 'height' => 15],
                    ['row' => 18, 'col' => 2, 'width' => 116, 'height' => 15],
                ];
                break;
            case 'vertical':
                $this->splitPositions = [
                    ['row' => 2, 'col' => 2, 'width' => 57, 'height' => 31],
                    ['row' => 2, 'col' => 60, 'width' => 58, 'height' => 31],
                ];
                break;
            case 'quad':
                $this->splitPositions = [
                    ['row' => 2, 'col' => 2, 'width' => 57, 'height' => 15],
                    ['row' => 2, 'col' => 60, 'width' => 58, 'height' => 15],
                    ['row' => 18, 'col' => 2, 'width' => 57, 'height' => 15],
                    ['row' => 18, 'col' => 60, 'width' => 58, 'height' => 15],
                ];
                break;
            case 'triple':
                $this->splitPositions = [
                    ['row' => 2, 'col' => 2, 'width' => 57, 'height' => 31],
                    ['row' => 2, 'col' => 60, 'width' => 58, 'height' => 15],
                    ['row' => 18, 'col' => 60, 'width' => 58, 'height' => 15],
                ];
                break;
        }
    }

    private function renderPane(array $pane, array $pos, bool $isActive): void
    {
        $borderColor = $isActive ? C_BORDER_ACTIVE : C_BORDER_INACTIVE;
        $titleColor = $isActive ? C_TITLE_ACTIVE : C_TITLE_INACTIVE;

        // Top border with title
        moveCursor($pos['row'], $pos['col']);
        echo $borderColor . '╭' . str_repeat('─', $pos['width'] - 2) . '╮' . RESET;

        // Title bar
        moveCursor($pos['row'] + 1, $pos['col']);
        echo $borderColor . '│' . RESET;
        echo $titleColor . ' ' . $this->getPaneIcon($pane['type']) . ' ' .
             mb_str_pad($pane['title'], $pos['width'] - 6) . ' ' . RESET;
        echo $borderColor . '│' . RESET;

        // Separator
        moveCursor($pos['row'] + 2, $pos['col']);
        echo $borderColor . '├' . str_repeat('─', $pos['width'] - 2) . '┤' . RESET;

        // Content area
        $contentHeight = $pos['height'] - 4;
        for ($i = 0; $i < $contentHeight; $i++) {
            moveCursor($pos['row'] + 3 + $i, $pos['col']);
            echo $borderColor . '│' . RESET;

            // Content
            if (isset($pane['content'][$pane['scrollPos'] + $i])) {
                $line = $pane['content'][$pane['scrollPos'] + $i];
                echo ' ' . $this->formatContent($line, $pane['type'], $pos['width'] - 4);
            } else {
                echo str_repeat(' ', $pos['width'] - 2);
            }

            moveCursor($pos['row'] + 3 + $i, $pos['col'] + $pos['width'] - 1);
            echo $borderColor . '│' . RESET;
        }

        // Bottom border
        moveCursor($pos['row'] + $pos['height'] - 1, $pos['col']);
        echo $borderColor . '╰' . str_repeat('─', $pos['width'] - 2) . '╯' . RESET;

        // Focus indicator
        if ($isActive) {
            $this->renderFocusIndicator($pos);
        }

        // Scrollbar
        if (count($pane['content']) > $contentHeight) {
            $this->renderScrollbar($pos, $pane['scrollPos'], count($pane['content']), $contentHeight);
        }
    }

    private function renderFocusIndicator(array $pos): void
    {
        // Animated corner indicators
        if ($this->frame % 20 < 10) {
            moveCursor($pos['row'], $pos['col']);
            echo C_BORDER_ACTIVE . BOLD . '◆' . RESET;
            moveCursor($pos['row'], $pos['col'] + $pos['width'] - 1);
            echo C_BORDER_ACTIVE . BOLD . '◆' . RESET;
        }
    }

    private function renderScrollbar(array $pos, int $scrollPos, int $contentCount, int $viewHeight): void
    {
        $scrollbarHeight = $viewHeight;
        $thumbSize = max(1, (int) (($viewHeight / $contentCount) * $scrollbarHeight));
        $thumbPos = (int) (($scrollPos / ($contentCount - $viewHeight)) * ($scrollbarHeight - $thumbSize));

        for ($i = 0; $i < $scrollbarHeight; $i++) {
            moveCursor($pos['row'] + 3 + $i, $pos['col'] + $pos['width'] - 2);

            if ($i >= $thumbPos && $i < $thumbPos + $thumbSize) {
                echo "\033[38;5;245m" . '█' . RESET;
            } else {
                echo DIM . '│' . RESET;
            }
        }
    }

    private function renderSplitHandles(): void
    {
        if ($this->currentLayout === 'horizontal') {
            // Horizontal split handle
            moveCursor(17, 2);
            echo C_SPLIT_HANDLE . str_repeat($this->isResizing ? '═' : '─', 116) . RESET;
            moveCursor(17, 58);
            echo C_SPLIT_HANDLE . ($this->isResizing ? '◈' : '○') . RESET;
        } elseif ($this->currentLayout === 'vertical') {
            // Vertical split handle
            for ($row = 2; $row < 33; $row++) {
                moveCursor($row, 59);
                echo C_SPLIT_HANDLE . ($this->isResizing ? '║' : '│') . RESET;
            }
            moveCursor(17, 59);
            echo C_SPLIT_HANDLE . ($this->isResizing ? '◈' : '○') . RESET;
        } elseif ($this->currentLayout === 'quad') {
            // Both handles
            moveCursor(17, 2);
            echo C_SPLIT_HANDLE . str_repeat('─', 116) . RESET;
            for ($row = 2; $row < 33; $row++) {
                moveCursor($row, 59);
                echo C_SPLIT_HANDLE . '│' . RESET;
            }
            moveCursor(17, 59);
            echo C_SPLIT_HANDLE . '┼' . RESET;
        }
    }

    private function renderStatusBar(): void
    {
        moveCursor(34, 1);
        echo C_STATUS . str_repeat(' ', 120) . RESET;

        moveCursor(34, 2);
        echo C_STATUS;

        // Layout indicator
        echo ' Layout: ' . BOLD . ucfirst($this->currentLayout) . RESET . C_STATUS;

        // Active pane
        echo ' │ Active: ' . BOLD . $this->panes[$this->activePane]['title'] . RESET . C_STATUS;

        // Controls
        echo ' │ [Tab] Switch Pane │ [L] Change Layout │ [R] Resize';

        // Time
        $time = date('H:i:s');
        moveCursor(34, 110);
        echo $time . ' ';

        echo RESET;
    }

    private function getPaneIcon(string $type): string
    {
        return match ($type) {
            'editor' => '📝',
            'terminal' => '💻',
            'output' => '📤',
            'explorer' => '📁',
            default => '📄'
        };
    }

    private function formatContent(string $line, string $type, int $width): string
    {
        $formatted = match ($type) {
            'editor' => $this->highlightCode($line),
            'terminal' => $this->formatTerminalLine($line),
            'output' => C_CONTENT . $line . RESET,
            'explorer' => $this->formatFileExplorerLine($line),
            default => $line
        };

        // Truncate if too long
        $plainLine = preg_replace('/\033\[[0-9;]*m/', '', $formatted);
        if (mb_strlen($plainLine) > $width) {
            $formatted = mb_substr($plainLine, 0, $width - 3) . '...';
        }

        return mb_str_pad($formatted, $width + (mb_strlen($formatted) - mb_strlen($plainLine)));
    }

    private function highlightCode(string $code): string
    {
        // Simple syntax highlighting
        $keywords = ['class', 'function', 'private', 'public', 'return', 'if', 'else'];

        foreach ($keywords as $keyword) {
            $code = preg_replace(
                '/\b(' . $keyword . ')\b/',
                "\033[38;5;141m$1\033[38;5;250m",
                $code
            );
        }

        // Strings
        $code = preg_replace('/"([^"]*)"/', "\033[38;5;221m\"$1\"\033[38;5;250m", $code);

        // Comments
        $code = preg_replace('/(\/\/.*)/', "\033[38;5;245m$1\033[38;5;250m", $code);

        return C_CONTENT . $code . RESET;
    }

    private function formatTerminalLine(string $line): string
    {
        if (str_starts_with($line, '$')) {
            return "\033[38;5;82m" . $line . RESET;
        } elseif (str_contains($line, 'error')) {
            return "\033[38;5;203m" . $line . RESET;
        } elseif (str_contains($line, 'warning')) {
            return "\033[38;5;221m" . $line . RESET;
        }

        return C_CONTENT . $line . RESET;
    }

    private function formatFileExplorerLine(string $line): string
    {
        if (str_starts_with($line, '├') || str_starts_with($line, '└')) {
            return DIM . mb_substr($line, 0, 3) . RESET . C_CONTENT . mb_substr($line, 3) . RESET;
        }

        return C_CONTENT . $line . RESET;
    }

    private function simulateInteractions(): void
    {
        // Switch active pane
        if ($this->frame % 50 === 0) {
            $this->activePane = ($this->activePane + 1) % count($this->splitPositions);
        }

        // Change layout
        if ($this->frame % 200 === 0) {
            $currentIndex = array_search($this->currentLayout, $this->layouts);
            $this->currentLayout = $this->layouts[($currentIndex + 1) % count($this->layouts)];
            $this->calculateSplits();
        }

        // Simulate resizing
        if ($this->frame % 100 === 0) {
            $this->isResizing = ! $this->isResizing;
        }

        // Scroll content
        foreach ($this->panes as &$pane) {
            if ($this->frame % 30 === 0 && rand(0, 2) === 0) {
                $maxScroll = max(0, count($pane['content']) - 10);
                $pane['scrollPos'] = min($maxScroll, $pane['scrollPos'] + rand(-2, 3));
                $pane['scrollPos'] = max(0, $pane['scrollPos']);
            }
        }

        // Update dynamic content
        if ($this->frame % 10 === 0) {
            $this->updateDynamicContent();
        }
    }

    private function updateDynamicContent(): void
    {
        // Update terminal output
        if ($this->frame % 40 === 0) {
            $this->panes[1]['content'][] = '$ Processing... ' . str_repeat('█', (int) ($this->frame / 10) % 10);
        }

        // Update output pane
        if ($this->frame % 20 === 0) {
            $this->panes[2]['content'][] = '[' . date('H:i:s') . '] Event logged';
        }
    }

    private function getEditorContent(): array
    {
        return [
            '  1  <?php',
            '  2  namespace App\\Terminal;',
            '  3  ',
            '  4  class SplitWindow {',
            '  5      private array $panes = [];',
            '  6      private int $activePane = 0;',
            '  7      ',
            '  8      public function __construct() {',
            '  9          $this->initializePanes();',
            ' 10      }',
            ' 11      ',
            ' 12      public function split(string $direction): void {',
            ' 13          // Split the current pane',
            ' 14          $newPane = $this->createPane();',
            ' 15          $this->adjustLayout($direction);',
            ' 16      }',
            ' 17      ',
            ' 18      public function focus(int $paneId): void {',
            ' 19          if (isset($this->panes[$paneId])) {',
            ' 20              $this->activePane = $paneId;',
            ' 21              $this->render();',
            ' 22          }',
            ' 23      }',
            ' 24      ',
            ' 25      private function render(): void {',
            ' 26          foreach ($this->panes as $pane) {',
            ' 27              $this->renderPane($pane);',
            ' 28          }',
            ' 29      }',
            ' 30  }',
        ];
    }

    private function getTerminalContent(): array
    {
        return [
            '$ php artisan serve',
            'Starting Laravel development server: http://127.0.0.1:8000',
            '[' . date('H:i:s') . '] Server started successfully',
            '',
            '$ npm run watch',
            '> project@1.0.0 watch',
            '> vite',
            '',
            '  VITE v4.0.0  ready in 523 ms',
            '',
            '  ➜  Local:   http://localhost:5173/',
            '  ➜  Network: http://192.168.1.100:5173/',
            '',
            '  watching for file changes...',
            '',
            '12:30:15 [vite] page reload src/main.js',
            '12:30:16 [vite] hmr update /src/App.vue',
        ];
    }

    private function getOutputContent(): array
    {
        return [
            '[INFO] Application started',
            '[DEBUG] Loading configuration from .env',
            '[INFO] Database connection established',
            '[DEBUG] Cache cleared successfully',
            '[INFO] Routes compiled: 42 routes registered',
            '[WARNING] Deprecated function used in UserController',
            '[INFO] Background jobs started',
            '[DEBUG] Queue worker spawned (PID: 12345)',
            '[INFO] WebSocket server listening on port 6001',
            '[SUCCESS] All systems operational',
        ];
    }

    private function getFileExplorerContent(): array
    {
        return [
            '📁 project/',
            '├── 📁 src/',
            '│   ├── 📁 Controllers/',
            '│   │   ├── 📄 UserController.php',
            '│   │   └── 📄 ApiController.php',
            '│   ├── 📁 Models/',
            '│   │   ├── 📄 User.php',
            '│   │   └── 📄 Post.php',
            '│   └── 📁 Views/',
            '│       ├── 📄 index.blade.php',
            '│       └── 📄 layout.blade.php',
            '├── 📁 tests/',
            '│   ├── 📄 UserTest.php',
            '│   └── 📄 ApiTest.php',
            '├── 📁 public/',
            '│   ├── 📄 index.php',
            '│   └── 📁 assets/',
            '├── 📄 composer.json',
            '├── 📄 package.json',
            '└── 📄 README.md',
        ];
    }
}

// Main execution
echo HIDE_CURSOR;

$terminal = new SplitTerminal;

while (true) {
    $terminal->render();
    usleep(100000); // 100ms refresh
}
