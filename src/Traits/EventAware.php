<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Traits;

use HelgeSverre\Swarm\Events\Event;
use HelgeSverre\Swarm\Events\EventBus;

/**
 * Trait for components that need to emit events
 * Provides easy access to EventBus without requiring injection
 */
trait EventAware
{
    /**
     * Emit an event through the global EventBus
     */
    protected function emit(Event $event): void
    {
        EventBus::fire($event);
    }

    /**
     * Subscribe to an event through the global EventBus
     */
    protected function subscribe(string $eventClass, callable $listener): void
    {
        EventBus::subscribe($eventClass, $listener);
    }

    /**
     * Unsubscribe from an event through the global EventBus
     */
    protected function unsubscribe(string $eventClass, callable $listener): void
    {
        EventBus::unsubscribe($eventClass, $listener);
    }
}
