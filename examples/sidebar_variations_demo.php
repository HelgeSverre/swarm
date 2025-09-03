#!/usr/bin/env php
<?php

/**
 * Terminal UI Sidebar Variations Demo
 * Shows 5 different approaches to sidebar display
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HelgeSverre\Swarm\CLI\Terminal\Ansi;

class SidebarVariationsDemo
{
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
        'notes' => ['Check performance metrics', 'Review security settings'],
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
                $this->renderStyle1_CleanSpacing();
                break;
            case 2:
                $this->renderStyle2_BackgroundSeparation();
                break;
            case 3:
                $this->renderStyle3_BoxedPanels();
                break;
            case 4:
                $this->renderStyle4_FloatingCards();
                break;
            case 5:
                $this->renderStyle5_DoubleLine();
                break;
        }

        // Render footer
        $this->renderFooter();
    }

    private function renderHeader(): void
    {
        $this->moveCursor(1, 1);
        echo Ansi::BG_DARK;
        $title = " 🎨 Sidebar Variations Demo - Style {$this->currentStyle} ";
        echo $title;
        echo str_repeat(' ', $this->terminalWidth - mb_strlen($title));
        echo Ansi::RESET;
    }

    private function renderMainArea(): void
    {
        $this->moveCursor(3, 2);
        echo Ansi::BOLD . 'Main Content Area' . Ansi::RESET;

        $this->moveCursor(5, 2);
        echo 'This is where the main content would appear.';

        $this->moveCursor(7, 2);
        echo 'Current style: ' . $this->getStyleName($this->currentStyle);

        $this->moveCursor(9, 2);
        echo Ansi::DIM . 'Features of this style:' . Ansi::RESET;

        $features = $this->getStyleFeatures($this->currentStyle);
        $row = 10;
        foreach ($features as $feature) {
            $this->moveCursor($row++, 4);
            echo '• ' . $feature;
        }
    }

    /**
     * Style 1: Clean Spacing - No dividers, just whitespace
     */
    private function renderStyle1_CleanSpacing(): void
    {
        $col = $this->mainAreaWidth + 5; // Extra spacing instead of divider
        $row = 3;

        // Tasks section
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . 'Tasks' . Ansi::RESET;

        $row++;
        foreach ($this->tasks as $i => $task) {
            if ($row > $this->terminalHeight - 10) {
                break;
            }
            $this->moveCursor($row++, $col);
            $this->renderTaskLine($task, $i + 1);
        }

        $row += 2;

        // Context section
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . 'Context' . Ansi::RESET;

        $row++;
        $this->moveCursor($row++, $col);
        echo Ansi::CYAN . '📁 ' . Ansi::RESET . $this->truncate($this->context['directory'], 30);

        $row++;
        foreach ($this->context['files'] as $file) {
            if ($row > $this->terminalHeight - 4) {
                break;
            }
            $this->moveCursor($row++, $col);
            echo '   ' . $file;
        }
    }

    /**
     * Style 2: Background Separation - Different background colors
     */
    private function renderStyle2_BackgroundSeparation(): void
    {
        // Fill sidebar background
        $bgColor = "\033[48;5;236m"; // Dark gray background
        for ($row = 2; $row <= $this->terminalHeight - 2; $row++) {
            $this->moveCursor($row, $this->mainAreaWidth + 2);
            echo $bgColor . str_repeat(' ', $this->sidebarWidth) . Ansi::RESET;
        }

        $col = $this->mainAreaWidth + 4;
        $row = 3;

        // Tasks section with background
        $this->moveCursor($row++, $col);
        echo $bgColor . Ansi::WHITE . Ansi::BOLD . 'Tasks' . Ansi::RESET . $bgColor;

        $row++;
        foreach ($this->tasks as $i => $task) {
            if ($row > $this->terminalHeight - 10) {
                break;
            }
            $this->moveCursor($row++, $col);
            echo $bgColor;
            $this->renderTaskLine($task, $i + 1);
            echo Ansi::RESET;
        }

        $row += 2;

        // Context section with background
        $this->moveCursor($row++, $col);
        echo $bgColor . Ansi::WHITE . Ansi::BOLD . 'Context' . Ansi::RESET . $bgColor;

        $row++;
        $this->moveCursor($row++, $col);
        echo $bgColor . Ansi::CYAN . '📁 ' . Ansi::WHITE . $this->truncate($this->context['directory'], 28) . Ansi::RESET;
    }

    /**
     * Style 3: Boxed Panels - Each section in its own box
     */
    private function renderStyle3_BoxedPanels(): void
    {
        $col = $this->mainAreaWidth + 3;

        // Tasks box
        $this->drawBox($col, 3, $this->sidebarWidth - 2, 10, 'Tasks', true);
        $row = 5;
        foreach ($this->tasks as $i => $task) {
            if ($row > 11) {
                break;
            }
            $this->moveCursor($row++, $col + 2);
            $this->renderTaskLine($task, $i + 1);
        }

        // Context box
        $this->drawBox($col, 14, $this->sidebarWidth - 2, 8, 'Context', true);
        $row = 16;
        $this->moveCursor($row++, $col + 2);
        echo Ansi::CYAN . '📁 ' . Ansi::RESET . $this->truncate($this->context['directory'], 25);

        $row++;
        foreach ($this->context['files'] as $file) {
            if ($row > 20) {
                break;
            }
            $this->moveCursor($row++, $col + 2);
            echo '  ' . $file;
        }
    }

    /**
     * Style 4: Floating Cards - Modern card-based design with shadows
     */
    private function renderStyle4_FloatingCards(): void
    {
        $col = $this->mainAreaWidth + 3;

        // Tasks card with shadow
        $this->drawCard($col, 3, $this->sidebarWidth - 3, 10, 'Tasks');
        $row = 5;
        foreach ($this->tasks as $i => $task) {
            if ($row > 11) {
                break;
            }
            $this->moveCursor($row++, $col + 2);
            $this->renderTaskLine($task, $i + 1);
        }

        // Context card with shadow
        $this->drawCard($col, 15, $this->sidebarWidth - 3, 8, 'Context');
        $row = 17;
        $this->moveCursor($row++, $col + 2);
        echo Ansi::CYAN . '📁 ' . Ansi::RESET . $this->truncate($this->context['directory'], 25);

        $row++;
        foreach ($this->context['files'] as $file) {
            if ($row > 21) {
                break;
            }
            $this->moveCursor($row++, $col + 2);
            echo '  ' . $file;
        }
    }

    /**
     * Style 5: Classic Double Line - Traditional terminal look
     */
    private function renderStyle5_DoubleLine(): void
    {
        // Draw vertical double line
        for ($row = 2; $row <= $this->terminalHeight - 2; $row++) {
            $this->moveCursor($row, $this->mainAreaWidth + 1);
            echo Ansi::DIM . '║' . Ansi::RESET;
        }

        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Tasks section with double line decoration
        $this->moveCursor($row++, $col);
        echo '╔═══ ' . Ansi::BOLD . 'Tasks' . Ansi::RESET . ' ═══╗';

        $row++;
        foreach ($this->tasks as $i => $task) {
            if ($row > $this->terminalHeight - 10) {
                break;
            }
            $this->moveCursor($row++, $col);
            echo '║ ';
            $this->renderTaskLine($task, $i + 1);

            // Add right border
            $lineLen = mb_strlen(strip_tags($this->getTaskLineText($task, $i + 1)));
            $padding = max(0, $this->sidebarWidth - 4 - $lineLen);
            echo str_repeat(' ', $padding) . '║';
        }

        $this->moveCursor($row++, $col);
        echo '╚' . str_repeat('═', $this->sidebarWidth - 3) . '╝';

        $row += 2;

        // Context section
        $this->moveCursor($row++, $col);
        echo '╔═══ ' . Ansi::BOLD . 'Context' . Ansi::RESET . ' ═══╗';

        $row++;
        $this->moveCursor($row++, $col);
        echo '║ ' . Ansi::CYAN . '📁 ' . Ansi::RESET . $this->truncate($this->context['directory'], 25) . ' ║';
    }

    private function renderTaskLine(array $task, int $number): void
    {
        $icon = match ($task['status']) {
            'completed' => Ansi::GREEN . '✓',
            'running' => Ansi::YELLOW . '▶',
            'pending' => Ansi::DIM . '○',
            default => ' '
        };

        $num = mb_str_pad($number . '.', 3);
        echo $num . ' ' . $icon . Ansi::RESET . ' ' . $this->truncate($task['description'], 25);
    }

    private function getTaskLineText(array $task, int $number): string
    {
        $num = mb_str_pad($number . '.', 3);

        return $num . ' ○ ' . $this->truncate($task['description'], 25);
    }

    private function drawBox(int $x, int $y, int $width, int $height, string $title = '', bool $rounded = false): void
    {
        $tl = $rounded ? '╭' : '┌';
        $tr = $rounded ? '╮' : '┐';
        $bl = $rounded ? '╰' : '└';
        $br = $rounded ? '╯' : '┘';

        // Top border
        $this->moveCursor($y, $x);
        echo Ansi::DIM . $tl;
        if ($title) {
            echo '─ ' . Ansi::RESET . Ansi::BOLD . $title . Ansi::RESET . Ansi::DIM . ' ';
            echo str_repeat('─', $width - mb_strlen($title) - 5);
        } else {
            echo str_repeat('─', $width - 2);
        }
        echo $tr . Ansi::RESET;

        // Sides
        for ($i = 1; $i < $height - 1; $i++) {
            $this->moveCursor($y + $i, $x);
            echo Ansi::DIM . '│' . Ansi::RESET;
            $this->moveCursor($y + $i, $x + $width - 1);
            echo Ansi::DIM . '│' . Ansi::RESET;
        }

        // Bottom
        $this->moveCursor($y + $height - 1, $x);
        echo Ansi::DIM . $bl . str_repeat('─', $width - 2) . $br . Ansi::RESET;
    }

    private function drawCard(int $x, int $y, int $width, int $height, string $title = ''): void
    {
        // Card background (white)
        for ($i = 0; $i < $height; $i++) {
            $this->moveCursor($y + $i, $x);
            echo "\033[48;5;255m" . str_repeat(' ', $width) . Ansi::RESET;
        }

        // Shadow (using block characters)
        for ($i = 1; $i < $height; $i++) {
            $this->moveCursor($y + $i, $x + $width);
            echo "\033[38;5;238m▒" . Ansi::RESET;
        }
        $this->moveCursor($y + $height, $x + 1);
        echo "\033[38;5;238m" . str_repeat('▒', $width) . Ansi::RESET;

        // Title
        if ($title) {
            $this->moveCursor($y + 1, $x + 2);
            echo "\033[48;5;255m\033[38;5;0m" . Ansi::BOLD . $title . Ansi::RESET;
        }
    }

    private function renderFooter(): void
    {
        $this->moveCursor($this->terminalHeight - 1, 2);
        echo Ansi::DIM . 'Press 1-5 to switch styles | Q to quit | Current: Style '
             . $this->currentStyle . ' - ' . $this->getStyleName($this->currentStyle) . Ansi::RESET;
    }

    private function getStyleName(int $style): string
    {
        return match ($style) {
            1 => 'Clean Spacing',
            2 => 'Background Separation',
            3 => 'Boxed Panels',
            4 => 'Floating Cards',
            5 => 'Classic Double Line',
            default => 'Unknown'
        };
    }

    private function getStyleFeatures(int $style): array
    {
        return match ($style) {
            1 => [
                'No divider lines',
                'Uses whitespace for separation',
                'Minimal and clean',
                'Focus on content',
            ],
            2 => [
                'Subtle background color',
                'No border lines',
                'Modern flat design',
                'Visual hierarchy through color',
            ],
            3 => [
                'Each section in a box',
                'Rounded corner option',
                'Clear boundaries',
                'Traditional UI approach',
            ],
            4 => [
                'Card-based design',
                'Drop shadows for depth',
                'Modern web-inspired',
                'Floating appearance',
            ],
            5 => [
                'Double-line borders',
                'Classic terminal aesthetic',
                'Strong visual separation',
                'Traditional box drawing',
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

        return mb_substr($text, 0, $length - 3) . '...';
    }
}

// Run the demo
echo "Starting Sidebar Variations Demo...\n";
$demo = new SidebarVariationsDemo;
$demo->run();
echo "Demo ended.\n";
