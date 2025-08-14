<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

/**
 * Base event class for all system events
 */
abstract class Event
{
    public readonly float $timestamp;

    public readonly string $id;

    public function __construct()
    {
        $this->timestamp = microtime(true);
        $this->id = uniqid('evt_', true);
    }

    /**
     * Get the event name (used for subscriptions)
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * Convert event to array for serialization
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'id' => $this->id,
            'timestamp' => $this->timestamp,
        ];
    }
}
