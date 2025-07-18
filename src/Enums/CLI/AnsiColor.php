<?php

namespace HelgeSverre\Swarm\Enums\CLI;

/**
 * ANSI color codes for terminal output
 */
enum AnsiColor: string
{
    case Reset = 'reset';
    case Bold = 'bold';
    case Dim = 'dim';
    case Red = 'red';
    case Green = 'green';
    case Yellow = 'yellow';
    case Blue = 'blue';
    case Magenta = 'magenta';
    case Cyan = 'cyan';
    case White = 'white';
    case Gray = 'gray';
    case DarkGray = 'dark_gray';
    case LightGray = 'light_gray';
    case BrightRed = 'bright_red';
    case BrightGreen = 'bright_green';
    case BrightYellow = 'bright_yellow';
    case BrightBlue = 'bright_blue';
    case BrightMagenta = 'bright_magenta';
    case BrightCyan = 'bright_cyan';
    case BrightWhite = 'bright_white';

    /**
     * Get the ANSI escape code for this color
     */
    public function toEscapeCode(): string
    {
        return match ($this) {
            self::Reset => "\033[0m",
            self::Bold => "\033[1m",
            self::Dim => "\033[2m",
            self::Red => "\033[31m",
            self::Green => "\033[32m",
            self::Yellow => "\033[33m",
            self::Blue => "\033[34m",
            self::Magenta => "\033[35m",
            self::Cyan => "\033[36m",
            self::White => "\033[37m",
            self::Gray => "\033[90m",
            self::DarkGray => "\033[90m",
            self::LightGray => "\033[37m",
            self::BrightRed => "\033[91m",
            self::BrightGreen => "\033[92m",
            self::BrightYellow => "\033[93m",
            self::BrightBlue => "\033[94m",
            self::BrightMagenta => "\033[95m",
            self::BrightCyan => "\033[96m",
            self::BrightWhite => "\033[97m",
        };
    }
}