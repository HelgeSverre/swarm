<?php

/** @noinspection PhpUnused */

namespace HelgeSverre\Swarm\CLI;

use HelgeSverre\Swarm\Agent\AgentResponse;

/**
 * TUIRenderer: Rich Terminal User Interface for CodeSwarm CLI
 *
 * Uses ANSI escape codes for colors, positioning, and formatting
 * Provides a real-time updating interface similar to modern CLI tools
 */
class TUIRenderer
{
    // ANSI Color codes
    protected const array COLORS = [
        'reset' => "\033[0m",
        'bold' => "\033[1m",
        'dim' => "\033[2m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'gray' => "\033[90m",
        'dark_gray' => "\033[90m",
        'light_gray' => "\033[37m",
        'bright_red' => "\033[91m",
        'bright_green' => "\033[92m",
        'bright_yellow' => "\033[93m",
        'bright_blue' => "\033[94m",
        'bright_magenta' => "\033[95m",
        'bright_cyan' => "\033[96m",
        'bright_white' => "\033[97m",
    ];

    // Theme colors
    protected const THEME = [
        'border' => 'dark_gray',
        'header' => 'bright_white',
        'accent' => 'bright_blue',
        'success' => 'bright_green',
        'error' => 'bright_red',
        'warning' => 'bright_yellow',
        'info' => 'bright_cyan',
        'muted' => 'gray',
    ];

    // Box drawing characters
    protected const array BOX = [
        'horizontal' => '─',
        'vertical' => '│',
        'top_left' => '┌',
        'top_right' => '┐',
        'bottom_left' => '└',
        'bottom_right' => '┘',
        'cross' => '┼',
        'tee_down' => '┬',
        'tee_up' => '┴',
        'tee_right' => '├',
        'tee_left' => '┤',
    ];

    // Status icons
    protected const array ICONS = [
        'pending' => '⏸',
        'running' => '⏳',
        'completed' => '✓',
        'failed' => '✗',
        'robot' => '🤖',
        'tool' => '🔧',
        'task' => '🎯',
        'error' => '❌',
        'success' => '✅',
        'info' => 'ℹ',
        'warning' => '⚠',
    ];

    protected int $terminalWidth;

    protected int $terminalHeight;

    protected array $history = [];

    protected int $maxHistoryLines = 20;

    protected int $currentLine = 1;

    public function __construct()
    {
        $this->detectTerminalSize();
        $this->enableRawMode();

        // Register cleanup on exit
        register_shutdown_function([$this, 'cleanup']);
    }

    public function showWelcome(): void
    {
        $this->clearScreen();

        // Center the welcome box
        $boxWidth = 45;
        $leftPadding = str_repeat(' ', (int) (($this->terminalWidth - $boxWidth) / 2));

        echo "\n\n";
        echo $leftPadding . $this->colorize('┌' . str_repeat('─', $boxWidth - 2) . '┐', self::THEME['border']) . "\n";
        echo $leftPadding . $this->colorize('│', self::THEME['border']) .
            $this->colorize(mb_str_pad('Swarm CLI v1.0', $boxWidth - 2, ' ', STR_PAD_BOTH), self::THEME['header'], 'bold') .
            $this->colorize('│', self::THEME['border']) . "\n";
        echo $leftPadding . $this->colorize('│', self::THEME['border']) .
            $this->colorize(mb_str_pad('AI-Powered Coding Assistant', $boxWidth - 2, ' ', STR_PAD_BOTH), self::THEME['muted']) .
            $this->colorize('│', self::THEME['border']) . "\n";
        echo $leftPadding . $this->colorize('└' . str_repeat('─', $boxWidth - 2) . '┘', self::THEME['border']) . "\n";
        echo "\n";
        echo $this->colorize('  🤖 Ready to help with your coding tasks!', self::THEME['success']) . "\n";
        echo $this->colorize('  💡 Try: "Create a Laravel migration for users"', self::THEME['warning']) . "\n";
        echo $this->colorize("  📝 Type 'help' for commands or 'exit' to quit", self::THEME['muted']) . "\n";
        echo "\n";

        $this->waitForKeypress();
    }

    public function prompt(string $message): string
    {
        // Draw input box at the bottom
        $inputStartRow = $this->terminalHeight - 3;
        $this->moveCursor($inputStartRow, 1);

        // Draw input box
        echo $this->drawBoxTop('', self::THEME['border']);
        echo $this->drawBoxLine('', self::THEME['border']); // Empty line for input
        echo $this->drawBoxBottom(self::THEME['border']);

        // Position cursor inside the box for the prompt
        $this->moveCursor($inputStartRow + 1, 3);
        echo $this->colorize($message . ' ', self::THEME['accent']);

        // Get cursor position after the prompt
        $promptEndCol = 3 + mb_strlen($message) + 1;

        // Restore normal terminal mode for input
        $this->restoreNormalMode();

        // Use readline if available for better input handling
        if (function_exists('readline')) {
            $input = readline('');
        } else {
            // Fallback to fgets if readline not available
            $input = trim(fgets(STDIN));
        }

        // Don't move to next line - stay in the input box
        // The next refresh will clear the screen anyway

        return trim($input);
    }

    public function displayResponse(AgentResponse $response): void
    {
        $this->addToHistory('agent', $response->getMessage(), 'bright_green');
        // Don't show notification as it disrupts the UI flow
        // The response will be shown in the next refresh cycle
    }

    public function showProcessing(): void
    {
        // Show a processing indicator in the input area
        $inputStartRow = $this->terminalHeight - 2;
        $this->moveCursor($inputStartRow, 3);

        // Clear the line first
        echo "\033[K";

        // Show animated processing message
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $messages = [
            'Processing',
            'Thinking',
            'Analyzing',
            'Working on it',
        ];

        $message = $messages[array_rand($messages)];
        $frame = $frames[time() % count($frames)];

        echo $this->colorize("{$frame} {$message}...", self::THEME['warning']);
    }

    public function showNotification(string $message, string $type = 'info'): void
    {
        $color = match ($type) {
            'error' => self::THEME['error'],
            'success' => self::THEME['success'],
            'warning' => self::THEME['warning'],
            default => self::THEME['info']
        };

        $this->moveCursor(1, 1);
        echo $this->colorize("  ⚡ {$message}", $color, 'bold') . str_repeat(' ', max(0, $this->terminalWidth - mb_strlen($message) - 6));
        usleep(1500000); // Show for 1.5 seconds
    }

    public function displayError(string $error): void
    {
        $this->addToHistory('error', $error, 'bright_red');
        $this->showNotification('❌ Error: ' . mb_substr($error, 0, 50) . '...', 'red');
    }

    public function displayToolCall(string $tool, array $params, $result): void
    {
        $message = "🔧 {$tool}(" . $this->formatParams($params) . ')';
        if ($result) {
            $message .= ' → ' . $this->summarizeResult($result);
        }
        $this->addToHistory('tool', $message, 'cyan');
        $this->refresh([]); // Quick refresh to show the tool call
    }

    public function refresh(array $status): void
    {
        $this->clearScreen();
        $this->drawHeader();
        $this->drawTaskStatus($status);
        echo "\n"; // Add spacing between sections
        $this->drawRecentActivity();
        $this->drawFooter();

        // Leave space for input box at the bottom
        $remainingLines = $this->terminalHeight - $this->getCurrentLine() - 5; // Adjusted for extra newline
        if ($remainingLines > 0) {
            echo str_repeat("\n", $remainingLines);
        }
    }

    public function updateTaskProgress(array $tasks): void
    {
        // This would be called during task execution to update the display
        $this->refresh(['tasks' => $tasks]);
    }

    public function cleanup(): void
    {
        // Ensure terminal is in normal mode
        $this->restoreNormalMode();
        echo self::COLORS['reset'];
    }

    // Additional utility methods for specific use cases

    public function showSpinner(string $message, callable $task): mixed
    {
        $spinnerChars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $i = 0;

        // Run task in background simulation (for demo purposes)
        echo $this->colorize("  {$spinnerChars[$i]} {$message}", 'yellow');

        $result = $task();

        echo "\r" . $this->colorize("  ✓ {$message}", 'green') . "\n";

        return $result;
    }

    public function showProgressBar(int $current, int $total, string $message = ''): void
    {
        $percent = round(($current / $total) * 100);
        $barLength = 30;
        $filledLength = (int) round(($percent / 100) * $barLength);

        $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);

        echo "\r" . $this->colorize("  [{$bar}] {$percent}% {$message}", 'cyan');

        if ($current >= $total) {
            echo "\n";
        }
    }

    protected function detectTerminalSize(): void
    {
        // Try to get terminal size
        $output = [];
        exec('stty size 2>/dev/null', $output);

        if (! empty($output[0])) {
            [$height, $width] = explode(' ', $output[0]);
            $this->terminalHeight = (int) $height;
            $this->terminalWidth = (int) $width;
        } else {
            // Try tput as fallback
            $height = exec('tput lines 2>/dev/null');
            $width = exec('tput cols 2>/dev/null');

            if ($height && $width) {
                $this->terminalHeight = (int) $height;
                $this->terminalWidth = (int) $width;
            } else {
                // Final fallback defaults
                $this->terminalHeight = 24;
                $this->terminalWidth = 80;
            }
        }
    }

    protected function enableRawMode(): void
    {
        // We no longer use persistent raw mode
        // Terminal modes are managed per-operation
    }

    protected function clearScreen(): void
    {
        // Clear screen and scrollback buffer, then move cursor to top-left
        echo "\033[2J\033[3J\033[H";
    }

    protected function colorize(string $text, string $color, ?string $style = null): string
    {
        $codes = [];
        if ($style) {
            $codes[] = self::COLORS[$style] ?? '';
        }
        $codes[] = self::COLORS[$color] ?? '';

        return implode('', $codes) . $text . self::COLORS['reset'];
    }

    protected function waitForKeypress(): void
    {
        echo $this->colorize("\n  Press any key to continue...", 'gray');
        // Temporarily enable raw mode just for single keypress
        if (function_exists('system')) {
            system('stty -icanon min 1 time 0 2>/dev/null');
        }
        fgetc(STDIN);
        // Restore normal mode
        $this->restoreNormalMode();
    }

    protected function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    protected function addToHistory(string $type, string $message, string $color = 'white'): void
    {
        $this->history[] = [
            'type' => $type,
            'message' => $message,
            'color' => $color,
            'timestamp' => time(),
        ];

        // Keep history manageable
        if (count($this->history) > $this->maxHistoryLines) {
            array_shift($this->history);
        }
    }

    protected function formatParams(array $params): string
    {
        return implode(', ', array_map(
            fn ($key, $value) => sprintf(
                '%s: %s',
                $key,
                json_encode(
                    is_string($value) && mb_strlen($value) > 20
                        ? mb_substr($value, 0, 17) . '...'
                        : $value
                )
            ),
            array_keys($params),
            array_values($params)
        ));
    }

    protected function summarizeResult(mixed $result): string
    {
        if (! is_array($result)) {
            return 'completed';
        }

        $data = $result['data'] ?? $result;

        return match (true) {
            isset($data['files']) => count($data['files']) . ' files found',
            isset($data['stdout']) => 'exit: ' . ($data['return_code'] ?? '?'),
            isset($data['bytes_written']) => $data['bytes_written'] . ' bytes written',
            default => 'completed'
        };
    }

    protected function drawHeader(): void
    {
        $title = 'Swarm - AI Coding Assistant';
        $time = date('H:i:s');

        // Use the new helper with theme colors
        $leftPart = $this->colorize(self::BOX['top_left'] . self::BOX['horizontal'] . ' ', self::THEME['border']);
        $titlePart = $this->colorize($title, self::THEME['header'], 'bold');
        $timePart = $this->colorize($time, self::THEME['muted']);
        $rightPart = $this->colorize(' ' . self::BOX['top_right'], self::THEME['border']);

        $titleLength = mb_strlen($title);
        $timeLength = mb_strlen($time);
        $remainingWidth = $this->terminalWidth - $titleLength - $timeLength - 6;

        echo $leftPart . $titlePart .
            $this->colorize(str_repeat(' ', $remainingWidth), self::THEME['border']) .
            $timePart . $rightPart . "\n";
    }

    protected function drawTaskStatus(array $status): void
    {
        $tasks = $status['tasks'] ?? [];
        $currentTask = $status['current_task'] ?? null;

        if (empty($tasks) && ! $currentTask) {
            echo $this->drawBoxLine('🎯 No active tasks', self::THEME['border'], self::THEME['muted']);

            return;
        }

        if ($currentTask) {
            $currentDescription = $currentTask['description'] ?? '';
            $content = '🎯 Current: ' . $currentDescription;
            echo $this->drawBoxLine($content, self::THEME['border'], self::THEME['accent']);
        }

        // Draw task list
        $maxTasks = min(5, count($tasks));
        for ($i = 0; $i < $maxTasks; $i++) {
            $task = $tasks[$i];
            $icon = self::ICONS[$task['status']] ?? '?';
            $color = $this->getStatusColor($task['status']);
            $description = $task['description'] ?? '';

            $content = "  {$icon} {$description}";
            echo $this->drawBoxLine($content, self::THEME['border'], $color);
        }

        if (count($tasks) > $maxTasks) {
            $moreText = '  ... and ' . (count($tasks) - $maxTasks) . ' more';
            echo $this->drawBoxLine($moreText, self::THEME['border'], self::THEME['muted']);
        }
    }

    protected function truncate(?string $text, int $length): string
    {
        if ($text === null) {
            return '';
        }

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 3) . '...' : $text;
    }

    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => self::THEME['success'],
            'running', 'executing' => self::THEME['warning'],
            'failed' => self::THEME['error'],
            'pending' => self::THEME['muted'],
            default => 'white'
        };
    }

    protected function drawRecentActivity(): void
    {
        echo $this->drawBoxSeparator('Recent Activity', self::THEME['border']);

        $recentHistory = array_slice($this->history, -8);

        if (empty($recentHistory)) {
            echo $this->drawBoxLine('No recent activity', self::THEME['border'], self::THEME['muted']);
        } else {
            foreach ($recentHistory as $entry) {
                $icon = $entry['type'] === 'tool' ? '🔧' : ($entry['type'] === 'error' ? '❌' : '💬');
                $message = $entry['message'] ?? '';
                $content = "{$icon} {$message}";
                echo $this->drawBoxLine($content, self::THEME['border'], $entry['color']);
            }
        }
    }

    protected function drawFooter(): void
    {
        echo $this->drawBoxBottom(self::THEME['border']);
        echo $this->colorize('💡 Commands: help | exit | clear', self::THEME['muted']) . "\n";
    }

    protected function restoreNormalMode(): void
    {
        // Restore normal terminal mode with echo and canonical mode
        if (function_exists('system')) {
            system('stty echo icanon 2>/dev/null');
        }
    }

    // Helper methods for drawing UI elements

    protected function drawBoxTop(string $title = '', string $borderColor = 'gray'): string
    {
        $leftCorner = $this->colorize(self::BOX['top_left'], $borderColor);
        $rightCorner = $this->colorize(self::BOX['top_right'], $borderColor);
        $horizontal = self::BOX['horizontal'];

        if ($title) {
            $titleFormatted = $this->colorize(" {$title} ", self::THEME['accent']);
            $titleLength = mb_strlen($title) + 2;
            $remainingWidth = $this->terminalWidth - $titleLength - 4;
            $line = $leftCorner . $this->colorize($horizontal, $borderColor) . $titleFormatted .
                $this->colorize(str_repeat($horizontal, $remainingWidth), $borderColor) . $rightCorner;
        } else {
            $line = $leftCorner . $this->colorize(str_repeat($horizontal, $this->terminalWidth - 2), $borderColor) . $rightCorner;
        }

        return $line . "\n";
    }

    protected function drawBoxBottom(string $borderColor = 'gray'): string
    {
        $leftCorner = $this->colorize(self::BOX['bottom_left'], $borderColor);
        $rightCorner = $this->colorize(self::BOX['bottom_right'], $borderColor);
        $horizontal = $this->colorize(str_repeat(self::BOX['horizontal'], $this->terminalWidth - 2), $borderColor);

        return $leftCorner . $horizontal . $rightCorner . "\n";
    }

    protected function drawBoxLine(string $content, string $borderColor = 'gray', string $contentColor = 'white'): string
    {
        $leftBorder = $this->colorize(self::BOX['vertical'] . ' ', $borderColor);
        $rightBorder = $this->colorize(' ' . self::BOX['vertical'], $borderColor);

        // Calculate available width for content (accounting for borders and padding)
        $availableWidth = $this->terminalWidth - 4;

        // Truncate content if needed
        $displayContent = $this->truncate($content, $availableWidth);
        $contentLength = mb_strlen($displayContent);

        // Pad with spaces to fill the line
        $padding = str_repeat(' ', max(0, $availableWidth - $contentLength));

        return $leftBorder . $this->colorize($displayContent, $contentColor) . $padding . $rightBorder . "\n";
    }

    protected function drawBoxSeparator(string $title = '', string $borderColor = 'gray'): string
    {
        $leftTee = $this->colorize(self::BOX['tee_right'], $borderColor);
        $rightTee = $this->colorize(self::BOX['tee_left'], $borderColor);
        $horizontal = self::BOX['horizontal'];

        if ($title) {
            $titleFormatted = $this->colorize(" {$title} ", self::THEME['accent']);
            $titleLength = mb_strlen($title) + 2;
            $remainingWidth = $this->terminalWidth - $titleLength - 4;
            $line = $leftTee . $this->colorize($horizontal, $borderColor) . $titleFormatted .
                $this->colorize(str_repeat($horizontal, $remainingWidth), $borderColor) . $rightTee;
        } else {
            $line = $leftTee . $this->colorize(str_repeat($horizontal, $this->terminalWidth - 2), $borderColor) . $rightTee;
        }

        return $line . "\n";
    }

    protected function padLine(string $content, int $width): string
    {
        $contentLength = mb_strlen($content);
        if ($contentLength >= $width) {
            return mb_substr($content, 0, $width);
        }

        return $content . str_repeat(' ', $width - $contentLength);
    }

    protected function getCurrentLine(): int
    {
        // Count lines used by current UI elements
        $lines = 1; // Header
        $lines += 5; // Task status (minimum)
        $lines += 1; // Separator
        $lines += min(8, count($this->history)) + 1; // Recent activity
        $lines += 2; // Footer

        return $lines;
    }
}
