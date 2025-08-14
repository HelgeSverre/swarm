<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

/**
 * ANSI Terminal Formatting - Static helper class for terminal output formatting
 * Provides ANSI color codes, box drawing characters, and formatting utilities
 */
class Ansi
{
    // ANSI color codes
    public const string RESET = "\033[0m";

    public const string BOLD = "\033[1m";

    public const string DIM = "\033[2m";

    public const string ITALIC = "\033[3m";

    public const string UNDERLINE = "\033[4m";

    public const string BLINK = "\033[5m";

    public const string REVERSE = "\033[7m";

    public const string STRIKETHROUGH = "\033[9m";

    // Cursor control
    public const string SAVE_CURSOR = "\033[s";

    public const string RESTORE_CURSOR = "\033[u";

    public const string CLEAR_LINE = "\033[2K";

    public const string CLEAR_TO_EOL = "\033[K";

    public const string CLEAR_TO_BOL = "\033[1K";

    // Terminal control
    public const string BELL = "\007";

    public const string ALT_SCREEN_ENABLE = "\033[?1049h";

    public const string ALT_SCREEN_DISABLE = "\033[?1049l";

    // Mouse tracking
    public const string MOUSE_ENABLE = "\033[?1000h";

    public const string MOUSE_DISABLE = "\033[?1000l";

    public const string MOUSE_ENABLE_ALL = "\033[?1003h";

    public const string MOUSE_DISABLE_ALL = "\033[?1003l";

    // Foreground colors
    public const string BLACK = "\033[30m";

    public const string RED = "\033[31m";

    public const string GREEN = "\033[32m";

    public const string YELLOW = "\033[33m";

    public const string BLUE = "\033[34m";

    public const string MAGENTA = "\033[35m";

    public const string CYAN = "\033[36m";

    public const string WHITE = "\033[37m";

    public const string GRAY = "\033[90m";

    public const string BRIGHT_GREEN = "\033[92m";

    public const string BRIGHT_CYAN = "\033[96m";

    // Background colors
    public const string BG_DARK = "\033[48;5;236m";

    public const string BG_BLACK = "\033[40m";

    public const string BG_GRAY = "\033[100m";

    // Box drawing characters - Single line
    public const string BOX_H = '─';

    public const string BOX_V = '│';

    public const string BOX_TL = '┌';

    public const string BOX_TR = '┐';

    public const string BOX_BL = '└';

    public const string BOX_BR = '┘';

    public const string BOX_T = '┬';

    public const string BOX_B = '┴';

    public const string BOX_L = '├';

    public const string BOX_R = '┤';

    public const string BOX_CROSS = '┼';

    // Box drawing - Double line
    public const string BOX_H2 = '═';

    public const string BOX_V2 = '║';

    public const string BOX_TL2 = '╔';

    public const string BOX_TR2 = '╗';

    public const string BOX_BL2 = '╚';

    public const string BOX_BR2 = '╝';

    // Box drawing - Rounded corners
    public const string BOX_TL_ROUND = '╭';

    public const string BOX_TR_ROUND = '╮';

    public const string BOX_BL_ROUND = '╰';

    public const string BOX_BR_ROUND = '╯';

    // Tree view characters
    public const string TREE_BRANCH = '├──';

    public const string TREE_LAST = '└──';

    public const string TREE_PIPE = '│  ';

    public const string TREE_SPACE = '   ';

    // UI elements
    public const string BULLET = '•';

    public const string ARROW = '→';

    public const string CHECK = '✓';

    public const string CROSS = '✗';

    public const string PLAY = '▶';

    public const string CIRCLE = '○';

    public const string ELLIPSIS = '…';

    // Progress indicators
    public const array BLOCKS = ['▏', '▎', '▍', '▌', '▋', '▊', '▉', '█'];

    public const array SPINNER_DOTS = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    public const array SPINNER_CIRCLE = ['◐', '◓', '◑', '◒'];

    public const array SPINNER_ARROW = ['←', '↖', '↑', '↗', '→', '↘', '↓', '↙'];

    public const string PROGRESS_EMPTY = '░';

    public const string PROGRESS_FULL = '█';

    // Status icons
    public const array ICONS = [
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

    /**
     * Strip ANSI codes from text (for length calculations)
     */
    public static function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    /**
     * Get terminal width
     */
    public static function getTerminalWidth(): int
    {
        $width = (int) exec('tput cols');

        return $width > 0 ? $width : 80;
    }

    /**
     * Clear screen
     */
    public static function clearScreen(): void
    {
        echo "\033[2J\033[H";
    }

    /**
     * Create a single-line info bar with background color
     */
    public static function infoBar(
        string $leftContent,
        string $rightContent = '',
        string $bgColor = self::BG_DARK,
        bool $fullWidth = true
    ): string {
        $terminalWidth = self::getTerminalWidth();
        $leftLen = mb_strlen(self::stripAnsi($leftContent));
        $rightLen = mb_strlen(self::stripAnsi($rightContent));
        $width = $fullWidth ? $terminalWidth : $terminalWidth - 2;

        $padding = max(0, $width - $leftLen - $rightLen);

        return $bgColor . $leftContent . str_repeat(' ', $padding) . $rightContent . self::RESET . "\n";
    }

    /**
     * Create a status line with icon and optional metadata
     */
    public static function statusLine(
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
     * Create a progress bar (auto-detects smooth bar capability)
     */
    public static function progressBar(
        int $current,
        int $total,
        int $width = 30,
        bool $showPercentage = true
    ): string {
        // Use smooth progress bar if Unicode is supported
        if (self::supportsUnicode()) {
            return self::smoothProgressBar($current, $total, $width, $showPercentage);
        }

        // Fallback to simple progress bar
        $percentage = $total > 0 ? round(($current / $total) * 100) : 0;
        $filled = (int) (($percentage / 100) * $width);

        $bar = '[' .
            str_repeat('=', $filled) .
            str_repeat('-', $width - $filled) .
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
    public static function activityLine(
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
    public static function sectionHeader(string $title, bool $underline = true): string
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
    public static function divider(int $width = 0, string $style = self::BOX_H): string
    {
        $width = $width ?: self::getTerminalWidth();

        return self::DIM . str_repeat($style, $width) . self::RESET . "\n";
    }

    /**
     * Create an indented item (for lists, hierarchies)
     */
    public static function indentedItem(
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
    public static function taskLine(
        int $number,
        string $description,
        string $status = 'pending',
        ?int $progress = null
    ): string {
        $icon = match ($status) {
            'completed' => self::GREEN . self::CHECK,
            'running' => self::YELLOW . self::PLAY,
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
    public static function infoBlock(string $label, string $value, string $labelColor = self::CYAN): string
    {
        return $labelColor . $label . ':' . self::RESET . "\n  " . $value . "\n";
    }

    /**
     * Create a thought/expandable content block
     */
    public static function thoughtBlock(string $content, bool $expanded = false, int $maxLines = 3): string
    {
        $lines = explode("\n", wordwrap($content, self::getTerminalWidth() - 15, "\n", true));
        $result = '';

        if (! $expanded && count($lines) > $maxLines) {
            // Show collapsed version
            for ($i = 0; $i < $maxLines; $i++) {
                $result .= '  ' . self::DIM . self::ITALIC . $lines[$i] . self::RESET . "\n";
            }
            $remaining = count($lines) - $maxLines;
            $result .= '  ' . self::DIM . self::ELLIPSIS . " +{$remaining} more lines (⌥R to expand)" . self::RESET . "\n";
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
    public static function prompt(string $label = '>', string $input = '', bool $active = true): string
    {
        if ($active) {
            return self::BLUE . $label . self::RESET . ' ' . $input;
        }

        return self::DIM . $label . ' ' . $input . self::RESET;
    }

    /**
     * Truncate text with ellipsis
     */
    public static function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 1) . self::ELLIPSIS;
    }

    /**
     * Move cursor to position
     */
    public static function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    /**
     * Hide cursor
     */
    public static function hideCursor(): void
    {
        echo "\033[?25l";
    }

    /**
     * Show cursor
     */
    public static function showCursor(): void
    {
        echo "\033[?25h";
    }

    /**
     * Word wrap text to a specific width
     */
    public static function wordWrap(string $text, int $width): string
    {
        return wordwrap($text, $width, "\n", true);
    }

    /**
     * Colorize text with ANSI escape codes
     */
    public static function colorize(string $text, string $style): string
    {
        $styles = [
            'default' => self::RESET,
            'dim' => self::DIM,
            'bold' => self::BOLD,
            'success' => self::GREEN,
            'warning' => self::YELLOW,
            'info' => self::CYAN,
            'accent' => self::MAGENTA,
            'error' => self::RED,
        ];

        $code = $styles[$style] ?? $styles['default'];

        return $code . $text . self::RESET;
    }

    // ========== NEW TERMINAL ENHANCEMENTS ==========

    /**
     * Clear entire line
     */
    public static function clearLine(): void
    {
        echo self::CLEAR_LINE;
    }

    /**
     * Clear to end of line
     */
    public static function clearToEndOfLine(): void
    {
        echo self::CLEAR_TO_EOL;
    }

    /**
     * Save cursor position
     */
    public static function saveCursor(): void
    {
        echo self::SAVE_CURSOR;
    }

    /**
     * Restore cursor position
     */
    public static function restoreCursor(): void
    {
        echo self::RESTORE_CURSOR;
    }

    /**
     * Terminal bell/alert
     */
    public static function bell(): void
    {
        echo self::BELL;
    }

    /**
     * Set terminal window title
     */
    public static function setTitle(string $title): void
    {
        echo "\033]0;{$title}\007";
    }

    /**
     * Enter alternative screen buffer (full-screen mode)
     */
    public static function enterAltScreen(): void
    {
        echo self::ALT_SCREEN_ENABLE;
    }

    /**
     * Exit alternative screen buffer
     */
    public static function exitAltScreen(): void
    {
        echo self::ALT_SCREEN_DISABLE;
    }

    /**
     * Enable mouse tracking (click events)
     */
    public static function enableMouse(): void
    {
        echo self::MOUSE_ENABLE;
    }

    /**
     * Disable mouse tracking
     */
    public static function disableMouse(): void
    {
        echo self::MOUSE_DISABLE;
    }

    /**
     * Enable all mouse events (movement + clicks)
     */
    public static function enableAllMouseEvents(): void
    {
        echo self::MOUSE_ENABLE_ALL;
    }

    /**
     * Disable all mouse events
     */
    public static function disableAllMouseEvents(): void
    {
        echo self::MOUSE_DISABLE_ALL;
    }

    /**
     * Terminal reset (full reset)
     */
    public static function reset(): void
    {
        echo "\033c";
    }

    /**
     * Soft terminal reset
     */
    public static function softReset(): void
    {
        echo "\033[!p";
    }

    /**
     * Check if terminal supports 256 colors
     */
    public static function supports256Colors(): bool
    {
        $term = getenv('TERM') ?: '';

        return str_contains($term, '256color') || str_contains($term, 'truecolor');
    }

    /**
     * Check if terminal supports Unicode
     */
    public static function supportsUnicode(): bool
    {
        $lang = getenv('LANG') ?: '';

        return str_contains($lang, 'UTF-8') || str_contains($lang, 'UTF8');
    }

    /**
     * Check if terminal supports true color (RGB)
     */
    public static function supportsTrueColor(): bool
    {
        $term = getenv('COLORTERM');

        return $term === 'truecolor' || $term === '24bit';
    }

    /**
     * Create 256-color foreground
     */
    public static function color256(int $color): string
    {
        return "\033[38;5;{$color}m";
    }

    /**
     * Create 256-color background
     */
    public static function bgColor256(int $color): string
    {
        return "\033[48;5;{$color}m";
    }

    /**
     * Create RGB foreground color
     */
    public static function rgb(int $r, int $g, int $b): string
    {
        return "\033[38;2;{$r};{$g};{$b}m";
    }

    /**
     * Create RGB background color
     */
    public static function bgRgb(int $r, int $g, int $b): string
    {
        return "\033[48;2;{$r};{$g};{$b}m";
    }

    /**
     * Create clickable hyperlink (OSC 8)
     */
    public static function hyperlink(string $url, string $text): string
    {
        return "\033]8;;{$url}\033\\{$text}\033]8;;\033\\";
    }

    /**
     * Create a status badge
     */
    public static function badge(string $text, string $type = 'info'): string
    {
        $colors = [
            'success' => [self::WHITE, self::GREEN],
            'error' => [self::WHITE, self::RED],
            'warning' => [self::BLACK, self::YELLOW],
            'info' => [self::WHITE, self::BLUE],
            'pending' => [self::BLACK, self::GRAY],
        ];

        [$fg, $bg] = $colors[$type] ?? $colors['info'];

        return $bg . $fg . " {$text} " . self::RESET;
    }

    /**
     * Animated spinner character
     */
    public static function spinner(int $frame = 0, string $type = 'dots'): string
    {
        $spinners = [
            'dots' => self::SPINNER_DOTS,
            'circle' => self::SPINNER_CIRCLE,
            'arrow' => self::SPINNER_ARROW,
        ];

        $spinner = $spinners[$type] ?? $spinners['dots'];

        return $spinner[$frame % count($spinner)];
    }

    /**
     * Enhanced progress bar with smooth blocks
     */
    public static function smoothProgressBar(
        int $current,
        int $total,
        int $width = 30,
        bool $showPercentage = true
    ): string {
        $percentage = $total > 0 ? ($current / $total) : 0;
        $filled = $percentage * $width;
        $fullBlocks = (int) $filled;
        $partialBlock = $filled - $fullBlocks;

        $bar = str_repeat(self::PROGRESS_FULL, $fullBlocks);

        // Add partial block if there's remainder
        if ($partialBlock > 0 && $fullBlocks < $width) {
            $blockIndex = min((int) ($partialBlock * count(self::BLOCKS)), count(self::BLOCKS) - 1);
            $bar .= self::BLOCKS[$blockIndex];
            $fullBlocks++;
        }

        $bar .= str_repeat(self::PROGRESS_EMPTY, max(0, $width - $fullBlocks));

        $result = '[' . self::GREEN . $bar . self::RESET . ']';

        if ($showPercentage) {
            $percent = round($percentage * 100);
            $result .= ' ' . self::DIM . $percent . '%' . self::RESET;
        }

        return $result;
    }

    /**
     * Format relative time (e.g., "2m ago")
     */
    public static function relativeTime(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return $diff . 's ago';
        } elseif ($diff < 3600) {
            return (int) ($diff / 60) . 'm ago';
        } elseif ($diff < 86400) {
            return (int) ($diff / 3600) . 'h ago';
        }

        return (int) ($diff / 86400) . 'd ago';
    }

    /**
     * Enhanced truncate with proper ellipsis
     */
    public static function truncateNice(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 1) . self::ELLIPSIS;
    }

    /**
     * Create a box with optional title and rounded corners
     */
    public static function box(
        string $content,
        ?string $title = null,
        int $width = 0,
        bool $rounded = false
    ): string {
        $width = $width ?: self::getTerminalWidth() - 4;
        $lines = explode("\n", $content);
        $maxContentWidth = $width - 4;

        // Box characters
        $tl = $rounded ? self::BOX_TL_ROUND : self::BOX_TL;
        $tr = $rounded ? self::BOX_TR_ROUND : self::BOX_TR;
        $bl = $rounded ? self::BOX_BL_ROUND : self::BOX_BL;
        $br = $rounded ? self::BOX_BR_ROUND : self::BOX_BR;

        $result = '';

        // Top border
        $result .= self::GRAY . $tl;
        if ($title) {
            $titleLen = mb_strlen($title);
            $padding = max(0, ($width - $titleLen - 4) / 2);
            $result .= str_repeat(self::BOX_H, (int) $padding);
            $result .= ' ' . self::WHITE . self::BOLD . $title . self::RESET . self::GRAY . ' ';
            $result .= str_repeat(self::BOX_H, $width - (int) $padding - $titleLen - 4);
        } else {
            $result .= str_repeat(self::BOX_H, $width - 2);
        }
        $result .= $tr . self::RESET . "\n";

        // Content lines
        foreach ($lines as $line) {
            $lineLength = mb_strlen(self::stripAnsi($line));
            $padding = str_repeat(' ', max(0, $maxContentWidth - $lineLength));
            $result .= self::GRAY . self::BOX_V . self::RESET . ' ' . $line . $padding . ' ' . self::GRAY . self::BOX_V . self::RESET . "\n";
        }

        // Bottom border
        $result .= self::GRAY . $bl . str_repeat(self::BOX_H, $width - 2) . $br . self::RESET . "\n";

        return $result;
    }

    /**
     * Tree view formatter
     */
    public static function treeItem(string $content, int $depth = 0, bool $isLast = false): string
    {
        $prefix = str_repeat(self::TREE_SPACE, $depth);
        if ($depth > 0) {
            $prefix = str_repeat(self::TREE_SPACE, $depth - 1);
            $prefix .= $isLast ? self::TREE_LAST : self::TREE_BRANCH;
        }

        return self::DIM . $prefix . self::RESET . $content . "\n";
    }

    /**
     * Semantic color methods
     */
    public static function success(string $text): string
    {
        return self::GREEN . $text . self::RESET;
    }

    public static function error(string $text): string
    {
        return self::RED . $text . self::RESET;
    }

    public static function warning(string $text): string
    {
        return self::YELLOW . $text . self::RESET;
    }

    public static function info(string $text): string
    {
        return self::CYAN . $text . self::RESET;
    }

    public static function muted(string $text): string
    {
        return self::DIM . $text . self::RESET;
    }

    /**
     * Make file paths clickable with hyperlinks
     */
    public static function clickableFile(string $path, ?string $displayName = null): string
    {
        $displayName = $displayName ?: basename($path);
        $fullPath = realpath($path) ?: $path;

        return self::hyperlink("file://{$fullPath}", $displayName);
    }

    /**
     * Enhanced activity line with relative time option
     */
    public static function activityLineRelative(
        string $type,
        string $content,
        ?int $timestamp = null,
        string $detail = ''
    ): string {
        $timestamp = $timestamp ?: time();
        $timeDisplay = self::relativeTime($timestamp);
        $prefix = self::DIM . "[{$timeDisplay}]" . self::RESET . ' ';

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
            $line .= "\n" . str_repeat(' ', mb_strlen(self::stripAnsi("[{$timeDisplay}] ")) + 1) . self::DIM . $detail . self::RESET;
        }

        return $line . "\n";
    }

    /**
     * Create an enhanced box with better spacing and options
     */
    public static function fancyBox(
        string $content,
        ?string $title = null,
        string $style = 'single', // single, double, rounded
        int $width = 0,
        string $color = self::GRAY
    ): string {
        $width = $width ?: self::getTerminalWidth() - 4;
        $lines = explode("\n", $content);
        $maxContentWidth = $width - 4;

        // Box character selection
        [$tl, $tr, $bl, $br, $h, $v] = match ($style) {
            'double' => [self::BOX_TL2, self::BOX_TR2, self::BOX_BL2, self::BOX_BR2, self::BOX_H2, self::BOX_V2],
            'rounded' => [self::BOX_TL_ROUND, self::BOX_TR_ROUND, self::BOX_BL_ROUND, self::BOX_BR_ROUND, self::BOX_H, self::BOX_V],
            default => [self::BOX_TL, self::BOX_TR, self::BOX_BL, self::BOX_BR, self::BOX_H, self::BOX_V],
        };

        $result = '';

        // Top border with title
        $result .= $color . $tl;
        if ($title) {
            $titleLen = mb_strlen($title);
            $padding = max(0, ($width - $titleLen - 4) / 2);
            $result .= str_repeat($h, (int) $padding);
            $result .= ' ' . self::WHITE . self::BOLD . $title . self::RESET . $color . ' ';
            $result .= str_repeat($h, $width - (int) $padding - $titleLen - 4);
        } else {
            $result .= str_repeat($h, $width - 2);
        }
        $result .= $tr . self::RESET . "\n";

        // Content lines
        foreach ($lines as $line) {
            $lineLength = mb_strlen(self::stripAnsi($line));
            $padding = str_repeat(' ', max(0, $maxContentWidth - $lineLength));
            $result .= $color . $v . self::RESET . ' ' . $line . $padding . ' ' . $color . $v . self::RESET . "\n";
        }

        // Bottom border
        $result .= $color . $bl . str_repeat($h, $width - 2) . $br . self::RESET . "\n";

        return $result;
    }
}
