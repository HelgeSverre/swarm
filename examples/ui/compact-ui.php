#!/usr/bin/env php
<?php

/**
 * Compact Information-Dense UI - Maximum Data Display
 *
 * Features:
 * - Narrow sidebar (20% width)
 * - Multi-column activity feed
 * - Tabbed sections in sidebar
 * - Keyboard shortcuts with numbers
 * - Tree view for hierarchical tasks
 * - Minimal decorations
 */

// Core ANSI codes
const ESC = "\033";
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const UNDERLINE = "\033[4m";
const REVERSE = "\033[7m";

// Terminal control
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";
const CLEAR = "\033[2J";
const CLEAR_LINE = "\033[2K";
const HOME = "\033[H";
const ALT_BUFFER_ON = "\033[?1049h";
const ALT_BUFFER_OFF = "\033[?1049l";

// Minimal color palette
const BLACK = "\033[30m";
const RED = "\033[31m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const MAGENTA = "\033[35m";
const CYAN = "\033[36m";
const WHITE = "\033[37m";
const GRAY = "\033[90m";
const BRIGHT_RED = "\033[91m";
const BRIGHT_GREEN = "\033[92m";

// Minimal box drawing
const H_LINE = '─';
const V_LINE = '│';
const CORNER_TL = '┌';
const CORNER_TR = '┐';
const CORNER_BL = '└';
const CORNER_BR = '┘';
const T_JOINT = '├';
const TREE_BRANCH = '├─';
const TREE_LAST = '└─';
const TREE_VERT = '│ ';
const TREE_SPACE = '  ';

class CompactUI
{
    private int $width;

    private int $height;

    private int $sidebarWidth;

    private bool $running = false;

    // UI Layout
    private int $headerLines = 2;

    private int $footerLines = 1;

    private int $mainColumns = 3; // Multi-column activity display

    // Sidebar tabs
    private array $tabs = ['Tasks', 'Files', 'Context', 'Tools'];

    private int $activeTab = 0;

    // Data structures
    private array $tasks = [];

    private array $files = [];

    private array $context = [];

    private array $tools = [];

    private array $activities = [];

    // Navigation state
    private int $selectedItem = 0;

    private array $scrollOffsets = [0, 0, 0, 0]; // Per tab

    private int $activityScroll = 0;

    // Quick access slots (1-9 keys)
    private array $quickSlots = [];

    // Performance
    private int $frame = 0;

    private float $lastRender = 0;

    public function __construct()
    {
        $this->initialize();
    }

    private function setup(): void
    {
        echo ALT_BUFFER_ON . CLEAR . HOME . HIDE_CURSOR;
        system('stty -echo -icanon min 1 time 0 2>/dev/null');
        stream_set_blocking(STDIN, false);
    }

    public function run(): void
    {
        $this->setup();
        $this->running = true;

        while ($this->running) {
            $this->processInput();

            // Throttled rendering for performance
            if (microtime(true) - $this->lastRender > 0.05) { // 20 FPS max
                $this->render();
                $this->lastRender = microtime(true);
            }

            pcntl_signal_dispatch();
            usleep(5000); // 5ms
            $this->frame++;
        }

        $this->cleanup();
    }

    private function initialize(): void
    {
        $this->updateDimensions();
        $this->sidebarWidth = max(25, (int) ($this->width * 0.2)); // 20% width, min 25 chars
        $this->loadDemoData();
        $this->setupSignals();
    }

    private function cleanup(): void
    {
        echo SHOW_CURSOR . ALT_BUFFER_OFF . RESET;
        system('stty sane');
    }

    private function updateDimensions(): void
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;
    }

    private function setupSignals(): void
    {
        pcntl_signal(SIGINT, fn () => $this->running = false);
        pcntl_signal(SIGWINCH, function () {
            $this->updateDimensions();
            $this->sidebarWidth = max(25, (int) ($this->width * 0.2));
        });
    }

    private function processInput(): void
    {
        $input = fread(STDIN, 1024);
        if (! $input) {
            return;
        }

        foreach (mb_str_split($input) as $char) {
            // Quick slot access (1-9)
            if ($char >= '1' && $char <= '9') {
                $slot = (int) $char - 1;
                if (isset($this->quickSlots[$slot])) {
                    $this->executeQuickSlot($slot);
                }

                continue;
            }

            switch ($char) {
                case 'q':
                case "\x03": // Ctrl+C
                    $this->running = false;
                    break;
                case "\t": // Tab - cycle tabs
                    $this->activeTab = ($this->activeTab + 1) % count($this->tabs);
                    $this->selectedItem = 0;
                    break;
                case 'j': // Down
                case "\033[B": // Arrow down
                    $this->navigateDown();
                    break;
                case 'k': // Up
                case "\033[A": // Arrow up
                    $this->navigateUp();
                    break;
                case 'h': // Left - previous tab
                case "\033[D": // Arrow left
                    $this->activeTab = ($this->activeTab - 1 + count($this->tabs)) % count($this->tabs);
                    break;
                case 'l': // Right - next tab
                case "\033[C": // Arrow right
                    $this->activeTab = ($this->activeTab + 1) % count($this->tabs);
                    break;
                case "\n": // Enter - execute selected
                case ' ': // Space - expand/collapse
                    $this->executeSelected();
                    break;
                case '/': // Search mode
                    // Would implement search here
                    break;
                case 'r': // Refresh
                    $this->addRandomActivity();
                    break;
                case 'c': // Clear activities
                    $this->activities = [];
                    break;
                case 'a': // Add test data
                    $this->addTestData();
                    break;
            }
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
        // Compact header - single line
        $this->moveTo(1, 1);
        echo CLEAR_LINE;

        // System name
        echo BOLD . CYAN . 'SWARM' . RESET;

        // Stats in center
        $stats = sprintf(
            ' %d ops | %.1f%% | %dms ',
            count($this->activities),
            $this->getSuccessRate(),
            rand(50, 200)
        );

        $centerPos = ($this->width - mb_strlen($stats)) / 2;
        $this->moveTo(1, (int) $centerPos);
        echo GRAY . $stats . RESET;

        // Time on right
        $time = date('H:i:s');
        $this->moveTo(1, $this->width - mb_strlen($time));
        echo GRAY . $time . RESET;

        // Separator
        $this->moveTo(2, 1);
        echo GRAY . str_repeat(H_LINE, $this->width) . RESET;
    }

    private function renderMainArea(): void
    {
        $mainWidth = $this->width - $this->sidebarWidth - 1;
        $contentHeight = $this->height - $this->headerLines - $this->footerLines;

        // Calculate column dimensions
        $colWidth = (int) ($mainWidth / $this->mainColumns);
        $lastColWidth = $mainWidth - ($colWidth * ($this->mainColumns - 1));

        // Render activities in columns
        $row = $this->headerLines + 1;
        $activitiesPerCol = (int) ($contentHeight / $this->mainColumns);

        $activityIndex = $this->activityScroll;

        for ($col = 0; $col < $this->mainColumns; $col++) {
            $colStart = $col * $colWidth + 1;

            for ($line = 0; $line < $contentHeight; $line++) {
                $this->moveTo($row + $line, $colStart);

                if (isset($this->activities[$activityIndex])) {
                    $this->renderCompactActivity(
                        $this->activities[$activityIndex],
                        $col === $this->mainColumns - 1 ? $lastColWidth : $colWidth
                    );
                    $activityIndex++;
                } else {
                    // Empty line
                    echo str_repeat(' ', $col === $this->mainColumns - 1 ? $lastColWidth : $colWidth);
                }
            }

            // Column separator (except last)
            if ($col < $this->mainColumns - 1) {
                for ($line = 0; $line < $contentHeight; $line++) {
                    $this->moveTo($row + $line, $colStart + $colWidth);
                    echo GRAY . V_LINE . RESET;
                }
            }
        }
    }

    private function renderCompactActivity(array $activity, int $width): void
    {
        // Ultra-compact format: [TIME] AGENT:OP STATUS
        $time = date('H:i', $activity['time']);
        $agent = mb_substr($activity['agent'], 0, 3); // 3-letter abbreviation
        $op = $activity['operation'];

        // Status indicator
        $status = match ($activity['status']) {
            'success' => GREEN . '●',
            'error' => RED . '✗',
            'warning' => YELLOW . '!',
            default => GRAY . '·'
        };

        // Build compact string
        $prefix = sprintf('[%s] %s:', $time, $agent);
        $availableWidth = $width - mb_strlen($prefix) - 4; // Leave room for status

        if (mb_strlen($op) > $availableWidth) {
            $op = mb_substr($op, 0, $availableWidth - 1) . '…';
        }

        echo GRAY . $prefix . RESET;
        echo ' ' . $op;

        // Right-align status
        $currentLen = mb_strlen($prefix) + mb_strlen($op) + 1;
        $padding = $width - $currentLen - 2;
        if ($padding > 0) {
            echo str_repeat(' ', $padding);
        }
        echo ' ' . $status . RESET;
    }

    private function renderSidebar(): void
    {
        $sidebarStart = $this->width - $this->sidebarWidth + 1;

        // Sidebar border
        for ($row = $this->headerLines + 1; $row <= $this->height - $this->footerLines; $row++) {
            $this->moveTo($row, $sidebarStart - 1);
            echo GRAY . V_LINE . RESET;
        }

        // Tab bar
        $this->renderTabBar($sidebarStart);

        // Tab content
        $contentStart = $this->headerLines + 2;
        $contentHeight = $this->height - $this->headerLines - $this->footerLines - 2;

        switch ($this->tabs[$this->activeTab]) {
            case 'Tasks':
                $this->renderTaskTree($contentStart, $sidebarStart, $contentHeight);
                break;
            case 'Files':
                $this->renderFileList($contentStart, $sidebarStart, $contentHeight);
                break;
            case 'Context':
                $this->renderContextInfo($contentStart, $sidebarStart, $contentHeight);
                break;
            case 'Tools':
                $this->renderToolStats($contentStart, $sidebarStart, $contentHeight);
                break;
        }

        // Quick slots at bottom
        $this->renderQuickSlots($this->height - $this->footerLines - 3, $sidebarStart);
    }

    private function renderTabBar(int $startCol): void
    {
        $this->moveTo($this->headerLines + 1, $startCol);

        $tabWidth = (int) (($this->sidebarWidth - 1) / count($this->tabs));

        foreach ($this->tabs as $i => $tab) {
            if ($i === $this->activeTab) {
                echo REVERSE;
            }

            $tabText = mb_substr($tab, 0, $tabWidth - 1);
            echo mb_str_pad($tabText, $tabWidth - 1);

            if ($i === $this->activeTab) {
                echo RESET;
            }

            if ($i < count($this->tabs) - 1) {
                echo GRAY . V_LINE . RESET;
            }
        }
    }

    private function renderTaskTree(int $row, int $col, int $height): void
    {
        $visibleTasks = array_slice($this->tasks, $this->scrollOffsets[0], $height);

        foreach ($visibleTasks as $i => $task) {
            $this->moveTo($row + $i, $col);

            $isSelected = $this->activeTab === 0 && $i === $this->selectedItem;
            if ($isSelected) {
                echo REVERSE;
            }

            // Tree structure
            $indent = str_repeat(TREE_SPACE, $task['level']);
            $branch = $task['isLast'] ? TREE_LAST : TREE_BRANCH;

            // Status icon
            $icon = match ($task['status']) {
                'done' => GREEN . '✓',
                'active' => YELLOW . '●',
                'pending' => GRAY . '○',
                default => ' '
            };

            // Task title (truncated)
            $availableWidth = $this->sidebarWidth - mb_strlen($indent) - 5;
            $title = mb_substr($task['title'], 0, $availableWidth);

            echo $indent . GRAY . $branch . RESET . $icon . ' ' . $title;

            if ($isSelected) {
                // Pad to full width for selection highlight
                $currentLen = mb_strlen($indent . $branch) + mb_strlen($title) + 3;
                echo str_repeat(' ', max(0, $this->sidebarWidth - $currentLen - 1));
                echo RESET;
            }
        }
    }

    private function renderFileList(int $row, int $col, int $height): void
    {
        $visibleFiles = array_slice($this->files, $this->scrollOffsets[1], $height);

        foreach ($visibleFiles as $i => $file) {
            $this->moveTo($row + $i, $col);

            $isSelected = $this->activeTab === 1 && $i === $this->selectedItem;
            if ($isSelected) {
                echo REVERSE;
            }

            // File icon based on extension
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $icon = match ($ext) {
                'php' => BLUE . 'P',
                'js' => YELLOW . 'J',
                'css' => MAGENTA . 'S',
                'html' => RED . 'H',
                default => GRAY . 'F'
            };

            // Size indicator
            $size = $this->formatSize($file['size']);

            // Format: [ICON] filename.ext (size)
            $nameWidth = $this->sidebarWidth - mb_strlen($size) - 5;
            $name = mb_substr($file['name'], 0, $nameWidth);

            echo $icon . RESET . ' ' . $name;

            // Right-align size
            $padding = $this->sidebarWidth - mb_strlen($name) - mb_strlen($size) - 3;
            if ($padding > 0) {
                echo str_repeat(' ', $padding);
            }
            echo GRAY . $size . RESET;

            if ($isSelected) {
                echo RESET;
            }
        }
    }

    private function renderContextInfo(int $row, int $col, int $height): void
    {
        $contextItems = [
            ['key' => 'Mode', 'value' => 'Development'],
            ['key' => 'Env', 'value' => 'Local'],
            ['key' => 'Branch', 'value' => 'main'],
            ['key' => 'Commit', 'value' => 'a3f2b1c'],
            ['key' => 'Memory', 'value' => $this->getMemoryUsage()],
            ['key' => 'Uptime', 'value' => $this->getUptime()],
        ];

        foreach ($contextItems as $i => $item) {
            if ($i >= $height) {
                break;
            }

            $this->moveTo($row + $i, $col);

            $isSelected = $this->activeTab === 2 && $i === $this->selectedItem;
            if ($isSelected) {
                echo REVERSE;
            }

            $key = mb_str_pad($item['key'] . ':', 8);
            echo GRAY . $key . RESET . ' ' . $item['value'];

            if ($isSelected) {
                $currentLen = mb_strlen($key) + mb_strlen($item['value']) + 1;
                echo str_repeat(' ', max(0, $this->sidebarWidth - $currentLen - 1));
                echo RESET;
            }
        }
    }

    private function renderToolStats(int $row, int $col, int $height): void
    {
        foreach ($this->tools as $i => $tool) {
            if ($i >= $height) {
                break;
            }

            $this->moveTo($row + $i, $col);

            $isSelected = $this->activeTab === 3 && $i === $this->selectedItem;
            if ($isSelected) {
                echo REVERSE;
            }

            // Tool name
            $name = mb_str_pad($tool['name'], 10);

            // Usage bar (mini)
            $barWidth = 8;
            $filled = (int) (($tool['usage'] / 100) * $barWidth);
            $bar = str_repeat('█', $filled) . str_repeat('░', $barWidth - $filled);

            // Count
            $count = sprintf('%3d', $tool['count']);

            echo $name . ' ' . CYAN . $bar . RESET . ' ' . $count;

            if ($isSelected) {
                $currentLen = mb_strlen($name) + mb_strlen($bar) + mb_strlen($count) + 2;
                echo str_repeat(' ', max(0, $this->sidebarWidth - $currentLen - 1));
                echo RESET;
            }
        }
    }

    private function renderQuickSlots(int $row, int $col): void
    {
        $this->moveTo($row, $col);
        echo GRAY . str_repeat(H_LINE, $this->sidebarWidth - 1) . RESET;

        $this->moveTo($row + 1, $col);
        echo DIM . 'Quick: ';

        for ($i = 1; $i <= 9; $i++) {
            if (isset($this->quickSlots[$i - 1])) {
                echo CYAN . $i . RESET . ' ';
            } else {
                echo GRAY . $i . ' ';
            }
        }
        echo RESET;
    }

    private function renderFooter(): void
    {
        $this->moveTo($this->height, 1);
        echo CLEAR_LINE;

        // Left: mode/status
        echo GRAY . '[' . $this->tabs[$this->activeTab] . ']' . RESET;

        // Center: shortcuts
        $shortcuts = 'TAB:switch j/k:nav ENTER:select q:quit';
        $centerPos = ($this->width - mb_strlen($shortcuts)) / 2;
        $this->moveTo($this->height, (int) $centerPos);
        echo DIM . $shortcuts . RESET;

        // Right: position indicator
        $position = sprintf('%d/%d', $this->selectedItem + 1, $this->getItemCount());
        $this->moveTo($this->height, $this->width - mb_strlen($position));
        echo GRAY . $position . RESET;
    }

    private function moveTo(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    private function navigateUp(): void
    {
        if ($this->selectedItem > 0) {
            $this->selectedItem--;

            // Adjust scroll if needed
            if ($this->selectedItem < $this->scrollOffsets[$this->activeTab]) {
                $this->scrollOffsets[$this->activeTab]--;
            }
        }
    }

    private function navigateDown(): void
    {
        $maxItems = $this->getItemCount() - 1;

        if ($this->selectedItem < $maxItems) {
            $this->selectedItem++;

            // Adjust scroll if needed
            $viewHeight = $this->height - $this->headerLines - $this->footerLines - 2;
            if ($this->selectedItem >= $this->scrollOffsets[$this->activeTab] + $viewHeight) {
                $this->scrollOffsets[$this->activeTab]++;
            }
        }
    }

    private function getItemCount(): int
    {
        return match ($this->tabs[$this->activeTab]) {
            'Tasks' => count($this->tasks),
            'Files' => count($this->files),
            'Context' => 6,
            'Tools' => count($this->tools),
            default => 0
        };
    }

    private function executeSelected(): void
    {
        // Execute action based on current tab and selection
        switch ($this->tabs[$this->activeTab]) {
            case 'Tasks':
                if (isset($this->tasks[$this->selectedItem])) {
                    $this->tasks[$this->selectedItem]['expanded'] =
                        ! ($this->tasks[$this->selectedItem]['expanded'] ?? false);
                }
                break;
            case 'Files':
                // Open file
                break;
        }
    }

    private function executeQuickSlot(int $slot): void
    {
        if (isset($this->quickSlots[$slot])) {
            // Execute quick action
            $this->addActivity([
                'time' => time(),
                'agent' => 'User',
                'operation' => 'Quick slot ' . ($slot + 1),
                'status' => 'success',
            ]);
        }
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . 'B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . 'K';
        }

        return round($bytes / 1048576, 1) . 'M';
    }

    private function getMemoryUsage(): string
    {
        $usage = memory_get_usage(true);

        return $this->formatSize($usage);
    }

    private function getUptime(): string
    {
        $uptime = time() - $_SERVER['REQUEST_TIME'];
        if ($uptime < 60) {
            return $uptime . 's';
        }
        if ($uptime < 3600) {
            return round($uptime / 60) . 'm';
        }

        return round($uptime / 3600, 1) . 'h';
    }

    private function getSuccessRate(): float
    {
        if (empty($this->activities)) {
            return 100.0;
        }

        $success = array_filter($this->activities, fn ($a) => $a['status'] === 'success');

        return (count($success) / count($this->activities)) * 100;
    }

    private function loadDemoData(): void
    {
        // Tasks with hierarchy
        $this->tasks = [
            ['title' => 'Authentication', 'level' => 0, 'status' => 'done', 'isLast' => false],
            ['title' => 'Login form', 'level' => 1, 'status' => 'done', 'isLast' => false],
            ['title' => 'JWT tokens', 'level' => 1, 'status' => 'done', 'isLast' => true],
            ['title' => 'Database', 'level' => 0, 'status' => 'active', 'isLast' => false],
            ['title' => 'Schema migration', 'level' => 1, 'status' => 'active', 'isLast' => false],
            ['title' => 'Indexes', 'level' => 1, 'status' => 'pending', 'isLast' => false],
            ['title' => 'Backup system', 'level' => 1, 'status' => 'pending', 'isLast' => true],
            ['title' => 'API Endpoints', 'level' => 0, 'status' => 'pending', 'isLast' => false],
            ['title' => 'REST routes', 'level' => 1, 'status' => 'pending', 'isLast' => false],
            ['title' => 'GraphQL', 'level' => 1, 'status' => 'pending', 'isLast' => true],
            ['title' => 'Testing', 'level' => 0, 'status' => 'pending', 'isLast' => true],
        ];

        // Files
        $this->files = [
            ['name' => 'Agent.php', 'size' => 12453],
            ['name' => 'CodingAgent.php', 'size' => 8932],
            ['name' => 'ToolRouter.php', 'size' => 5621],
            ['name' => 'Terminal.php', 'size' => 3421],
            ['name' => 'styles.css', 'size' => 2156],
            ['name' => 'app.js', 'size' => 18932],
            ['name' => 'index.html', 'size' => 1254],
            ['name' => 'config.json', 'size' => 892],
        ];

        // Tools
        $this->tools = [
            ['name' => 'ReadFile', 'usage' => 85, 'count' => 234],
            ['name' => 'WriteFile', 'usage' => 62, 'count' => 156],
            ['name' => 'Terminal', 'usage' => 73, 'count' => 189],
            ['name' => 'Search', 'usage' => 91, 'count' => 412],
            ['name' => 'Analyze', 'usage' => 45, 'count' => 89],
        ];

        // Initial activities
        $agents = ['Ana', 'Cod', 'Tst', 'Rev', 'Bld'];
        $operations = ['read', 'write', 'exec', 'scan', 'test', 'build'];

        for ($i = 0; $i < 50; $i++) {
            $this->activities[] = [
                'time' => time() - ($i * 60),
                'agent' => $agents[array_rand($agents)],
                'operation' => $operations[array_rand($operations)],
                'status' => ['success', 'success', 'success', 'warning', 'error'][rand(0, 4)],
            ];
        }

        // Quick slots
        $this->quickSlots = [
            0 => ['action' => 'run_tests'],
            1 => ['action' => 'build'],
            2 => ['action' => 'deploy'],
        ];
    }

    private function addRandomActivity(): void
    {
        $agents = ['Ana', 'Cod', 'Tst', 'Rev', 'Bld'];
        $operations = ['analyzing', 'compiling', 'testing', 'reviewing', 'building'];

        array_unshift($this->activities, [
            'time' => time(),
            'agent' => $agents[array_rand($agents)],
            'operation' => $operations[array_rand($operations)],
            'status' => ['success', 'success', 'warning', 'error'][rand(0, 3)],
        ]);

        // Keep max 200 activities
        if (count($this->activities) > 200) {
            array_pop($this->activities);
        }
    }

    private function addTestData(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->addRandomActivity();
        }
    }
}

// Execute
$ui = new CompactUI;

try {
    $ui->run();
} catch (Exception $e) {
    echo SHOW_CURSOR . ALT_BUFFER_OFF . RESET;
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
