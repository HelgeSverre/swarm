<?php

namespace HelgeSverre\Swarm\Enums\Core;

use Monolog\Logger;

/**
 * Logging levels
 */
enum LogLevel: string
{
    /**
     * Convert to Monolog log level constant
     */
    public function toMonologLevel(): int
    {
        return match ($this) {
            self::Debug => Logger::DEBUG,
            self::Info => Logger::INFO,
            self::Notice => Logger::NOTICE,
            self::Warning => Logger::WARNING,
            self::Error => Logger::ERROR,
            self::Critical => Logger::CRITICAL,
            self::Alert => Logger::ALERT,
            self::Emergency => Logger::EMERGENCY,
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
