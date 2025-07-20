<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

/**
 * Terminal UI Toolkit - Clean, efficient terminal UI components
 * Inspired by the swarm mockup aesthetic
 */
class Termz
{
    // ANSI color codes
    const string RESET = "\033[0m";

    const string BOLD = "\033[1m";

    const string DIM = "\033[2m";

    const string ITALIC = "\033[3m";

    const string UNDERLINE = "\033[4m";

    const string REVERSE = "\033[7m";

    // Foreground colors
    const string BLACK = "\033[30m";

    const string RED = "\033[31m";

    const string GREEN = "\033[32m";

    const string YELLOW = "\033[33m";

    const string BLUE = "\033[34m";

    const string MAGENTA = "\033[35m";

    const string CYAN = "\033[36m";

    const string WHITE = "\033[37m";

    const string GRAY = "\033[90m";

    const string BRIGHT_GREEN = "\033[92m";

    const string BRIGHT_CYAN = "\033[96m";

    // Background colors
    const string BG_DARK = "\033[48;5;236m";

    const string BG_BLACK = "\033[40m";

    const string BG_GRAY = "\033[100m";

    // Box drawing characters
    const string BOX_H = '─';

    const string BOX_V = '│';

    const string BULLET = '•';

    const string ARROW = '→';

    const string CHECK = '✓';

    const string PLAY = '▶';

    const string CIRCLE = '○';

    // Status icons
    const array ICONS = [
        'success' => '✓',
        'running' => '▶',
        'pending' => '○',
        'error' => '✗',
        'info' => '●',
        'tool' => '>',
        'command' => '$',
        'system' => '!',
        'thinking' => '💭',
        'task' => '📋',
        'search' => '🔍',
        'build' => '🔧',
        'write' => '✏️',
    ];

    protected int $terminalWidth;

    public function __construct()
    {
        $this->updateTerminalWidth();
    }

    /**
     * Update terminal width
     */
    public function updateTerminalWidth(): void
    {
        $this->terminalWidth = (int) exec('tput cols') ?: 80;
    }

    /**
     * Clear screen
     */
    public function clearScreen(): void
    {
        echo "\033[2J\033[H";
    }

    /**
     * Create a single-line info bar with background color
     */
    public function infoBar(
        string $leftContent,
        string $rightContent = '',
        string $bgColor = self::BG_DARK,
        bool $fullWidth = true
    ): string {
        $leftLen = mb_strlen($this->stripAnsi($leftContent));
        $rightLen = mb_strlen($this->stripAnsi($rightContent));
        $width = $fullWidth ? $this->terminalWidth : $this->terminalWidth - 2;

        $padding = max(0, $width - $leftLen - $rightLen);

        return $bgColor . $leftContent . str_repeat(' ', $padding) . $rightContent . self::RESET . "\n";
    }

    /**
     * Create a status line with icon and optional metadata
     */
    public function statusLine(
        string $icon,
        string $label,
        string $value = '',
        string $extra = '',
        string $iconColor = self::GREEN,
        string $labelColor = self::WHITE,
        string $valueColor = self::YELLOW
    ): string {
        $parts = [];
        $parts[] = $iconColor . $icon . self::RESET;
        $parts[] = $labelColor . $label . self::RESET;

        if ($value) {
            $parts[] = self::DIM . self::BOX_V . self::RESET;
            $parts[] = $valueColor . $value . self::RESET;
        }

        if ($extra) {
            $parts[] = self::DIM . $extra . self::RESET;
        }

        return ' ' . implode(' ', $parts) . "\n";
    }

    /**
     * Create a progress bar
     */
    public function progressBar(
        int $current,
        int $total,
        int $width = 30,
        bool $showPercentage = true
    ): string {
        $percentage = $total > 0 ? round(($current / $total) * 100) : 0;
        $filled = (int) (($percentage / 100) * $width);

        $bar = '[' .
            str_repeat('█', $filled) .
            str_repeat('░', $width - $filled) .
            ']';

        $result = self::GREEN . $bar . self::RESET;

        if ($showPercentage) {
            $result .= ' ' . self::DIM . $percentage . '%' . self::RESET;
        }

        return $result;
    }

    /**
     * Create a timestamped activity line
     */
    public function activityLine(
        string $type,
        string $content,
        ?int $timestamp = null,
        string $detail = ''
    ): string {
        $time = date('H:i:s', $timestamp ?: time());
        $prefix = self::DIM . "[{$time}]" . self::RESET . ' ';

        $icon = self::ICONS[$type] ?? '•';
        $color = match ($type) {
            'success', 'running' => self::GREEN,
            'error' => self::RED,
            'command' => self::BLUE,
            'tool' => self::CYAN,
            'system' => self::YELLOW,
            default => self::WHITE
        };

        $line = $prefix . $color . $icon . self::RESET . ' ' . $content;

        if ($detail) {
            $line .= "\n" . str_repeat(' ', 13) . self::DIM . $detail . self::RESET;
        }

        return $line . "\n";
    }

    /**
     * Create a section header with underline
     */
    public function sectionHeader(string $title, bool $underline = true): string
    {
        $result = self::BOLD;
        if ($underline) {
            $result .= self::UNDERLINE;
        }
        $result .= $title . self::RESET . "\n";

        return $result;
    }

    /**
     * Create a horizontal divider
     */
    public function divider(int $width = 0, string $style = self::BOX_H): string
    {
        $width = $width ?: $this->terminalWidth;

        return self::DIM . str_repeat($style, $width) . self::RESET . "\n";
    }

    /**
     * Create an indented item (for lists, hierarchies)
     */
    public function indentedItem(
        string $content,
        int $level = 1,
        string $bullet = self::BULLET,
        string $bulletColor = self::DIM
    ): string {
        $indent = str_repeat('  ', $level);

        return $indent . $bulletColor . $bullet . self::RESET . ' ' . $content . "\n";
    }

    /**
     * Create a task line with status
     */
    public function taskLine(
        int $number,
        string $description,
        string $status = 'pending',
        ?int $progress = null
    ): string {
        $icon = match ($status) {
            'completed' => self::GREEN . self::CHECK,
            'running' => self::YELLOW . self::PLAY,
            'pending' => self::DIM . self::CIRCLE,
            'error' => self::RED . '✗',
            default => self::DIM . self::CIRCLE
        };

        $num = mb_str_pad($number . '.', 3);
        $line = $num . ' ' . $icon . self::RESET . ' ' . $description;

        if ($progress !== null) {
            $line .= ' ' . self::DIM . $progress . '%' . self::RESET;
        }

        return $line . "\n";
    }

    /**
     * Create a compact info block with label and value
     */
    public function infoBlock(string $label, string $value, string $labelColor = self::CYAN): string
    {
        return $labelColor . $label . ':' . self::RESET . "\n  " . $value . "\n";
    }

    /**
     * Create a thought/expandable content block
     */
    public function thoughtBlock(string $content, bool $expanded = false, int $maxLines = 3): string
    {
        $lines = explode("\n", wordwrap($content, $this->terminalWidth - 15, "\n", true));
        $result = '';

        if (! $expanded && count($lines) > $maxLines) {
            // Show collapsed version
            for ($i = 0; $i < $maxLines; $i++) {
                $result .= '  ' . self::DIM . self::ITALIC . $lines[$i] . self::RESET . "\n";
            }
            $remaining = count($lines) - $maxLines;
            $result .= '  ' . self::DIM . "... +{$remaining} more lines (⌥R to expand)" . self::RESET . "\n";
        } else {
            // Show all lines
            foreach ($lines as $line) {
                $result .= '  ' . self::DIM . self::ITALIC . $line . self::RESET . "\n";
            }
            if (count($lines) > $maxLines) {
                $result .= '  ' . self::DIM . '(⌥R to collapse)' . self::RESET . "\n";
            }
        }

        return $result;
    }

    /**
     * Create a prompt line
     */
    public function prompt(string $label = '>', string $input = '', bool $active = true): string
    {
        if ($active) {
            return self::BLUE . $label . self::RESET . ' ' . $input;
        }

        return self::DIM . $label . ' ' . $input . self::RESET;
    }

    /**
     * Truncate text with ellipsis
     */
    public function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }

    /**
     * Move cursor to position
     */
    public function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    /**
     * Hide cursor
     */
    public function hideCursor(): void
    {
        echo "\033[?25l";
    }

    /**
     * Show cursor
     */
    public function showCursor(): void
    {
        echo "\033[?25h";
    }

    /**
     * Strip ANSI codes from text (for length calculations)
     */
    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }
}

$ui = new Termz;

// Clear and setup
$ui->clearScreen();

// Info bar at top
echo $ui->infoBar(
    ' 💮 swarm ' . Termz::BOX_V . ' ' . Termz::GREEN . '● Task running',
    'working (2/4)',
    Termz::BG_DARK
);

echo "\n";

// Section with activities
echo $ui->sectionHeader('Recent activity:');
echo $ui->activityLine('command', 'Create email validator function');
echo $ui->activityLine('tool', 'read_file src/validators.php', null, 'Reading current implementation');
echo $ui->activityLine('success', 'Email validation function created');

echo "\n";

// Task list
echo $ui->sectionHeader('Tasks');
echo $ui->taskLine(1, 'Setup project structure', 'completed');
echo $ui->taskLine(2, 'Create email validator function', 'running', 50);
echo $ui->taskLine(3, 'Write unit tests', 'pending');

echo "\n";

// Progress example
echo 'Progress: ' . $ui->progressBar(2, 4) . "\n";

echo "\n";

// Divider
echo $ui->divider();

// Prompt
echo $ui->prompt('swarm >', 'test command');
