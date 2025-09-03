#!/usr/bin/env php
<?php

/**
 * Visual Style Variations for the Practical Sidebar
 * Same functionality, different aesthetics
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HelgeSverre\Swarm\CLI\Terminal\Ansi;

class VisualStyleVariations
{
    private int $terminalHeight;

    private int $terminalWidth;

    private int $mainAreaWidth;

    private int $sidebarWidth;

    private bool $running = true;

    private int $currentStyle = 3; // Default to Modern style

    // Scrolling states
    private int $taskScrollOffset = 0;

    private int $selectedTaskIndex = 0;

    private int $toolLogScrollOffset = 0;

    private string $activeSection = 'tasks';

    // Same data as before
    private array $tasks = [
        ['id' => '1', 'status' => 'completed', 'description' => 'Search for authentication implementation in the codebase'],
        ['id' => '2', 'status' => 'completed', 'description' => 'Read UserController.php to understand current auth flow'],
        ['id' => '3', 'status' => 'running', 'description' => 'Implement bcrypt password hashing with proper salt generation'],
        ['id' => '4', 'status' => 'pending', 'description' => 'Write unit tests for the new authentication system'],
        ['id' => '5', 'status' => 'pending', 'description' => 'Update API documentation'],
        ['id' => '6', 'status' => 'pending', 'description' => 'Add rate limiting to login endpoint'],
    ];

    private array $commands = [
        ['command' => '/help', 'description' => 'Show available commands'],
        ['command' => '/clear', 'description' => 'Clear conversation history'],
        ['command' => '/save', 'description' => 'Save current state'],
        ['command' => '/tasks', 'description' => 'List all tasks'],
    ];

    private array $toolLog = [
        ['time' => '14:23:45', 'tool' => 'Grep', 'params' => 'auth* --type=php', 'result' => 'success', 'output' => 'Found 3 matches'],
        ['time' => '14:23:46', 'tool' => 'ReadFile', 'params' => 'UserController.php', 'result' => 'success', 'output' => 'Read 130 lines'],
        ['time' => '14:23:52', 'tool' => 'WriteFile', 'params' => 'src/auth/Hash.php', 'result' => 'running', 'output' => 'Writing...'],
        ['time' => '14:23:53', 'tool' => 'Terminal', 'params' => 'composer require', 'result' => 'failed', 'output' => 'Package not found'],
    ];

    private array $chatHistory = [
        ['time' => '14:23:40', 'role' => 'user', 'message' => 'Implement secure password hashing for the authentication system'],
        ['time' => '14:23:41', 'role' => 'assistant', 'message' => 'I\'ll implement secure password hashing using bcrypt. Let me first analyze the existing authentication code.'],
        ['time' => '14:23:47', 'role' => 'assistant', 'message' => 'Found the current auth implementation. Now creating a dedicated Hash utility class with bcrypt.'],
        ['time' => '14:23:52', 'role' => 'user', 'message' => 'Make sure to use a cost factor of at least 12'],
        ['time' => '14:23:53', 'role' => 'assistant', 'message' => 'Good point! I\'ll set the cost factor to 12 for better security. This will make brute force attacks significantly harder.'],
    ];

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->sidebarWidth = max(45, (int) ($this->terminalWidth * 0.35));
        $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;
    }

    public function run(): void
    {
        system('stty -echo -icanon min 1 time 0');
        stream_set_blocking(STDIN, false);
        echo "\033[?1049h\033[?25l";

        while ($this->running) {
            $this->render();

            $key = $this->readKey();
            if ($key !== null) {
                $this->handleInput($key);
            }

            usleep(50000);
        }

        echo "\033[?25h\033[?1049l";
        system('stty sane');
    }

    private function render(): void
    {
        $this->clearScreen();

        switch ($this->currentStyle) {
            case 1:
                $this->renderStyle1_Clean();
                break;
            case 2:
                $this->renderStyle2_Subtle();
                break;
            case 3:
                $this->renderStyle3_Modern();
                break;
            case 4:
                $this->renderStyle4_Classic();
                break;
            case 5:
                $this->renderStyle5_Compact();
                break;
        }

        $this->renderFooter();
    }

    /**
     * Style 1: Clean & Sharp
     */
    private function renderStyle1_Clean(): void
    {
        // Header
        $this->moveCursor(1, 1);
        echo str_repeat(' ', $this->terminalWidth);
        $this->moveCursor(1, 2);
        echo '▪ SWARM │ ' . Ansi::YELLOW . 'Working' . Ansi::RESET . ' │ Implementing password hashing';

        // Main chat area
        $this->renderMainChatClean();

        // Sidebar with no borders
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Tasks
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . 'TASKS' . Ansi::RESET;
        echo '  ' . Ansi::GREEN . $this->countByStatus('completed') . Ansi::RESET;
        echo ' ' . Ansi::YELLOW . $this->countByStatus('running') . Ansi::RESET;
        echo ' ' . Ansi::DIM . $this->countByStatus('pending') . Ansi::RESET;

        $row++;
        $this->renderTasksClean($col, $row);

        $row = 15;

        // Commands
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . 'COMMANDS' . Ansi::RESET;
        $row++;
        foreach (array_slice($this->commands, 0, 2) as $cmd) {
            $this->moveCursor($row++, $col);
            echo $cmd['command'] . ' — ' . Ansi::DIM . $cmd['description'] . Ansi::RESET;
        }

        $row = 20;

        // Tools
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . 'TOOLS' . Ansi::RESET;
        $row++;
        $this->renderToolsClean($col, $row);
    }

    /**
     * Style 2: Subtle Borders
     */
    private function renderStyle2_Subtle(): void
    {
        // Soft header
        $this->moveCursor(1, 1);
        echo "\033[48;5;236m" . str_repeat(' ', $this->terminalWidth) . Ansi::RESET;
        $this->moveCursor(1, 2);
        echo "\033[48;5;236m swarm • processing • " . $this->truncate('implementing password hashing', 40) . Ansi::RESET;

        // Main area
        $this->renderMainChatSubtle();

        // Sidebar with dotted separators
        $col = $this->mainAreaWidth + 2;

        // Dotted vertical line
        for ($row = 3; $row <= $this->terminalHeight - 2; $row += 2) {
            $this->moveCursor($row, $col);
            echo Ansi::DIM . '┊' . Ansi::RESET;
        }

        $col += 2;
        $row = 3;

        // Tasks with underlines
        $this->moveCursor($row++, $col);
        echo 'Tasks';
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '─────' . Ansi::RESET;

        $this->renderTasksSubtle($col, $row);

        $row = 15;
        $this->moveCursor($row++, $col);
        echo 'Commands';
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '────────' . Ansi::RESET;

        foreach (array_slice($this->commands, 0, 2) as $cmd) {
            $this->moveCursor($row++, $col);
            echo Ansi::CYAN . $cmd['command'] . Ansi::RESET . ' ' . Ansi::DIM . $cmd['description'] . Ansi::RESET;
        }

        $row = 20;
        $this->moveCursor($row++, $col);
        echo 'Activity';
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '────────' . Ansi::RESET;

        $this->renderToolsSubtle($col, $row);
    }

    /**
     * Style 3: Modern with Colors
     */
    private function renderStyle3_Modern(): void
    {
        // Clean modern header
        $this->moveCursor(1, 1);
        echo str_repeat(' ', $this->terminalWidth);
        $this->moveCursor(1, 2);
        echo "\033[38;5;117m◉\033[0m Swarm Agent ";
        echo "\033[38;5;240m│\033[0m ";
        echo "\033[38;5;220m▶\033[0m " . $this->truncate('Implementing password hashing', 40);

        // Main area with colored indicators
        $this->renderMainChatModern();

        // Sidebar with color accents
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Tasks with colored header
        $this->moveCursor($row++, $col);
        echo "\033[38;5;117m◆ Tasks\033[0m";
        echo '  ';
        echo "\033[38;5;120m✓" . $this->countByStatus('completed') . "\033[0m ";
        echo "\033[38;5;220m●" . $this->countByStatus('running') . "\033[0m ";
        echo "\033[38;5;245m○" . $this->countByStatus('pending') . "\033[0m";

        $row++;
        $this->renderTasksModern($col, $row);

        $row = 15;

        // Commands with color
        $this->moveCursor($row++, $col);
        echo "\033[38;5;213m◆ Commands\033[0m";
        $row++;
        foreach (array_slice($this->commands, 0, 2) as $cmd) {
            $this->moveCursor($row++, $col);
            echo "\033[38;5;87m" . $cmd['command'] . "\033[0m " . "\033[38;5;245m" . $cmd['description'] . "\033[0m";
        }

        $row = 20;

        // Tools with color coding
        $this->moveCursor($row++, $col);
        echo "\033[38;5;221m◆ Tool Activity\033[0m";
        $row++;
        $this->renderToolsModern($col, $row);
    }

    /**
     * Style 4: Classic Terminal
     */
    private function renderStyle4_Classic(): void
    {
        // Classic header with brackets
        $this->moveCursor(1, 1);
        echo '[SWARM] Status: PROCESSING | Task: Password Hashing Implementation';

        // Main area classic style
        $this->renderMainChatClassic();

        // Sidebar with ASCII borders
        $col = $this->mainAreaWidth + 2;

        // ASCII vertical line
        for ($row = 2; $row <= $this->terminalHeight - 2; $row++) {
            $this->moveCursor($row, $col);
            echo '|';
        }

        $col += 2;
        $row = 3;

        // Tasks with brackets
        $this->moveCursor($row++, $col);
        echo '[TASKS] (' . $this->countByStatus('completed') . '/' . count($this->tasks) . ')';

        $row++;
        $this->renderTasksClassic($col, $row);

        $row = 15;

        // Commands
        $this->moveCursor($row++, $col);
        echo '[COMMANDS]';
        $row++;
        foreach (array_slice($this->commands, 0, 2) as $cmd) {
            $this->moveCursor($row++, $col);
            echo '  ' . $cmd['command'] . ' - ' . $cmd['description'];
        }

        $row = 20;

        // Tools
        $this->moveCursor($row++, $col);
        echo '[TOOL LOG]';
        $row++;
        $this->renderToolsClassic($col, $row);
    }

    /**
     * Style 5: Ultra Compact
     */
    private function renderStyle5_Compact(): void
    {
        // Minimal header
        $this->moveCursor(1, 1);
        echo 'swarm:processing';

        // Compact main area
        $this->renderMainChatCompact();

        // Compact sidebar
        $col = $this->mainAreaWidth + 2;
        $row = 2;

        // Tasks inline
        $this->moveCursor($row++, $col);
        echo 'T:' . $this->countByStatus('completed') . '/' . $this->countByStatus('running') . '/' . $this->countByStatus('pending');

        $this->renderTasksCompact($col, $row);

        $row = 12;

        // Commands inline
        $this->moveCursor($row++, $col);
        echo 'C:';
        foreach (array_slice($this->commands, 0, 3) as $cmd) {
            echo ' ' . $cmd['command'];
        }

        $row = 14;

        // Tools compact
        $this->moveCursor($row++, $col);
        echo 'L:';
        $this->renderToolsCompact($col, $row);
    }

    // Main chat renderers for each style
    private function renderMainChatClean(): void
    {
        $row = 3;
        foreach ($this->chatHistory as $entry) {
            $this->moveCursor($row++, 2);

            if ($entry['role'] === 'user') {
                echo '→ ';
            } else {
                echo '  ';
            }

            $lines = $this->wordWrap($entry['message'], $this->mainAreaWidth - 5);
            echo $lines[0];

            for ($i = 1; $i < count($lines) && $row < 20; $i++) {
                $this->moveCursor($row++, 4);
                echo $lines[$i];
            }
            $row++;
        }
    }

    private function renderMainChatSubtle(): void
    {
        $row = 3;
        foreach ($this->chatHistory as $entry) {
            $this->moveCursor($row++, 2);

            echo Ansi::DIM . mb_substr($entry['time'], -8) . Ansi::RESET . ' ';

            if ($entry['role'] === 'user') {
                echo Ansi::BLUE . '›' . Ansi::RESET . ' ';
            } else {
                echo Ansi::GREEN . '•' . Ansi::RESET . ' ';
            }

            $lines = $this->wordWrap($entry['message'], $this->mainAreaWidth - 15);
            echo $lines[0];
            $row++;
        }
    }

    private function renderMainChatModern(): void
    {
        $row = 3;
        foreach ($this->chatHistory as $entry) {
            $this->moveCursor($row++, 2);

            // Timestamp on its own line with dim styling
            echo "\033[38;5;240m" . $entry['time'] . "\033[0m";

            $this->moveCursor($row++, 2);

            // Role indicator with different symbols and colors
            if ($entry['role'] === 'user') {
                echo "\033[38;5;117m⏺ User\033[0m";
            } else {
                echo "\033[38;5;120m⏺ Assistant\033[0m";
            }

            // Message content indented
            $lines = $this->wordWrap($entry['message'], $this->mainAreaWidth - 8);
            foreach ($lines as $i => $line) {
                if ($row > $this->terminalHeight - 5) {
                    break;
                }
                $this->moveCursor($row++, 4);
                echo '⎿  ' . $line;
            }

            $row++; // Extra line between messages
        }
    }

    private function renderMainChatClassic(): void
    {
        $row = 3;
        foreach ($this->chatHistory as $entry) {
            $this->moveCursor($row++, 2);

            if ($entry['role'] === 'user') {
                echo '> ';
            } else {
                echo '< ';
            }

            $lines = $this->wordWrap($entry['message'], $this->mainAreaWidth - 5);
            echo $lines[0];
            $row++;
        }
    }

    private function renderMainChatCompact(): void
    {
        $row = 2;
        foreach ($this->chatHistory as $entry) {
            $this->moveCursor($row++, 1);
            echo ($entry['role'] === 'user' ? '>' : '<') . $this->truncate($entry['message'], $this->mainAreaWidth - 3);
        }
    }

    // Task renderers for each style
    private function renderTasksClean(int $col, int &$row): void
    {
        foreach (array_slice($this->tasks, $this->taskScrollOffset, 5) as $task) {
            $this->moveCursor($row++, $col);

            $icon = match ($task['status']) {
                'completed' => '✓',
                'running' => '●',
                'pending' => '○',
            };

            echo $icon . ' ';

            $lines = $this->wordWrap($task['description'], $this->sidebarWidth - 5);
            echo $lines[0];

            for ($i = 1; $i < min(2, count($lines)); $i++) {
                $this->moveCursor($row++, $col + 2);
                echo $lines[$i];
            }
        }
    }

    private function renderTasksSubtle(int $col, int &$row): void
    {
        foreach (array_slice($this->tasks, $this->taskScrollOffset, 5) as $task) {
            $this->moveCursor($row++, $col);

            $color = match ($task['status']) {
                'completed' => Ansi::DIM,
                'running' => Ansi::YELLOW,
                'pending' => '',
            };

            echo $color . '• ' . $this->truncate($task['description'], $this->sidebarWidth - 5) . Ansi::RESET;
        }
    }

    private function renderTasksModern(int $col, int &$row): void
    {
        foreach (array_slice($this->tasks, $this->taskScrollOffset, 5) as $task) {
            $this->moveCursor($row++, $col);

            $icon = match ($task['status']) {
                'completed' => "\033[38;5;120m✓\033[0m",
                'running' => "\033[38;5;220m●\033[0m",
                'pending' => "\033[38;5;245m○\033[0m",
            };

            echo $icon . ' ' . $this->truncate($task['description'], $this->sidebarWidth - 5);
        }
    }

    private function renderTasksClassic(int $col, int &$row): void
    {
        foreach (array_slice($this->tasks, $this->taskScrollOffset, 5) as $task) {
            $this->moveCursor($row++, $col);

            $prefix = match ($task['status']) {
                'completed' => '[X]',
                'running' => '[*]',
                'pending' => '[ ]',
            };

            echo $prefix . ' ' . $this->truncate($task['description'], $this->sidebarWidth - 7);
        }
    }

    private function renderTasksCompact(int $col, int &$row): void
    {
        foreach (array_slice($this->tasks, $this->taskScrollOffset, 4) as $i => $task) {
            $this->moveCursor($row++, $col);

            $marker = match ($task['status']) {
                'completed' => '+',
                'running' => '*',
                'pending' => '-',
            };

            echo $marker . ($i + 1) . ':' . $this->truncate($task['description'], $this->sidebarWidth - 5);
        }
    }

    // Tool renderers for each style
    private function renderToolsClean(int $col, int &$row): void
    {
        foreach (array_slice($this->toolLog, -3) as $log) {
            $this->moveCursor($row++, $col);

            $color = match ($log['result']) {
                'success' => Ansi::GREEN,
                'running' => Ansi::YELLOW,
                'failed' => Ansi::RED,
                default => '',
            };

            echo $log['tool'] . ' ' . $color . '→' . Ansi::RESET . ' ' . $log['output'];
        }
    }

    private function renderToolsSubtle(int $col, int &$row): void
    {
        foreach (array_slice($this->toolLog, -3) as $log) {
            $this->moveCursor($row++, $col);
            echo Ansi::DIM . mb_substr($log['time'], -5) . Ansi::RESET . ' ' . $log['tool'];
        }
    }

    private function renderToolsModern(int $col, int &$row): void
    {
        foreach (array_slice($this->toolLog, -3) as $log) {
            $this->moveCursor($row++, $col);

            $color = match ($log['result']) {
                'success' => "\033[38;5;120m",
                'running' => "\033[38;5;220m",
                'failed' => "\033[38;5;203m",
                default => '',
            };

            echo $color . '▪' . Ansi::RESET . ' ' . $log['tool'] . ' ' . Ansi::DIM . $log['output'] . Ansi::RESET;
        }
    }

    private function renderToolsClassic(int $col, int &$row): void
    {
        foreach (array_slice($this->toolLog, -3) as $log) {
            $this->moveCursor($row++, $col);
            echo '  ' . mb_substr($log['time'], -8) . ' ' . $log['tool'] . ': ' . $log['result'];
        }
    }

    private function renderToolsCompact(int $col, int &$row): void
    {
        foreach (array_slice($this->toolLog, -2) as $log) {
            $this->moveCursor($row++, $col);
            echo mb_substr($log['tool'], 0, 4) . ':' . mb_substr($log['result'], 0, 1);
        }
    }

    private function renderFooter(): void
    {
        $this->moveCursor($this->terminalHeight, 2);

        $styleName = match ($this->currentStyle) {
            1 => 'Clean',
            2 => 'Subtle',
            3 => 'Modern',
            4 => 'Classic',
            5 => 'Compact',
        };

        echo Ansi::DIM . '1-5: Switch style (' . $styleName . ') | ↑↓: Scroll | Tab: Switch section | Q: Quit' . Ansi::RESET;
    }

    private function countByStatus(string $status): int
    {
        return count(array_filter($this->tasks, fn ($t) => $t['status'] === $status));
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
            case "\t":
                $sections = ['tasks', 'commands', 'tools'];
                $currentIndex = array_search($this->activeSection, $sections);
                $this->activeSection = $sections[($currentIndex + 1) % count($sections)];
                break;
            case 'j':
                if ($this->activeSection === 'tasks' && $this->taskScrollOffset < count($this->tasks) - 5) {
                    $this->taskScrollOffset++;
                } elseif ($this->activeSection === 'tools' && $this->toolLogScrollOffset < count($this->toolLog) - 3) {
                    $this->toolLogScrollOffset++;
                }
                break;
            case 'k':
                if ($this->activeSection === 'tasks' && $this->taskScrollOffset > 0) {
                    $this->taskScrollOffset--;
                } elseif ($this->activeSection === 'tools' && $this->toolLogScrollOffset > 0) {
                    $this->toolLogScrollOffset--;
                }
                break;
            case 'q':
            case 'Q':
                $this->running = false;
                break;
        }
    }

    private function wordWrap(string $text, int $maxWidth): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            if (mb_strlen($currentLine . ' ' . $word) <= $maxWidth) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        return $lines ?: [''];
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }

    private function readKey(): ?string
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        if (stream_select($read, $write, $except, 0, 0) > 0) {
            $key = fgetc(STDIN);

            if ($key === "\033") {
                $seq = $key . fgetc(STDIN) . fgetc(STDIN);
                if ($seq === "\033[A") {
                    return 'k';
                }
                if ($seq === "\033[B") {
                    return 'j';
                }
            }

            return $key;
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
}

// Run the demo
echo "Starting Visual Style Variations...\n";
echo "Press 1-5 to switch between different visual styles.\n";
sleep(1);
$demo = new VisualStyleVariations;
$demo->run();
echo "\nDemo ended.\n";
