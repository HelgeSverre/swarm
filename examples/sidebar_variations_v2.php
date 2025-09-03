#!/usr/bin/env php
<?php

/**
 * Terminal UI Sidebar Variations V2
 * Based on approaches from the UI examples
 * 5 new styles without animations or excessive fanciness
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HelgeSverre\Swarm\CLI\Terminal\Ansi;

class SidebarVariationsV2
{
    // Tokyo Night inspired colors
    const TN_BG_DARK = "\033[48;5;232m";

    const TN_BG_HIGHLIGHT = "\033[48;5;236m";

    const TN_FG = "\033[38;5;251m";

    const TN_FG_DARK = "\033[38;5;245m";

    const TN_BLUE = "\033[38;5;111m";

    const TN_GREEN = "\033[38;5;115m";

    const TN_YELLOW = "\033[38;5;221m";

    const TN_CYAN = "\033[38;5;87m";

    const TN_MAGENTA = "\033[38;5;176m";

    const TN_GRAY = "\033[38;5;240m";

    private int $terminalHeight;

    private int $terminalWidth;

    private int $mainAreaWidth;

    private int $sidebarWidth;

    private int $currentStyle = 1;

    private bool $running = true;

    // Sample data
    private array $tasks = [
        ['status' => 'running', 'description' => 'Implement user authentication'],
        ['status' => 'completed', 'description' => 'Set up database schema'],
        ['status' => 'pending', 'description' => 'Write API documentation'],
        ['status' => 'pending', 'description' => 'Add test coverage'],
        ['status' => 'pending', 'description' => 'Deploy to staging'],
    ];

    private array $context = [
        'directory' => '/Users/helge/code/project',
        'files' => ['main.php', 'config.yaml', 'README.md'],
        'notes' => ['Check performance', 'Review security'],
    ];

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->sidebarWidth = max(35, (int) ($this->terminalWidth * 0.3));
        $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;
    }

    public function run(): void
    {
        // Setup terminal
        system('stty -echo -icanon min 1 time 0');
        stream_set_blocking(STDIN, false);
        echo "\033[?1049h"; // Alternate screen
        echo "\033[?25l";   // Hide cursor

        while ($this->running) {
            $this->render();

            $key = $this->readKey();
            if ($key !== null) {
                $this->handleInput($key);
            }

            usleep(50000);
        }

        // Cleanup
        echo "\033[?25h";   // Show cursor
        echo "\033[?1049l"; // Exit alternate screen
        system('stty sane');
    }

    private function render(): void
    {
        $this->clearScreen();

        // Render header
        $this->renderHeader();

        // Render main area
        $this->renderMainArea();

        // Render sidebar based on current style
        switch ($this->currentStyle) {
            case 1:
                $this->renderStyle1_TokyoNight();
                break;
            case 2:
                $this->renderStyle2_CompactSemantic();
                break;
            case 3:
                $this->renderStyle3_MinimalDivider();
                break;
            case 4:
                $this->renderStyle4_InlineStatus();
                break;
            case 5:
                $this->renderStyle5_GoldenRatio();
                break;
        }

        // Render footer
        $this->renderFooter();
    }

    private function renderHeader(): void
    {
        $this->moveCursor(1, 1);
        echo self::TN_BG_DARK;
        $title = " 🎨 Sidebar V2 - Style {$this->currentStyle} ";
        echo self::TN_MAGENTA . '⟡ ' . self::TN_FG . Ansi::BOLD . $title . Ansi::RESET;
        echo self::TN_BG_DARK . str_repeat(' ', $this->terminalWidth - mb_strlen($title) - 2) . Ansi::RESET;
    }

    private function renderMainArea(): void
    {
        $this->moveCursor(3, 2);
        echo Ansi::BOLD . 'Main Content Area' . Ansi::RESET;

        $this->moveCursor(5, 2);
        echo 'This is where the main content would appear.';

        $this->moveCursor(7, 2);
        echo 'Style: ' . $this->getStyleName($this->currentStyle);

        $this->moveCursor(9, 2);
        echo self::TN_FG_DARK . 'Features:' . Ansi::RESET;

        $features = $this->getStyleFeatures($this->currentStyle);
        $row = 10;
        foreach ($features as $feature) {
            $this->moveCursor($row++, 4);
            echo '• ' . $feature;
        }
    }

    /**
     * Style 1: Tokyo Night Theme - Cohesive color scheme
     */
    private function renderStyle1_TokyoNight(): void
    {
        $col = $this->mainAreaWidth + 2;

        // Subtle vertical separator using color
        for ($row = 2; $row <= $this->terminalHeight - 2; $row++) {
            $this->moveCursor($row, $col - 1);
            echo self::TN_GRAY . '│' . Ansi::RESET;
        }

        $row = 3;

        // Tasks section with semantic colors
        $this->moveCursor($row++, $col);
        echo self::TN_BLUE . '◆ ' . self::TN_FG . Ansi::BOLD . 'Tasks' . Ansi::RESET;

        $running = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'running'));
        $completed = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));

        $this->moveCursor($row++, $col);
        echo self::TN_GREEN . $completed . ' done' . Ansi::RESET . self::TN_FG_DARK . ' · ' .
             self::TN_YELLOW . $running . ' active' . Ansi::RESET;

        $row++;
        foreach ($this->tasks as $i => $task) {
            if ($row > $this->terminalHeight - 10) {
                break;
            }
            $this->moveCursor($row++, $col);

            $icon = match ($task['status']) {
                'completed' => self::TN_GREEN . '✓',
                'running' => self::TN_YELLOW . '●',
                'pending' => self::TN_GRAY . '○',
            };

            echo $icon . ' ' . self::TN_FG . $this->truncate($task['description'], 30) . Ansi::RESET;
        }

        $row += 2;

        // Context section
        $this->moveCursor($row++, $col);
        echo self::TN_CYAN . '◆ ' . self::TN_FG . Ansi::BOLD . 'Context' . Ansi::RESET;

        $row++;
        $this->moveCursor($row++, $col);
        echo self::TN_BLUE . '📁 ' . self::TN_FG . $this->truncate($this->context['directory'], 28) . Ansi::RESET;

        foreach ($this->context['files'] as $file) {
            if ($row > $this->terminalHeight - 4) {
                break;
            }
            $this->moveCursor($row++, $col);
            echo self::TN_FG_DARK . '   ' . $file . Ansi::RESET;
        }
    }

    /**
     * Style 2: Compact Semantic - Icons and minimal text
     */
    private function renderStyle2_CompactSemantic(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Compact task indicators
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . 'Tasks' . Ansi::RESET;

        // Task status bar
        $this->moveCursor($row++, $col);
        $taskBar = '';
        foreach ($this->tasks as $task) {
            $taskBar .= match ($task['status']) {
                'completed' => '█',
                'running' => '▓',
                'pending' => '░',
            };
        }
        echo Ansi::GREEN . mb_substr($taskBar, 0, 5) . Ansi::YELLOW . mb_substr($taskBar, 5, 2) .
             Ansi::DIM . mb_substr($taskBar, 7) . Ansi::RESET;

        $row++;

        // Show only active/important tasks
        foreach ($this->tasks as $i => $task) {
            if ($task['status'] === 'pending') {
                continue;
            }
            if ($row > $this->terminalHeight - 10) {
                break;
            }

            $this->moveCursor($row++, $col);
            $prefix = $task['status'] === 'running' ? '▶ ' : '✓ ';
            echo $prefix . $this->truncate($task['description'], 28);
        }

        $row = max($row + 2, 12);

        // Compact context
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . 'Context' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo '📁 ' . basename($this->context['directory']);

        $this->moveCursor($row++, $col);
        echo '📄 ' . count($this->context['files']) . ' files';

        $this->moveCursor($row++, $col);
        echo '📝 ' . count($this->context['notes']) . ' notes';
    }

    /**
     * Style 3: Minimal Divider - Single subtle line
     */
    private function renderStyle3_MinimalDivider(): void
    {
        $col = $this->mainAreaWidth + 2;

        // Single vertical line with breaks
        for ($row = 4; $row <= $this->terminalHeight - 4; $row++) {
            // Break line at section boundaries
            if ($row === 11 || $row === 12) {
                continue;
            }

            $this->moveCursor($row, $col);
            echo Ansi::DIM . '·' . Ansi::RESET;
        }

        $col += 2;
        $row = 3;

        // Tasks
        $this->moveCursor($row++, $col);
        echo Ansi::UNDERLINE . 'Tasks' . Ansi::RESET;

        $row++;
        foreach ($this->tasks as $i => $task) {
            if ($row > 10) {
                break;
            }
            $this->moveCursor($row++, $col);

            $num = ($i + 1) . '.';
            $color = match ($task['status']) {
                'completed' => Ansi::GREEN,
                'running' => Ansi::YELLOW,
                'pending' => Ansi::DIM,
            };

            echo $color . mb_str_pad($num, 3) . $this->truncate($task['description'], 27) . Ansi::RESET;
        }

        $row = 13;

        // Context
        $this->moveCursor($row++, $col);
        echo Ansi::UNDERLINE . 'Context' . Ansi::RESET;

        $row++;
        $this->moveCursor($row++, $col);
        echo Ansi::CYAN . 'Dir: ' . Ansi::RESET . $this->truncate(basename($this->context['directory']), 25);

        $this->moveCursor($row++, $col);
        echo Ansi::CYAN . 'Files:' . Ansi::RESET;
        foreach ($this->context['files'] as $file) {
            if ($row > $this->terminalHeight - 4) {
                break;
            }
            $this->moveCursor($row++, $col);
            echo '  · ' . $file;
        }
    }

    /**
     * Style 4: Inline Status - Everything in a compact status line
     */
    private function renderStyle4_InlineStatus(): void
    {
        // No vertical divider - use full width status blocks
        $col = $this->mainAreaWidth + 2;
        $row = 3;

        // Status block background
        for ($r = $row; $r < $row + 3; $r++) {
            $this->moveCursor($r, $col);
            echo self::TN_BG_HIGHLIGHT . str_repeat(' ', $this->sidebarWidth - 2) . Ansi::RESET;
        }

        // Inline task status
        $this->moveCursor($row, $col + 1);
        echo self::TN_BG_HIGHLIGHT . Ansi::WHITE . 'Tasks: ';
        echo Ansi::GREEN . '✓' . count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));
        echo Ansi::YELLOW . ' ●' . count(array_filter($this->tasks, fn ($t) => $t['status'] === 'running'));
        echo Ansi::DIM . ' ○' . count(array_filter($this->tasks, fn ($t) => $t['status'] === 'pending'));
        echo Ansi::RESET;

        $row++;
        $this->moveCursor($row, $col + 1);
        echo self::TN_BG_HIGHLIGHT . Ansi::WHITE . 'Active: ' . Ansi::YELLOW;
        $runningTask = array_filter($this->tasks, fn ($t) => $t['status'] === 'running');
        if (! empty($runningTask)) {
            echo $this->truncate(reset($runningTask)['description'], 25);
        } else {
            echo 'None';
        }
        echo Ansi::RESET;

        $row++;
        $this->moveCursor($row, $col + 1);
        echo self::TN_BG_HIGHLIGHT . Ansi::WHITE . 'Path: ' . Ansi::CYAN;
        echo $this->truncate(basename($this->context['directory']), 27);
        echo Ansi::RESET;

        $row += 3;

        // Detailed list below
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '─── Details ───' . Ansi::RESET;

        $row++;
        foreach ($this->tasks as $task) {
            if ($task['status'] !== 'running') {
                continue;
            }
            if ($row > $this->terminalHeight - 8) {
                break;
            }

            $this->moveCursor($row++, $col);
            echo '▶ ' . $this->truncate($task['description'], 30);
        }

        $row++;
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . 'Files: ' . Ansi::RESET . implode(', ', array_slice($this->context['files'], 0, 2));
    }

    /**
     * Style 5: Golden Ratio Layout - Proportional spacing
     */
    private function renderStyle5_GoldenRatio(): void
    {
        // Use golden ratio for vertical spacing
        $goldenRatio = 1.618;
        $taskHeight = (int) (($this->terminalHeight - 10) / $goldenRatio);
        $contextHeight = (int) ($taskHeight / $goldenRatio);

        $col = $this->mainAreaWidth + 3;

        // Top section (tasks) - larger
        $row = 3;
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '╱ Tasks' . Ansi::RESET;

        $row++;
        $maxTasks = min(count($this->tasks), $taskHeight - 2);
        foreach (array_slice($this->tasks, 0, $maxTasks) as $i => $task) {
            $this->moveCursor($row++, $col);

            // Indented hierarchy
            $indent = $task['status'] === 'pending' ? '  ' : '';
            $marker = match ($task['status']) {
                'completed' => '✓',
                'running' => '→',
                'pending' => '·',
            };

            echo $indent . $marker . ' ' . $this->truncate($task['description'], 28 - mb_strlen($indent));
        }

        // Middle divider
        $row = 3 + $taskHeight;
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . str_repeat('·', 30) . Ansi::RESET;

        // Bottom section (context) - smaller
        $row++;
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '╱ Context' . Ansi::RESET;

        $row++;
        $this->moveCursor($row++, $col);
        echo $this->truncate($this->context['directory'], 30);

        if ($row < $this->terminalHeight - 4) {
            foreach ($this->context['notes'] as $note) {
                $this->moveCursor($row++, $col);
                echo Ansi::DIM . '• ' . $note . Ansi::RESET;
                if ($row >= $this->terminalHeight - 4) {
                    break;
                }
            }
        }
    }

    private function renderFooter(): void
    {
        $this->moveCursor($this->terminalHeight - 1, 2);
        echo Ansi::DIM . 'Press 1-5 to switch styles | Q to quit | Current: '
             . $this->getStyleName($this->currentStyle) . Ansi::RESET;
    }

    private function getStyleName(int $style): string
    {
        return match ($style) {
            1 => 'Tokyo Night Theme',
            2 => 'Compact Semantic',
            3 => 'Minimal Divider',
            4 => 'Inline Status',
            5 => 'Golden Ratio',
            default => 'Unknown'
        };
    }

    private function getStyleFeatures(int $style): array
    {
        return match ($style) {
            1 => [
                'Cohesive color scheme',
                'Semantic color coding',
                'Subtle gray divider',
                'Status indicators',
            ],
            2 => [
                'Ultra-compact display',
                'Visual task progress bar',
                'Icon-based indicators',
                'Active tasks only',
            ],
            3 => [
                'Dotted vertical divider',
                'Numbered task list',
                'Section breaks',
                'Minimal decoration',
            ],
            4 => [
                'Status blocks at top',
                'No vertical divider',
                'Inline statistics',
                'Focused on current work',
            ],
            5 => [
                'Golden ratio proportions',
                'Hierarchical indentation',
                'Natural spacing',
                'Aesthetic balance',
            ],
            default => []
        };
    }

    private function handleInput(string $key): void
    {
        switch ($key) {
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
                $this->currentStyle = (int) $key;
                break;
            case 'q':
            case 'Q':
                $this->running = false;
                break;
        }
    }

    private function readKey(): ?string
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        if (stream_select($read, $write, $except, 0, 0) > 0) {
            return fgetc(STDIN);
        }

        return null;
    }

    private function clearScreen(): void
    {
        echo "\033[2J\033[H";
    }

    private function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    private function updateTerminalSize(): void
    {
        $this->terminalHeight = (int) exec('tput lines') ?: 24;
        $this->terminalWidth = (int) exec('tput cols') ?: 80;
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 1) . '…';
    }
}

// Run the demo
echo "Starting Sidebar Variations V2...\n";
$demo = new SidebarVariationsV2;
$demo->run();
echo "Demo ended.\n";
