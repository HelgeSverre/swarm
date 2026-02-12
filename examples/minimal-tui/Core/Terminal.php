<?php

declare(strict_types=1);

namespace MinimalTui\Core;

use RuntimeException;

/**
 * Terminal utilities for ANSI escape sequences, input handling, and terminal management
 */
class Terminal
{
    // ANSI Color codes
    public const BLACK = "\033[30m";

    public const RED = "\033[31m";

    public const GREEN = "\033[32m";

    public const YELLOW = "\033[33m";

    public const BLUE = "\033[34m";

    public const MAGENTA = "\033[35m";

    public const CYAN = "\033[36m";

    public const WHITE = "\033[37m";

    public const GRAY = "\033[90m";

    public const BRIGHT_RED = "\033[91m";

    public const BRIGHT_GREEN = "\033[92m";

    public const BRIGHT_YELLOW = "\033[93m";

    public const BRIGHT_BLUE = "\033[94m";

    public const BRIGHT_MAGENTA = "\033[95m";

    public const BRIGHT_CYAN = "\033[96m";

    public const BRIGHT_WHITE = "\033[97m";

    // Background colors
    public const BG_BLACK = "\033[40m";

    public const BG_RED = "\033[41m";

    public const BG_GREEN = "\033[42m";

    public const BG_YELLOW = "\033[43m";

    public const BG_BLUE = "\033[44m";

    public const BG_MAGENTA = "\033[45m";

    public const BG_CYAN = "\033[46m";

    public const BG_WHITE = "\033[47m";

    public const BG_GRAY = "\033[100m";

    public const BG_DARK = "\033[100m";

    // Text formatting
    public const RESET = "\033[0m";

    public const BOLD = "\033[1m";

    public const DIM = "\033[2m";

    public const ITALIC = "\033[3m";

    public const UNDERLINE = "\033[4m";

    public const REVERSE = "\033[7m";

    // Box drawing characters
    public const BOX_H = '─';

    public const BOX_V = '│';

    public const BOX_TL = '┌';

    public const BOX_TR = '┐';

    public const BOX_BL = '└';

    public const BOX_BR = '┘';

    public const BOX_CROSS = '┼';

    public const BOX_T = '┬';

    public const BOX_B = '┴';

    public const BOX_L = '├';

    public const BOX_R = '┤';

    protected static ?self $instance = null;

    protected string $originalTermState = '';

    protected bool $initialized = false;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Initialize terminal for TUI mode
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Check if we're running in a terminal
        if (! $this->isTerminal()) {
            throw new RuntimeException('This application requires a terminal to run.');
        }

        // Save current terminal state
        $this->originalTermState = trim(shell_exec('stty -g 2>/dev/null') ?? '');

        // Enter alternate screen buffer
        echo "\033[?1049h";

        // Clear screen and scrollback
        echo "\033[2J\033[3J\033[H";

        // Set up raw mode for non-blocking input
        if ($this->originalTermState) {
            system('stty -echo -icanon min 1 time 0 2>/dev/null');
        }
        stream_set_blocking(STDIN, false);

        // Hide cursor initially
        echo "\033[?25l";

        // Register cleanup
        register_shutdown_function([$this, 'cleanup']);

        $this->initialized = true;
    }

    /**
     * Cleanup terminal state
     */
    public function cleanup(): void
    {
        if (! $this->initialized) {
            return;
        }

        // Show cursor
        echo "\033[?25h";

        // Reset all attributes
        echo self::RESET;

        // Exit alternate screen buffer
        echo "\033[?1049l";

        // Restore original terminal state
        if (! empty($this->originalTermState)) {
            system("stty {$this->originalTermState} 2>/dev/null");
        } elseif ($this->isTerminal()) {
            system('stty sane 2>/dev/null');
        }

        $this->initialized = false;
    }

    /**
     * Get terminal size
     */
    public function getSize(): array
    {
        $width = (int) exec('tput cols') ?: 80;
        $height = (int) exec('tput lines') ?: 24;

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Move cursor to position
     */
    public function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    /**
     * Clear entire screen
     */
    public function clearScreen(): void
    {
        echo "\033[2J\033[3J\033[H";
    }

    /**
     * Clear current line
     */
    public function clearLine(): void
    {
        echo "\033[2K";
    }

    /**
     * Show cursor
     */
    public function showCursor(): void
    {
        echo "\033[?25h";
    }

    /**
     * Hide cursor
     */
    public function hideCursor(): void
    {
        echo "\033[?25l";
    }

    /**
     * Read a single key with escape sequence handling
     */
    public function readKey(): ?string
    {
        // Check if input is available
        $read = [STDIN];
        $write = null;
        $except = null;
        $result = stream_select($read, $write, $except, 0, 0);

        if ($result === 0 || $result === false) {
            return null;
        }

        $key = fgetc(STDIN);
        if ($key === false || $key === '') {
            return null;
        }

        // Handle escape sequences
        if ($key === "\033") {
            $seq = $key;

            // Read next character with short timeout
            $read2 = [STDIN];
            $result2 = stream_select($read2, $write, $except, 0, 10000); // 10ms

            if ($result2 > 0) {
                $next = fgetc(STDIN);
                if ($next !== false && $next !== '') {
                    $seq .= $next;

                    // Check for Alt+key combinations
                    if ($next !== '[' && $next !== "\033") {
                        return 'ALT+' . mb_strtoupper($next);
                    }
                } else {
                    return 'ESC';
                }
            } else {
                return 'ESC';
            }

            // Handle arrow keys and other sequences
            if (isset($seq[1]) && $seq[1] === '[') {
                $read3 = [STDIN];
                $result3 = stream_select($read3, $write, $except, 0, 10000);

                if ($result3 > 0) {
                    $third = fgetc(STDIN);
                    if ($third !== false && $third !== '') {
                        $seq .= $third;
                    }
                }
            }

            // Arrow keys
            return match ($seq) {
                "\033[A" => 'UP',
                "\033[B" => 'DOWN',
                "\033[C" => 'RIGHT',
                "\033[D" => 'LEFT',
                default => null
            };
        }

        // Tab key
        if ($key === "\t") {
            return 'TAB';
        }

        return $key;
    }

    /**
     * Strip ANSI escape sequences from text
     */
    public static function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    /**
     * Wrap text to specified width
     */
    public static function wrapText(string $text, int $width): array
    {
        if ($width <= 0) {
            return [$text];
        }

        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine . ($currentLine ? ' ' : '') . $word;
            if (mb_strlen($testLine) <= $width) {
                $currentLine = $testLine;
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

    /**
     * Truncate text to specified length with ellipsis
     */
    public static function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }

    /**
     * Pad string to specified width
     */
    public static function pad(string $text, int $width, string $pad = ' ', int $type = STR_PAD_RIGHT): string
    {
        $textLength = mb_strlen(self::stripAnsi($text));
        $padLength = max(0, $width - $textLength);

        return match ($type) {
            STR_PAD_LEFT => str_repeat($pad, $padLength) . $text,
            STR_PAD_BOTH => str_repeat($pad, (int) ($padLength / 2)) . $text . str_repeat($pad, (int) ceil($padLength / 2)),
            default => $text . str_repeat($pad, $padLength)
        };
    }

    /**
     * Check if running in a terminal
     */
    protected function isTerminal(): bool
    {
        return stream_isatty(STDIN) && stream_isatty(STDOUT);
    }
}
