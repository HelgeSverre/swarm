#!/usr/bin/env php
<?php

/**
 * Practical Sidebar Design for Swarm
 * Clean, minimal, with the actual information you need
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HelgeSverre\Swarm\CLI\Terminal\Ansi;

class PracticalSidebar
{
    private int $terminalHeight;

    private int $terminalWidth;

    private int $mainAreaWidth;

    private int $sidebarWidth;

    private bool $running = true;

    // Scrolling states
    private int $taskScrollOffset = 0;

    private int $selectedTaskIndex = 0;

    private int $toolLogScrollOffset = 0;

    private string $activeSection = 'tasks'; // tasks, commands, tools

    // Real data structures
    private array $tasks = [
        ['id' => '1', 'status' => 'completed', 'description' => 'Search for authentication implementation in the codebase'],
        ['id' => '2', 'status' => 'completed', 'description' => 'Read UserController.php to understand current auth flow'],
        ['id' => '3', 'status' => 'running', 'description' => 'Implement bcrypt password hashing with proper salt generation'],
        ['id' => '4', 'status' => 'pending', 'description' => 'Write unit tests for the new authentication system'],
        ['id' => '5', 'status' => 'pending', 'description' => 'Update API documentation'],
        ['id' => '6', 'status' => 'pending', 'description' => 'Add rate limiting to login endpoint'],
        ['id' => '7', 'status' => 'pending', 'description' => 'Create migration for password field changes'],
    ];

    private array $commands = [
        ['command' => '/help', 'description' => 'Show available commands'],
        ['command' => '/clear', 'description' => 'Clear conversation history'],
        ['command' => '/save', 'description' => 'Save current state'],
        ['command' => '/tasks', 'description' => 'List all tasks'],
        ['command' => '/retry', 'description' => 'Retry last action'],
    ];

    private array $toolLog = [
        ['time' => '14:23:45', 'tool' => 'Grep', 'params' => 'auth* --type=php', 'result' => 'success', 'output' => 'Found 3 matches'],
        ['time' => '14:23:46', 'tool' => 'ReadFile', 'params' => 'UserController.php:120-250', 'result' => 'success', 'output' => 'Read 130 lines'],
        ['time' => '14:23:48', 'tool' => 'FindFiles', 'params' => '*.php --path=src/auth', 'result' => 'success', 'output' => '5 files found'],
        ['time' => '14:23:52', 'tool' => 'WriteFile', 'params' => 'src/auth/Hash.php', 'result' => 'running', 'output' => 'Writing...'],
        ['time' => '14:23:53', 'tool' => 'Terminal', 'params' => 'composer require password', 'result' => 'failed', 'output' => 'Package not found'],
        ['time' => '14:23:55', 'tool' => 'ReadFile', 'params' => 'tests/AuthTest.php', 'result' => 'success', 'output' => 'Read 245 lines'],
    ];

    private array $chatHistory = [
        ['time' => '14:23:40', 'role' => 'user', 'message' => 'Implement secure password hashing for the authentication system'],
        ['time' => '14:23:41', 'role' => 'assistant', 'message' => 'I\'ll implement secure password hashing using bcrypt. Let me first analyze the existing authentication code.'],
        ['time' => '14:23:47', 'role' => 'assistant', 'message' => 'Found the current auth implementation. Now creating a dedicated Hash utility class with bcrypt.'],
        ['time' => '14:23:54', 'role' => 'user', 'message' => 'Make sure to use a cost factor of at least 12'],
        ['time' => '14:23:55', 'role' => 'assistant', 'message' => 'Good point! I\'ll set the cost factor to 12 for better security.'],
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

        // Main layout
        $this->renderHeader();
        $this->renderMainChat();
        $this->renderSidebar();
        $this->renderFooter();
    }

    private function renderHeader(): void
    {
        $this->moveCursor(1, 1);
        echo Ansi::BG_DARK;
        echo ' 🤖 ' . Ansi::BOLD . 'Swarm Agent' . Ansi::RESET . Ansi::BG_DARK;
        echo ' │ ' . Ansi::YELLOW . 'Processing: Implementing password hashing...' . Ansi::RESET . Ansi::BG_DARK;
        echo str_repeat(' ', max(0, $this->terminalWidth - 55)) . Ansi::RESET;
    }

    private function renderMainChat(): void
    {
        $row = 3;

        // Chat header
        $this->moveCursor($row++, 2);
        echo Ansi::BOLD . 'Conversation' . Ansi::RESET;

        // Thin separator line
        $this->moveCursor($row++, 2);
        echo Ansi::DIM . str_repeat('─', $this->mainAreaWidth - 3) . Ansi::RESET;

        $row++;

        // Chat history (clean, minimal)
        $maxRows = $this->terminalHeight - 8;
        foreach ($this->chatHistory as $entry) {
            if ($row > $maxRows) {
                break;
            }

            $this->moveCursor($row++, 2);

            // Timestamp and role
            echo Ansi::DIM . '[' . mb_substr($entry['time'], -8) . ']' . Ansi::RESET . ' ';

            if ($entry['role'] === 'user') {
                echo Ansi::BLUE . '>' . Ansi::RESET . ' ';
            } else {
                echo Ansi::GREEN . '●' . Ansi::RESET . ' ';
            }

            // Message with word wrap
            $messageWidth = $this->mainAreaWidth - 15;
            $wrappedLines = $this->wordWrap($entry['message'], $messageWidth);

            echo $wrappedLines[0];

            // Additional wrapped lines
            for ($i = 1; $i < count($wrappedLines) && $row < $maxRows; $i++) {
                $this->moveCursor($row++, 14);
                echo $wrappedLines[$i];
            }

            $row++; // Empty line between messages
        }
    }

    private function renderSidebar(): void
    {
        $col = $this->mainAreaWidth + 2;

        // Subtle vertical separator
        for ($row = 2; $row <= $this->terminalHeight - 2; $row++) {
            $this->moveCursor($row, $col - 1);
            echo Ansi::DIM . '│' . Ansi::RESET;
        }

        $col++;
        $row = 3;

        // Tasks Section
        $this->moveCursor($row++, $col);
        echo ($this->activeSection === 'tasks' ? Ansi::REVERSE : '') . Ansi::BOLD . ' Tasks ' . Ansi::RESET;

        // Task count summary
        $completed = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));
        $running = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'running'));
        $pending = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'pending'));

        echo ' ' . Ansi::GREEN . '✓' . $completed . Ansi::RESET;
        echo ' ' . Ansi::YELLOW . '●' . $running . Ansi::RESET;
        echo ' ' . Ansi::DIM . '○' . $pending . Ansi::RESET;

        $row++;

        // Scrollable task list
        $taskAreaHeight = 10;
        $visibleTasks = array_slice($this->tasks, $this->taskScrollOffset, $taskAreaHeight);

        foreach ($visibleTasks as $i => $task) {
            if ($row > $row + $taskAreaHeight) {
                break;
            }

            $isSelected = ($this->activeSection === 'tasks' &&
                          $i + $this->taskScrollOffset === $this->selectedTaskIndex);

            $this->moveCursor($row++, $col);

            // Status icon
            $icon = match ($task['status']) {
                'completed' => Ansi::GREEN . '✓',
                'running' => Ansi::YELLOW . '●',
                'pending' => Ansi::DIM . '○',
            };

            echo ($isSelected ? Ansi::REVERSE : '') . $icon . ' ';

            // Task description (word wrapped, max 3 lines)
            $descWidth = $this->sidebarWidth - 5;
            $wrappedLines = $this->wordWrap($task['description'], $descWidth);
            $linesToShow = min(3, count($wrappedLines));

            // First line
            echo $wrappedLines[0];
            if ($isSelected) {
                echo Ansi::RESET;
            }
            echo Ansi::RESET;

            // Additional lines (indented)
            for ($j = 1; $j < $linesToShow && $row < $row + $taskAreaHeight; $j++) {
                $this->moveCursor($row++, $col + 2);
                echo ($isSelected ? Ansi::REVERSE : '') . $wrappedLines[$j] . Ansi::RESET;
            }

            // Show ellipsis if truncated
            if (count($wrappedLines) > 3) {
                $this->moveCursor($row - 1, $col + 2 + mb_strlen($wrappedLines[2]));
                echo Ansi::DIM . '...' . Ansi::RESET;
            }
        }

        // Scroll indicators
        if ($this->taskScrollOffset > 0) {
            $this->moveCursor(5, $col + $this->sidebarWidth - 5);
            echo Ansi::DIM . '▲' . Ansi::RESET;
        }
        if ($this->taskScrollOffset + $taskAreaHeight < count($this->tasks)) {
            $this->moveCursor($row - 1, $col + $this->sidebarWidth - 5);
            echo Ansi::DIM . '▼' . Ansi::RESET;
        }

        $row++;

        // Commands Section
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . str_repeat('─', $this->sidebarWidth - 3) . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo ($this->activeSection === 'commands' ? Ansi::REVERSE : '') . Ansi::BOLD . ' Commands ' . Ansi::RESET;

        $row++;

        // Command list (compact)
        foreach (array_slice($this->commands, 0, 3) as $cmd) {
            $this->moveCursor($row++, $col);
            echo Ansi::CYAN . $cmd['command'] . Ansi::RESET . ' ' . Ansi::DIM . $cmd['description'] . Ansi::RESET;
        }

        $row++;

        // Tool Log Section
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . str_repeat('─', $this->sidebarWidth - 3) . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo ($this->activeSection === 'tools' ? Ansi::REVERSE : '') . Ansi::BOLD . ' Tool Activity ' . Ansi::RESET;

        $row++;

        // Tool log (scrollable)
        $toolAreaHeight = $this->terminalHeight - $row - 3;
        $visibleTools = array_slice($this->toolLog, $this->toolLogScrollOffset, $toolAreaHeight / 3);

        foreach ($visibleTools as $log) {
            if ($row > $this->terminalHeight - 3) {
                break;
            }

            // Time and tool name
            $this->moveCursor($row++, $col);
            echo Ansi::DIM . mb_substr($log['time'], -8) . Ansi::RESET . ' ';

            $resultColor = match ($log['result']) {
                'success' => Ansi::GREEN,
                'running' => Ansi::YELLOW,
                'failed' => Ansi::RED,
                default => '',
            };

            echo $resultColor . $log['tool'] . Ansi::RESET;

            // Parameters (truncated)
            $this->moveCursor($row++, $col + 2);
            echo Ansi::DIM . $this->truncate($log['params'], $this->sidebarWidth - 5) . Ansi::RESET;

            // Result/output
            $this->moveCursor($row++, $col + 2);
            echo $resultColor . '→ ' . $log['output'] . Ansi::RESET;

            $row++;
        }

        // Tool scroll indicators
        if ($this->toolLogScrollOffset > 0) {
            $this->moveCursor($row - $toolAreaHeight, $col + $this->sidebarWidth - 5);
            echo Ansi::DIM . '▲' . Ansi::RESET;
        }
        if ($this->toolLogScrollOffset + ($toolAreaHeight / 3) < count($this->toolLog)) {
            $this->moveCursor($this->terminalHeight - 3, $col + $this->sidebarWidth - 5);
            echo Ansi::DIM . '▼' . Ansi::RESET;
        }
    }

    private function renderFooter(): void
    {
        $this->moveCursor($this->terminalHeight, 2);
        echo Ansi::DIM . '↑↓: Scroll ' . $this->activeSection . ' | Tab: Switch section | Q: Quit' . Ansi::RESET;
    }

    private function handleInput(string $key): void
    {
        switch ($key) {
            case "\t": // Tab - switch sections
                $sections = ['tasks', 'commands', 'tools'];
                $currentIndex = array_search($this->activeSection, $sections);
                $this->activeSection = $sections[($currentIndex + 1) % count($sections)];
                break;
            case 'k': // Up
                if ($this->activeSection === 'tasks') {
                    if ($this->selectedTaskIndex > 0) {
                        $this->selectedTaskIndex--;
                        if ($this->selectedTaskIndex < $this->taskScrollOffset) {
                            $this->taskScrollOffset = $this->selectedTaskIndex;
                        }
                    }
                } elseif ($this->activeSection === 'tools') {
                    if ($this->toolLogScrollOffset > 0) {
                        $this->toolLogScrollOffset--;
                    }
                }
                break;
            case 'j': // Down
                if ($this->activeSection === 'tasks') {
                    if ($this->selectedTaskIndex < count($this->tasks) - 1) {
                        $this->selectedTaskIndex++;
                        if ($this->selectedTaskIndex >= $this->taskScrollOffset + 10) {
                            $this->taskScrollOffset++;
                        }
                    }
                } elseif ($this->activeSection === 'tools') {
                    if ($this->toolLogScrollOffset < count($this->toolLog) - 3) {
                        $this->toolLogScrollOffset++;
                    }
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

            // Handle escape sequences for arrow keys
            if ($key === "\033") {
                $seq = $key . fgetc(STDIN) . fgetc(STDIN);
                if ($seq === "\033[A") {
                    return 'k';
                } // Up
                if ($seq === "\033[B") {
                    return 'j';
                } // Down
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
echo "Starting Practical Sidebar Demo...\n";
echo "This shows the actual information you need in a clean, minimal layout.\n";
sleep(1);
$demo = new PracticalSidebar;
$demo->run();
echo "\nDemo ended.\n";
