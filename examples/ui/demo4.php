#!/usr/bin/env php
<?php

/**
 * Demo 4: Enhanced Activity Feed with Relative Times
 * Shows relative timestamps, syntax highlighting, collapsible sections
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const ITALIC = "\033[3m";
const CLEAR = "\033[2J\033[H";

// Colors
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const CYAN = "\033[36m";
const MAGENTA = "\033[35m";
const RED = "\033[31m";
const WHITE = "\033[37m";
const GRAY = "\033[90m";

// Backgrounds
const BG_DARK = "\033[48;5;236m";
const BG_CODE = "\033[48;5;237m";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

function getRelativeTime(int $timestamp): string
{
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);

        return $mins . 'm ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);

        return $hours . 'h ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);

        return $days . 'd ago';
    }

    return date('M j', $timestamp);
}

function highlightCode(string $code, string $language = 'php'): string
{
    // Simple syntax highlighting
    $highlighted = $code;

    if ($language === 'php') {
        // Keywords
        $keywords = ['function', 'return', 'if', 'else', 'foreach', 'class', 'public', 'private', 'protected', 'new'];
        foreach ($keywords as $keyword) {
            $highlighted = preg_replace(
                '/\b(' . $keyword . ')\b/',
                MAGENTA . '$1' . RESET,
                $highlighted
            );
        }

        // Strings
        $highlighted = preg_replace(
            '/(["\'])([^"\']*)\1/',
            GREEN . '$1$2$1' . RESET,
            $highlighted
        );

        // Variables
        $highlighted = preg_replace(
            '/(\$\w+)/',
            CYAN . '$1' . RESET,
            $highlighted
        );

        // Comments
        $highlighted = preg_replace(
            '/(\/\/.*)/',
            GRAY . ITALIC . '$1' . RESET,
            $highlighted
        );
    }

    return $highlighted;
}

function renderActivityFeed(): void
{
    $activities = [
        [
            'type' => 'command',
            'content' => 'composer require symfony/console',
            'timestamp' => time() - 30,
            'icon' => '$',
            'color' => BLUE,
        ],
        [
            'type' => 'assistant',
            'content' => 'I\'ll help you set up the Symfony Console component.',
            'timestamp' => time() - 120,
            'icon' => '●',
            'color' => GREEN,
            'thought' => 'The user wants to add CLI functionality. Symfony Console is a great choice for building command-line interfaces in PHP.',
            'expanded' => false,
        ],
        [
            'type' => 'code',
            'content' => "class CreateUserCommand extends Command {\n    protected function configure() {\n        \$this->setName('user:create')\n             ->setDescription('Create a new user')\n             ->addArgument('username', InputArgument::REQUIRED);\n    }\n    \n    protected function execute(InputInterface \$input, OutputInterface \$output) {\n        \$username = \$input->getArgument('username');\n        // Create user logic here\n        \$output->writeln('User created: ' . \$username);\n        return Command::SUCCESS;\n    }\n}",
            'timestamp' => time() - 300,
            'icon' => '</>',
            'color' => YELLOW,
            'language' => 'php',
            'collapsed' => true,
        ],
        [
            'type' => 'tool',
            'content' => 'WriteFile',
            'params' => 'src/Command/CreateUserCommand.php',
            'result' => 'Success',
            'timestamp' => time() - 420,
            'icon' => '>',
            'color' => CYAN,
        ],
        [
            'type' => 'error',
            'content' => 'Class "InputArgument" not found',
            'timestamp' => time() - 600,
            'icon' => '✗',
            'color' => RED,
            'details' => "Stack trace:\n  at src/Command/CreateUserCommand.php:12\n  at vendor/symfony/console/Application.php:145",
            'collapsed' => true,
        ],
        [
            'type' => 'system',
            'content' => 'Fixed missing import statement',
            'timestamp' => time() - 660,
            'icon' => '!',
            'color' => YELLOW,
        ],
    ];

    $row = 3;
    moveCursor($row++, 2);
    echo BOLD . 'Activity Feed' . RESET . ' ' . DIM . '(Real-time updates)' . RESET;
    moveCursor($row++, 2);
    echo DIM . str_repeat('─', 70) . RESET;

    foreach ($activities as $i => $activity) {
        // Timestamp and icon
        moveCursor($row, 2);
        echo GRAY . '[' . getRelativeTime($activity['timestamp']) . ']' . RESET;

        moveCursor($row, 14);
        echo $activity['color'] . $activity['icon'] . RESET;

        // Main content
        moveCursor($row, 17);

        switch ($activity['type']) {
            case 'command':
                echo WHITE . $activity['content'] . RESET;
                $row++;
                break;
            case 'assistant':
                echo $activity['content'];
                $row++;

                // Thought bubble (collapsible)
                if (isset($activity['thought'])) {
                    moveCursor($row, 17);
                    if ($activity['expanded']) {
                        echo DIM . ITALIC . '💭 ' . wordwrap($activity['thought'], 60, "\n" . str_repeat(' ', 19), true) . RESET;
                        $row += mb_substr_count(wordwrap($activity['thought'], 60), "\n") + 1;
                        moveCursor($row, 17);
                        echo DIM . "   [press 't' to collapse]" . RESET;
                    } else {
                        echo DIM . ITALIC . '💭 ' . mb_substr($activity['thought'], 0, 50) . '...' . RESET;
                        moveCursor($row, 70);
                        echo DIM . "[press 't' to expand]" . RESET;
                    }
                    $row++;
                }
                break;
            case 'code':
                if ($activity['collapsed']) {
                    echo YELLOW . '<code>' . RESET . ' ' . DIM . mb_substr(str_replace("\n", ' ', $activity['content']), 0, 40) . '...' . RESET;
                    moveCursor($row, 70);
                    echo DIM . "[press 'c' to expand]" . RESET;
                    $row++;
                } else {
                    echo YELLOW . '<code>' . RESET;
                    $row++;

                    // Code block with syntax highlighting
                    $lines = explode("\n", $activity['content']);
                    foreach ($lines as $lineNum => $line) {
                        moveCursor($row, 17);
                        echo BG_CODE . GRAY . mb_str_pad($lineNum + 1, 3, ' ', STR_PAD_LEFT) . RESET;
                        echo BG_CODE . ' ' . highlightCode($line, $activity['language']) . str_repeat(' ', max(0, 65 - mb_strlen($line))) . RESET;
                        $row++;
                    }
                    moveCursor($row, 17);
                    echo DIM . "[press 'c' to collapse]" . RESET;
                    $row++;
                }
                break;
            case 'tool':
                echo $activity['content'] . ' ' . DIM . $activity['params'] . RESET;
                echo ' → ';
                if ($activity['result'] === 'Success') {
                    echo GREEN . '✓ ' . $activity['result'] . RESET;
                } else {
                    echo RED . '✗ ' . $activity['result'] . RESET;
                }
                $row++;
                break;
            case 'error':
                echo RED . $activity['content'] . RESET;
                $row++;
                if (isset($activity['details'])) {
                    if ($activity['collapsed']) {
                        moveCursor($row, 17);
                        echo DIM . '   View details...' . RESET;
                        moveCursor($row, 70);
                        echo DIM . "[press 'e' to expand]" . RESET;
                    } else {
                        foreach (explode("\n", $activity['details']) as $detail) {
                            moveCursor($row, 17);
                            echo RED . DIM . '   ' . $detail . RESET;
                            $row++;
                        }
                        moveCursor($row, 17);
                        echo DIM . "[press 'e' to collapse]" . RESET;
                    }
                    $row++;
                }
                break;
            case 'system':
                echo DIM . $activity['content'] . RESET;
                $row++;
                break;
        }

        // Separator
        if ($i < count($activities) - 1) {
            moveCursor($row, 2);
            echo DIM . str_repeat('·', 70) . RESET;
            $row++;
        }
    }

    // Stats bar
    $row += 2;
    moveCursor($row, 2);
    echo BG_DARK . ' ';
    echo WHITE . 'Stats: ' . RESET . BG_DARK;
    echo GREEN . '12 tasks ' . RESET . BG_DARK;
    echo DIM . WHITE . '│' . RESET . BG_DARK . ' ';
    echo YELLOW . '3 warnings ' . RESET . BG_DARK;
    echo DIM . WHITE . '│' . RESET . BG_DARK . ' ';
    echo RED . '1 error ' . RESET . BG_DARK;
    echo DIM . WHITE . '│' . RESET . BG_DARK . ' ';
    echo CYAN . '45 files modified ' . RESET . BG_DARK;
    echo str_repeat(' ', 20) . RESET;
}

// Clear and render
echo CLEAR;

moveCursor(1, 2);
echo BOLD . 'Enhanced Activity Feed Demo' . RESET;

renderActivityFeed();

// Instructions
$height = (int) exec('tput lines') ?: 24;
moveCursor($height - 2, 2);
echo DIM . 'Interactive keys: [t] toggle thoughts │ [c] toggle code │ [e] toggle errors' . RESET;
moveCursor($height - 1, 2);
echo DIM . 'Press Ctrl+C to exit' . RESET;

// Keep running
while (true) {
    sleep(1);
}
