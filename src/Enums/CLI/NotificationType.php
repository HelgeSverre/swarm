<?php

namespace HelgeSverre\Swarm\Enums\CLI;

/**
 * Types of notifications shown in the TUI
 */
enum NotificationType: string
{
    /**
     * Get the theme color for this notification type
     */
    public function getThemeColor(): ThemeColor
    {
        return match ($this) {
            self::Error => ThemeColor::Error,
            self::Success => ThemeColor::Success,
            self::Warning => ThemeColor::Warning,
            self::Info => ThemeColor::Info,
        };
    }

    /**
     * Get the icon for this notification type
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::Error => '❌',
            self::Success => '✅',
            self::Warning => '⚠️',
            self::Info => 'ℹ️',
        };
    }

    /**
     * Create from string with default fallback
     */
    public static function fromString(string $type): self
    {
        return self::tryFrom($type) ?? self::Info;
    }
    case Error = 'error';
    case Success = 'success';
    case Warning = 'warning';
    case Info = 'info';
}
