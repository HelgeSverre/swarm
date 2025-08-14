<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Traits;

use HelgeSverre\Swarm\Core\LoggerRegistry;
use Psr\Log\LoggerInterface;

/**
 * Trait for components that need logging
 * Provides easy access to logger without requiring injection
 */
trait Loggable
{
    /**
     * Get the global logger instance
     */
    protected function log(): LoggerInterface
    {
        return LoggerRegistry::get();
    }

    /**
     * Log a debug message
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->log()->debug($message, $context);
    }

    /**
     * Log an info message
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->log()->info($message, $context);
    }

    /**
     * Log a warning message
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->log()->warning($message, $context);
    }

    /**
     * Log an error message
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->log()->error($message, $context);
    }
}
