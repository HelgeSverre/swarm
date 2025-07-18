<?php

namespace HelgeSverre\Swarm\Task;

enum TaskStatus: string
{
    /**
     * Get all possible status values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if task can transition to another status
     */
    public function canTransitionTo(TaskStatus $newStatus): bool
    {
        return match ($this) {
            self::Pending => $newStatus === self::Planned,
            self::Planned => $newStatus === self::Executing,
            self::Executing => $newStatus === self::Completed,
            self::Completed => false, // Completed is final
        };
    }

    /**
     * Get the next valid status in the workflow
     */
    public function nextStatus(): ?TaskStatus
    {
        return match ($this) {
            self::Pending => self::Planned,
            self::Planned => self::Executing,
            self::Executing => self::Completed,
            self::Completed => null,
        };
    }

    /**
     * Check if this is a terminal status
     */
    public function isTerminal(): bool
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
            self::Planned => 'Planned',
            self::Executing => 'Executing',
            self::Completed => 'Completed',
        };
    }

    /**
     * Get emoji representation for TUI
     */
    public function emoji(): string
    {
        return match ($this) {
            self::Pending => '⏳',
            self::Planned => '📋',
            self::Executing => '🔄',
            self::Completed => '✅',
        };
    }
    case Pending = 'pending';
    case Planned = 'planned';
    case Executing = 'executing';
    case Completed = 'completed';
}
