<?php

namespace HelgeSverre\Swarm\Enums\CLI;

/**
 * Types of activities displayed in the TUI history
 */
enum ActivityType: string
{
    case Agent = 'agent';
    case User = 'user';
    case Tool = 'tool';
    case Error = 'error';
    case Notification = 'notification';

    /**
     * Get the display color for this activity type
     */
    public function getColor(): AnsiColor
    {
        return match ($this) {
            self::Agent => AnsiColor::White,
            self::User => AnsiColor::BrightWhite,
            self::Tool => AnsiColor::Cyan,
            self::Error => AnsiColor::BrightRed,
            self::Notification => AnsiColor::BrightCyan,
        };
    }

    /**
     * Get the icon for this activity type
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::Agent => '🤖',
            self::User => '💬',
            self::Tool => '🔧',
            self::Error => '❌',
            self::Notification => '', // Notifications have their own icons
        };
    }

    /**
     * Check if this activity type should show an icon
     */
    public function hasIcon(): bool
    {
        return $this !== self::Notification;
    }

    /**
     * Try to create from string value, with default fallback
     */
    public static function tryFromWithDefault(string $value): self
    {
        return self::tryFrom($value) ?? self::Agent;
    }
}