#!/usr/bin/env php
<?php

/**
 * Split Focus UI - Golden ratio layout with primary/secondary sections
 *
 * Features:
 * - Golden ratio proportions for visual harmony
 * - Primary focus area for active tasks
 * - Secondary area for context
 * - Clean separation with thin lines
 * - No animations, clear visual hierarchy
 */

// ANSI codes
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const UNDERLINE = "\033[4m";

// Terminal control
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";
const CLEAR = "\033[2J";
const HOME = "\033[H";
const ALT_SCREEN_ON = "\033[?1049h";
const ALT_SCREEN_OFF = "\033[?1049l";

// Colors - minimal palette
const FG_WHITE = "\033[97m";
const FG_GRAY = "\033[37m";
const FG_DARK_GRAY = "\033[90m";
const FG_BLACK = "\033[30m";

const FG_RED = "\033[91m";
const FG_GREEN = "\033[92m";
const FG_YELLOW = "\033[93m";
const FG_BLUE = "\033[94m";
const FG_MAGENTA = "\033[95m";
const FG_CYAN = "\033[96m";

const BG_BLACK = "\033[40m";
const BG_DARK_GRAY = "\033[100m";
const BG_LIGHT_GRAY = "\033[47m";

// Line drawing
const LINE_THIN = '─';
const LINE_THICK = '━';
const LINE_VERTICAL = '│';
const LINE_DOTTED = '┈';
const CORNER_TL = '┌';
const CORNER_TR = '┐';
const CORNER_BL = '└';
const CORNER_BR = '┘';
const T_DOWN = '┬';
const T_UP = '┴';
const T_RIGHT = '├';
const T_LEFT = '┤';

class SplitFocusUI
{
    private const GOLDEN_RATIO = 1.618;

    private int $width;

    private int $height;

    private int $sidebarWidth;

    private int $primaryHeight;

    private int $secondaryHeight;

    private bool $running = false;

    private int $frame = 0;

    // UI state
    private array $primaryTasks = [];

    private array $secondaryContext = [];

    private array $activities = [];

    private int $focusedArea = 0; // 0=main, 1=primary, 2=secondary

    private int $selectedItem = 0;

    // Metrics
    private array $stats = [
        'active' => 0,
        'completed' => 0,
        'pending' => 0,
        'success_rate' => 0,
    ];

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->calculateLayout();
        $this->initializeData();

        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        pcntl_signal(SIGWINCH, [$this, 'handleResize']);
    }

    private function setup(): void
    {
        echo ALT_SCREEN_ON . CLEAR . HOME . HIDE_CURSOR;
        system('stty -echo -icanon min 1 time 0 2>/dev/null');
        stream_set_blocking(STDIN, false);
    }

    public function run(): void
    {
        $this->setup();
        $this->running = true;

        while ($this->running) {
            $this->handleInput();
            $this->update();
            $this->render();

            pcntl_signal_dispatch();
            usleep(50000); // 50ms
            $this->frame++;
        }

        $this->cleanup();
    }

    private function cleanup(): void
    {
        echo SHOW_CURSOR . ALT_SCREEN_OFF . RESET;
        system('stty sane');
    }

    private function updateTerminalSize(): void
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;
    }

    private function calculateLayout(): void
    {
        // Sidebar uses golden ratio for width
        $this->sidebarWidth = (int) ($this->width / self::GOLDEN_RATIO / 2);

        // Primary section gets golden ratio of available height
        $availableHeight = $this->height - 4; // Header and footer
        $this->primaryHeight = (int) ($availableHeight / self::GOLDEN_RATIO);
        $this->secondaryHeight = $availableHeight - $this->primaryHeight - 1; // -1 for divider
    }

    private function handleSignal(int $signal): void
    {
        if ($signal === SIGINT) {
            $this->running = false;
        }
    }

    private function handleResize(int $signal): void
    {
        $this->updateTerminalSize();
        $this->calculateLayout();
    }

    private function handleInput(): void
    {
        $input = fread(STDIN, 1024);
        if (! $input) {
            return;
        }

        foreach (mb_str_split($input) as $char) {
            switch ($char) {
                case 'q':
                case 'Q':
                    $this->running = false;
                    break;
                case "\t": // Tab - cycle focus
                    $this->focusedArea = ($this->focusedArea + 1) % 3;
                    $this->selectedItem = 0;
                    break;
                case "\033": // Escape sequences
                    if (mb_strlen($input) >= 3) {
                        $seq = mb_substr($input, 1, 2);
                        if ($seq === '[A') { // Up
                            $this->navigateUp();
                        } elseif ($seq === '[B') { // Down
                            $this->navigateDown();
                        }
                    }
                    break;
                case 'r':
                    $this->addRandomActivity();
                    break;
                case ' ': // Space - toggle task
                    $this->toggleSelectedTask();
                    break;
            }
        }
    }

    private function update(): void
    {
        // Update stats
        $this->updateStats();

        // Add activity periodically
        if ($this->frame % 60 === 0) {
            $this->addRandomActivity();
        }

        // Update task progress
        if ($this->frame % 40 === 0) {
            $this->updateTaskProgress();
        }
    }

    private function render(): void
    {
        echo HOME;

        $this->renderHeader();
        $this->renderMainArea();
        $this->renderSidebar();
        $this->renderFooter();
    }

    private function renderHeader(): void
    {
        $this->moveTo(1, 1);
        echo BG_BLACK . FG_WHITE;

        $title = ' ⧉ Split Focus UI ';
        echo BOLD . $title . RESET . BG_BLACK;

        // Center stats
        $stats = sprintf('Active: %d | Complete: %d | Rate: %.0f%%',
            $this->stats['active'],
            $this->stats['completed'],
            $this->stats['success_rate']
        );

        $centerPos = ($this->width - mb_strlen($stats)) / 2;
        $this->moveTo(1, (int) $centerPos);
        echo BG_BLACK . FG_CYAN . $stats . RESET;

        // Time on right
        $time = date('H:i:s');
        $this->moveTo(1, $this->width - mb_strlen($time));
        echo BG_BLACK . FG_DARK_GRAY . $time . RESET;

        // Separator
        $this->moveTo(2, 1);
        echo FG_DARK_GRAY . str_repeat(LINE_THIN, $this->width) . RESET;
    }

    private function renderMainArea(): void
    {
        $mainWidth = $this->width - $this->sidebarWidth - 1;
        $row = 3;

        // Main content title
        $this->moveTo($row++, 2);
        $focusIndicator = $this->focusedArea === 0 ? FG_CYAN . '▶ ' : '  ';
        echo $focusIndicator . FG_WHITE . BOLD . 'Activity Feed' . RESET;

        $row++;

        // Render activities
        $maxActivities = $this->height - 6;
        $visibleActivities = array_slice($this->activities, 0, $maxActivities);

        foreach ($visibleActivities as $activity) {
            if ($row >= $this->height - 1) {
                break;
            }

            $this->moveTo($row++, 3);
            $this->renderActivityLine($activity, $mainWidth - 4);
        }
    }

    private function renderActivityLine(array $activity, int $maxWidth): void
    {
        $time = date('H:i', $activity['time']);
        echo FG_DARK_GRAY . "[{$time}] " . RESET;

        // Agent with color
        $agentColor = match ($activity['agent']) {
            'Planner' => FG_BLUE,
            'Executor' => FG_GREEN,
            'Validator' => FG_YELLOW,
            'Reporter' => FG_MAGENTA,
            default => FG_CYAN
        };

        echo $agentColor . $activity['agent'] . RESET . ': ';

        // Message
        $message = $activity['message'];
        if (mb_strlen($message) > $maxWidth - 20) {
            $message = mb_substr($message, 0, $maxWidth - 23) . '...';
        }

        $messageColor = match ($activity['type']) {
            'success' => FG_GREEN,
            'error' => FG_RED,
            'info' => FG_WHITE,
            default => FG_GRAY
        };

        echo $messageColor . $message . RESET;
    }

    private function renderSidebar(): void
    {
        $col = $this->width - $this->sidebarWidth + 1;

        // Vertical divider
        for ($row = 3; $row <= $this->height - 2; $row++) {
            $this->moveTo($row, $col - 1);
            echo FG_DARK_GRAY . LINE_VERTICAL . RESET;
        }

        // Render primary section (larger, top)
        $this->renderPrimarySection($col);

        // Horizontal divider between sections
        $dividerRow = 3 + $this->primaryHeight;
        $this->moveTo($dividerRow, $col);
        echo FG_DARK_GRAY . str_repeat(LINE_DOTTED, $this->sidebarWidth - 1) . RESET;

        // Render secondary section (smaller, bottom)
        $this->renderSecondarySection($col, $dividerRow + 1);
    }

    private function renderPrimarySection(int $col): void
    {
        $row = 3;

        // Section header
        $this->moveTo($row++, $col);
        $focusIndicator = $this->focusedArea === 1 ? FG_CYAN . '▶ ' : '  ';
        echo $focusIndicator . FG_WHITE . BOLD . 'Primary Focus' . RESET;

        $this->moveTo($row++, $col + 2);
        echo FG_DARK_GRAY . '(Active Tasks)' . RESET;

        $row++;

        // Render primary tasks
        $maxTasks = $this->primaryHeight - 4;
        $visibleTasks = array_slice($this->primaryTasks, 0, $maxTasks);

        foreach ($visibleTasks as $i => $task) {
            if ($row >= 3 + $this->primaryHeight - 1) {
                break;
            }

            $this->moveTo($row++, $col);

            $isSelected = $this->focusedArea === 1 && $i === $this->selectedItem;

            if ($isSelected) {
                echo BG_DARK_GRAY;
            }

            // Status icon
            $icon = match ($task['status']) {
                'active' => FG_YELLOW . '▸',
                'completed' => FG_GREEN . '✓',
                'blocked' => FG_RED . '⊗',
                default => FG_DARK_GRAY . '○'
            };

            echo $icon . RESET;

            if ($isSelected) {
                echo BG_DARK_GRAY;
            }

            // Task title
            $title = ' ' . $task['title'];
            if (mb_strlen($title) > $this->sidebarWidth - 4) {
                $title = mb_substr($title, 0, $this->sidebarWidth - 7) . '...';
            }

            echo FG_WHITE . $title;

            // Fill rest of line if selected
            if ($isSelected) {
                $padding = $this->sidebarWidth - mb_strlen($title) - 2;
                echo str_repeat(' ', max(0, $padding));
            }

            echo RESET;

            // Show priority or progress
            if ($task['status'] === 'active' && isset($task['progress'])) {
                $row++;
                $this->moveTo($row, $col + 2);
                $this->renderMiniProgress($task['progress'], 15);
            }
        }
    }

    private function renderSecondarySection(int $col, int $startRow): void
    {
        $row = $startRow;

        // Section header
        $this->moveTo($row++, $col);
        $focusIndicator = $this->focusedArea === 2 ? FG_CYAN . '▶ ' : '  ';
        echo $focusIndicator . FG_WHITE . BOLD . 'Secondary' . RESET;

        $this->moveTo($row++, $col + 2);
        echo FG_DARK_GRAY . '(Context & Info)' . RESET;

        $row++;

        // Render context items
        $maxItems = $this->secondaryHeight - 4;
        $visibleItems = array_slice($this->secondaryContext, 0, $maxItems);

        foreach ($visibleItems as $i => $item) {
            if ($row >= $this->height - 2) {
                break;
            }

            $this->moveTo($row++, $col);

            $isSelected = $this->focusedArea === 2 && $i === $this->selectedItem;

            if ($isSelected) {
                echo BG_DARK_GRAY;
            }

            // Item icon
            $icon = match ($item['type']) {
                'file' => FG_BLUE . '◈',
                'note' => FG_YELLOW . '◉',
                'link' => FG_CYAN . '⬡',
                default => FG_DARK_GRAY . '•'
            };

            echo $icon . RESET;

            if ($isSelected) {
                echo BG_DARK_GRAY;
            }

            // Item content
            $content = ' ' . $item['content'];
            if (mb_strlen($content) > $this->sidebarWidth - 4) {
                $content = mb_substr($content, 0, $this->sidebarWidth - 7) . '...';
            }

            echo FG_GRAY . $content;

            // Fill rest if selected
            if ($isSelected) {
                $padding = $this->sidebarWidth - mb_strlen($content) - 2;
                echo str_repeat(' ', max(0, $padding));
            }

            echo RESET;
        }
    }

    private function renderMiniProgress(float $progress, int $width): void
    {
        $filled = (int) ($progress / 100 * $width);

        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                echo FG_GREEN . '▰';
            } else {
                echo FG_DARK_GRAY . '▱';
            }
        }

        echo RESET . FG_DARK_GRAY . sprintf(' %d%%', (int) $progress) . RESET;
    }

    private function renderFooter(): void
    {
        $this->moveTo($this->height - 1, 1);
        echo FG_DARK_GRAY . str_repeat(LINE_THIN, $this->width) . RESET;

        $this->moveTo($this->height, 1);

        // Left: shortcuts
        $shortcuts = '[Tab] Switch  [↑↓] Navigate  [Space] Toggle  [Q] Quit';
        echo FG_DARK_GRAY . $shortcuts . RESET;

        // Right: focus indicator
        $focus = match ($this->focusedArea) {
            0 => 'MAIN',
            1 => 'PRIMARY',
            2 => 'SECONDARY',
            default => ''
        };

        $focusText = "Focus: {$focus}";
        $this->moveTo($this->height, $this->width - mb_strlen($focusText));
        echo FG_CYAN . $focusText . RESET;
    }

    private function moveTo(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    private function navigateUp(): void
    {
        if ($this->selectedItem > 0) {
            $this->selectedItem--;
        }
    }

    private function navigateDown(): void
    {
        $maxItems = match ($this->focusedArea) {
            1 => count($this->primaryTasks) - 1,
            2 => count($this->secondaryContext) - 1,
            default => 0
        };

        if ($this->selectedItem < $maxItems) {
            $this->selectedItem++;
        }
    }

    private function toggleSelectedTask(): void
    {
        if ($this->focusedArea === 1 && isset($this->primaryTasks[$this->selectedItem])) {
            $task = &$this->primaryTasks[$this->selectedItem];

            if ($task['status'] === 'active') {
                $task['status'] = 'completed';
                unset($task['progress']);
            } elseif ($task['status'] === 'completed') {
                $task['status'] = 'active';
                $task['progress'] = 0;
            }
        }
    }

    private function updateStats(): void
    {
        $this->stats['active'] = count(array_filter($this->primaryTasks,
            fn ($t) => $t['status'] === 'active'));
        $this->stats['completed'] = count(array_filter($this->primaryTasks,
            fn ($t) => $t['status'] === 'completed'));
        $this->stats['pending'] = count(array_filter($this->primaryTasks,
            fn ($t) => $t['status'] === 'pending'));

        $total = count($this->primaryTasks);
        if ($total > 0) {
            $this->stats['success_rate'] = ($this->stats['completed'] / $total) * 100;
        }
    }

    private function updateTaskProgress(): void
    {
        foreach ($this->primaryTasks as &$task) {
            if ($task['status'] === 'active' && isset($task['progress'])) {
                $task['progress'] = min(100, $task['progress'] + rand(5, 15));
            }
        }
    }

    private function addRandomActivity(): void
    {
        $agents = ['Planner', 'Executor', 'Validator', 'Reporter'];
        $types = ['success', 'info', 'error'];
        $messages = [
            'Task execution completed',
            'Analyzing dependencies',
            'Validation in progress',
            'Generating report',
        ];

        array_unshift($this->activities, [
            'time' => time(),
            'agent' => $agents[array_rand($agents)],
            'type' => $types[array_rand($types)],
            'message' => $messages[array_rand($messages)],
        ]);

        if (count($this->activities) > 100) {
            array_pop($this->activities);
        }
    }

    private function initializeData(): void
    {
        // Primary tasks (important/active)
        $this->primaryTasks = [
            ['title' => 'Design system architecture', 'status' => 'active', 'progress' => 75],
            ['title' => 'Implement core features', 'status' => 'active', 'progress' => 40],
            ['title' => 'Setup authentication', 'status' => 'completed'],
            ['title' => 'Database schema', 'status' => 'completed'],
            ['title' => 'API integration', 'status' => 'pending'],
            ['title' => 'Testing framework', 'status' => 'blocked'],
        ];

        // Secondary context items
        $this->secondaryContext = [
            ['type' => 'file', 'content' => 'README.md'],
            ['type' => 'file', 'content' => 'config.yaml'],
            ['type' => 'note', 'content' => 'Review security'],
            ['type' => 'note', 'content' => 'Check performance'],
            ['type' => 'link', 'content' => 'Documentation'],
        ];

        // Initial activities
        for ($i = 0; $i < 5; $i++) {
            $this->addRandomActivity();
        }
    }
}

// Run the UI
$ui = new SplitFocusUI;

try {
    $ui->run();
} catch (Exception $e) {
    echo SHOW_CURSOR . ALT_SCREEN_OFF . RESET;
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
