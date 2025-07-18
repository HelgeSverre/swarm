<?php

namespace HelgeSverre\Swarm\Enums\CLI;

/**
 * Theme colors used throughout the TUI
 */
enum ThemeColor: string
{
    case Border = 'border';
    case Header = 'header';
    case Accent = 'accent';
    case Success = 'success';
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
    case Muted = 'muted';

    /**
     * Get the ANSI color for this theme color
     */
    public function getAnsiColor(): AnsiColor
    {
        return match ($this) {
            self::Border => AnsiColor::DarkGray,
            self::Header => AnsiColor::BrightWhite,
            self::Accent => AnsiColor::BrightBlue,
            self::Success => AnsiColor::BrightGreen,
            self::Error => AnsiColor::BrightRed,
            self::Warning => AnsiColor::BrightYellow,
            self::Info => AnsiColor::BrightCyan,
            self::Muted => AnsiColor::Gray,
        };
    }

    /**
     * Get the ANSI escape code directly
     */
    public function toEscapeCode(): string
    {
        return $this->getAnsiColor()->toEscapeCode();
    }

}
