<?php

namespace HelgeSverre\Swarm\CLI\Activity;

use DateTimeImmutable;
use HelgeSverre\Swarm\Enums\CLI\ActivityType;
use HelgeSverre\Swarm\Enums\CLI\AnsiColor;

/**
 * Base class for all activity entries in the TUI
 *
 * Provides type-safe structure for different types of activities
 * displayed in the Recent Activity section
 */
abstract class ActivityEntry
{
    public readonly DateTimeImmutable $createdAt;

    public function __construct(
        public readonly ActivityType $type,
        public readonly int $timestamp,
    ) {
        $this->createdAt = (new DateTimeImmutable)->setTimestamp($timestamp);
    }

    /**
     * Get the formatted message for display
     */
    abstract public function getMessage(): string;

    /**
     * Get the display color for this entry
     */
    public function getColor(): AnsiColor
    {
        return $this->type->getColor();
    }

    /**
     * Get the icon for this entry
     */
    public function getIcon(): string
    {
        return $this->type->getIcon();
    }

    /**
     * Check if this entry should display an icon
     */
    public function hasIcon(): bool
    {
        return $this->type->hasIcon();
    }

    /**
     * Convert to array for backwards compatibility
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'message' => $this->getMessage(),
            'color' => $this->getColor()->value,
            'timestamp' => $this->timestamp,
        ];
    }
}
