<?php

namespace HelgeSverre\Swarm\Enums\Core;

use Monolog\Level;

/**
 * Logging levels
 */
enum LogLevel: string
{
    /**
     * Convert to Monolog Level enum
     */
    public function toMonologLevel(): Level
    {
        return match ($this) {
            self::Debug => Level::Debug,
            self::Info => Level::Info,
            self::Notice => Level::Notice,
            self::Warning => Level::Warning,
            self::Error => Level::Error,
            self::Critical => Level::Critical,
            self::Alert => Level::Alert,
            self::Emergency => Level::Emergency,
        };
    }

    /**
     * Create from string with fallback to INFO
     */
    public static function fromString(string $value): self
    {
        $normalized = mb_strtolower($value);

        // Handle common aliases
        if ($normalized === 'warn') {
            $normalized = 'warning';
        }

        return self::tryFrom($normalized) ?? self::Info;
    }

    /**
     * Get all values as strings
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    case Debug = 'debug';
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
    case Alert = 'alert';
    case Emergency = 'emergency';
}
