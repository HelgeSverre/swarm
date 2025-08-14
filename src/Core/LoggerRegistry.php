<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Global logger registry for static access to logger
 * Avoids passing LoggerInterface everywhere while maintaining testability
 */
class LoggerRegistry
{
    /**
     * The registered logger instance
     */
    private static ?LoggerInterface $logger = null;

    /**
     * Set the global logger instance
     */
    public static function setLogger(?LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Get the global logger instance
     * Returns NullLogger if no logger is set
     */
    public static function get(): LoggerInterface
    {
        return self::$logger ?? new NullLogger;
    }

    /**
     * Check if a logger is registered
     */
    public static function hasLogger(): bool
    {
        return self::$logger !== null;
    }

    /**
     * Clear the registered logger
     */
    public static function clear(): void
    {
        self::$logger = null;
    }
}
