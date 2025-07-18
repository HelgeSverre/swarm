<?php

namespace HelgeSverre\Swarm\Enums\Core;

/**
 * Status of tool execution
 */
enum ToolStatus: string
{
    /**
     * Check if this is a terminal status
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            self::Pending, self::Running => false,
        };
    }

    /**
     * Check if this represents a successful completion
     */
    public function isSuccess(): bool
    {
        return $this === self::Completed;
    }

    /**
     * Get a human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Create from string with null fallback
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
