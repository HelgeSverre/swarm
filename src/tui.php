<?php

namespace HelgeSverre\Swarm;

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
        'bright_red' => "\033[91m",
        'bright_green' => "\033[92m",
        'bright_yellow' => "\033[93m",
        'bright_blue' => "\033[94m",
        'bright_magenta' => "\033[95m",
        'bright_cyan' => "\033[96m",
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

    protected $terminalWidth;
    protected $terminalHeight;
    protected $history = [];
    protected $maxHistoryLines = 20;

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

        $welcome = [
            "",
            $this->colorize("  ┌─────────────────────────────────────────┐", 'cyan'),
            $this->colorize("  │", 'cyan') . $this->colorize("          CodeSwarm CLI v1.0            ", 'bright_cyan', 'bold') . $this->colorize("│", 'cyan'),
            $this->colorize("  │", 'cyan') . $this->colorize("      AI-Powered Coding Assistant       ", 'white') . $this->colorize("│", 'cyan'),
            $this->colorize("  └─────────────────────────────────────────┘", 'cyan'),
            "",
            $this->colorize("  🤖 Ready to help with your coding tasks!", 'bright_green'),
            $this->colorize("  💡 Try: \"Create a Laravel migration for users\"", 'yellow'),
            $this->colorize("  📝 Type 'help' for commands or 'exit' to quit", 'gray'),
            "",
        ];

        foreach ($welcome as $line) {
            echo $line . "\n";
        }

        $this->waitForKeypress();
    }

    public function refresh(array $status): void
    {
        $this->clearScreen();
        $this->drawHeader();
        $this->drawTaskStatus($status);
        $this->drawRecentActivity();
        $this->drawFooter();
    }

    public function prompt(string $message): string
    {
        $this->moveCursor($this->terminalHeight - 2, 1);
        echo $this->colorize("┌─ Input ", 'cyan') . str_repeat('─', $this->terminalWidth - 10) . $this->colorize("┐", 'cyan') . "\n";
        echo $this->colorize("│ ", 'cyan') . $this->colorize($message . " ", 'white');

        // Read user input
        $input = trim(fgets(STDIN));

        return $input;
    }

    public function displayResponse(AgentResponse $response): void
    {
        $this->addToHistory('agent', $response->getMessage(), 'bright_green');
        $this->showNotification("✅ Task completed", 'green');
    }

    public function displayError(string $error): void
    {
        $this->addToHistory('error', $error, 'bright_red');
        $this->showNotification("❌ Error: " . substr($error, 0, 50) . "...", 'red');
    }

    public function displayToolCall(string $tool, array $params, $result): void
    {
        $message = "🔧 $tool(" . $this->formatParams($params) . ")";
        if ($result) {
            $message .= " → " . $this->summarizeResult($result);
        }
        $this->addToHistory('tool', $message, 'cyan');
        $this->refresh([]); // Quick refresh to show the tool call
    }

    public function updateTaskProgress(array $tasks): void
    {
        // This would be called during task execution to update the display
        $this->refresh(['tasks' => $tasks]);
    }

    public function showNotification(string $message, string $type = 'info'): void
    {
        $color = match ($type) {
            'error' => 'bright_red',
            'success' => 'bright_green',
            'warning' => 'bright_yellow',
            default => 'bright_blue'
        };

        $this->moveCursor(1, 1);
        echo $this->colorize("  ⚡ $message", $color, 'bold') . str_repeat(' ', max(0, $this->terminalWidth - strlen($message) - 6));
        usleep(1500000); // Show for 1.5 seconds
    }

    protected function drawHeader(): void
    {
        $title = "CodeSwarm CLI - AI Coding Assistant";
        $time = date('H:i:s');

        echo $this->colorize("┌─ $title ", 'cyan') .
            str_repeat('─', max(0, $this->terminalWidth - strlen($title) - strlen($time) - 8)) .
            $this->colorize(" $time ┐", 'cyan') . "\n";
    }

    protected function drawTaskStatus(array $status): void
    {
        $tasks = $status['tasks'] ?? [];
        $currentTask = $status['current_task'] ?? null;

        if (empty($tasks) && !$currentTask) {
            echo $this->colorize("│ ", 'cyan') .
                $this->colorize("🎯 No active tasks", 'gray') .
                str_repeat(' ', $this->terminalWidth - 18) .
                $this->colorize("│", 'cyan') . "\n";
            return;
        }

        if ($currentTask) {
            echo $this->colorize("│ ", 'cyan') .
                $this->colorize("🎯 Current: ", 'bright_yellow', 'bold') .
                $this->colorize($this->truncate($currentTask['description'], $this->terminalWidth - 25), 'white') .
                str_repeat(' ', max(0, $this->terminalWidth - strlen($currentTask['description']) - 25)) .
                $this->colorize("│", 'cyan') . "\n";
        }

        // Draw task list
        $maxTasks = min(5, count($tasks));
        for ($i = 0; $i < $maxTasks; $i++) {
            $task = $tasks[$i];
            $icon = self::ICONS[$task['status']] ?? '?';
            $color = $this->getStatusColor($task['status']);

            echo $this->colorize("│ ", 'cyan') .
                $this->colorize("  $icon ", $color) .
                $this->colorize($this->truncate($task['description'], $this->terminalWidth - 15), 'white') .
                str_repeat(' ', max(0, $this->terminalWidth - strlen($task['description']) - 15)) .
                $this->colorize("│", 'cyan') . "\n";
        }

        if (count($tasks) > $maxTasks) {
            echo $this->colorize("│ ", 'cyan') .
                $this->colorize("  ... and " . (count($tasks) - $maxTasks) . " more", 'gray') .
                str_repeat(' ', max(0, $this->terminalWidth - 20)) .
                $this->colorize("│", 'cyan') . "\n";
        }
    }

    protected function drawRecentActivity(): void
    {
        echo $this->colorize("├─ Recent Activity ", 'cyan') .
            str_repeat('─', max(0, $this->terminalWidth - 19)) .
            $this->colorize("┤", 'cyan') . "\n";

        $recentHistory = array_slice($this->history, -8);

        if (empty($recentHistory)) {
            echo $this->colorize("│ ", 'cyan') .
                $this->colorize("No recent activity", 'gray') .
                str_repeat(' ', $this->terminalWidth - 20) .
                $this->colorize("│", 'cyan') . "\n";
        } else {
            foreach ($recentHistory as $entry) {
                $icon = $entry['type'] === 'tool' ? '🔧' : ($entry['type'] === 'error' ? '❌' : '💬');
                echo $this->colorize("│ ", 'cyan') .
                    $this->colorize("$icon ", $entry['color']) .
                    $this->colorize($this->truncate($entry['message'], $this->terminalWidth - 10), $entry['color']) .
                    str_repeat(' ', max(0, $this->terminalWidth - strlen($entry['message']) - 10)) .
                    $this->colorize("│", 'cyan') . "\n";
            }
        }
    }

    protected function drawFooter(): void
    {
        echo $this->colorize("└", 'cyan') .
            str_repeat('─', $this->terminalWidth - 2) .
            $this->colorize("┘", 'cyan') . "\n";

        echo $this->colorize("💡 Commands: help | exit | clear", 'gray') .
            str_repeat(' ', max(0, $this->terminalWidth - 32)) . "\n";
    }

    protected function addToHistory(string $type, string $message, string $color = 'white'): void
    {
        $this->history[] = [
            'type' => $type,
            'message' => $message,
            'color' => $color,
            'timestamp' => time()
        ];

        // Keep history manageable
        if (count($this->history) > $this->maxHistoryLines) {
            array_shift($this->history);
        }
    }

    protected function formatParams(array $params): string
    {
        $formatted = [];
        foreach ($params as $key => $value) {
            if (is_string($value) && strlen($value) > 20) {
                $value = substr($value, 0, 17) . '...';
            }
            $formatted[] = "$key: " . json_encode($value);
        }
        return implode(', ', $formatted);
    }

    protected function summarizeResult($result): string
    {
        if (is_array($result)) {
            $data = $result['data'] ?? $result;
            if (isset($data['files'])) {
                return count($data['files']) . ' files found';
            }
            if (isset($data['stdout'])) {
                return 'exit: ' . ($data['return_code'] ?? '?');
            }
            if (isset($data['bytes_written'])) {
                return $data['bytes_written'] . ' bytes written';
            }
        }
        return 'completed';
    }

    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'bright_green',
            'running', 'executing' => 'bright_yellow',
            'failed' => 'bright_red',
            'pending' => 'gray',
            default => 'white'
        };
    }

    protected function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
    }

    protected function colorize(string $text, string $color, ?string $style = null): string
    {
        $codes = [];
        if ($style) $codes[] = self::COLORS[$style] ?? '';
        $codes[] = self::COLORS[$color] ?? '';

        return implode('', $codes) . $text . self::COLORS['reset'];
    }

    protected function clearScreen(): void
    {
        echo "\033[2J\033[H"; // Clear screen and move cursor to top-left
    }

    protected function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    protected function detectTerminalSize(): void
    {
        // Try to get terminal size
        $output = [];
        exec('stty size 2>/dev/null', $output);

        if (!empty($output[0])) {
            [$height, $width] = explode(' ', $output[0]);
            $this->terminalHeight = (int)$height;
            $this->terminalWidth = (int)$width;
        } else {
            // Fallback defaults
            $this->terminalHeight = 24;
            $this->terminalWidth = 80;
        }
    }

    protected function enableRawMode(): void
    {
        // Enable raw mode for better input handling
        if (function_exists('system')) {
            system('stty -echo -icanon min 1 time 0 2>/dev/null');
        }
    }

    protected function waitForKeypress(): void
    {
        echo $this->colorize("\n  Press any key to continue...", 'gray');
        fgetc(STDIN);
    }

    public function cleanup(): void
    {
        // Restore normal terminal mode
        if (function_exists('system')) {
            system('stty echo icanon 2>/dev/null');
        }
        echo self::COLORS['reset'];
    }

    // Additional utility methods for specific use cases

    public function showSpinner(string $message, callable $task): mixed
    {
        $spinnerChars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $i = 0;

        // Run task in background simulation (for demo purposes)
        echo $this->colorize("  {$spinnerChars[$i]} $message", 'yellow');

        $result = $task();

        echo "\r" . $this->colorize("  ✓ $message", 'green') . "\n";

        return $result;
    }

    public function showProgressBar(int $current, int $total, string $message = ''): void
    {
        $percent = round(($current / $total) * 100);
        $barLength = 30;
        $filledLength = round(($percent / 100) * $barLength);

        $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);

        echo "\r" . $this->colorize("  [$bar] $percent% $message", 'cyan');

        if ($current >= $total) {
            echo "\n";
        }
    }
}

// Example usage:
$tui = new TUIRenderer();
$tui->showWelcome();

// Simulate some activity
$tui->refresh([
    'current_task' => ['description' => 'Creating Laravel migration'],
    'tasks' => [
        ['description' => 'Create migration file', 'status' => 'completed'],
        ['description' => 'Add columns to migration', 'status' => 'running'],
        ['description' => 'Run migration', 'status' => 'pending'],
    ]
]);

$userInput = $tui->prompt("What would you like me to help with?");
$tui->displayResponse(AgentResponse::success("Task completed successfully!"));

