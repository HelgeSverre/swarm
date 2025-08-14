<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

use Exception;

/**
 * Simple event bus for pub/sub communication
 * Supports both static and instance-based usage for pragmatic access
 */
class EventBus
{
    /**
     * @var array<string, array<callable>>
     */
    protected array $listeners = [];

    /**
     * @var array<Event>
     */
    protected array $eventQueue = [];

    protected bool $processing = false;

    /**
     * Singleton instance for static access
     */
    private static ?EventBus $instance = null;

    /**
     * Get the singleton instance
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Set the singleton instance (useful for testing or custom instances)
     */
    public static function setInstance(?EventBus $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Emit an event to all registered listeners
     */
    public function emit(Event $event): void
    {
        // Add to queue to prevent recursive emit issues
        $this->eventQueue[] = $event;

        // If we're not already processing, start processing the queue
        if (! $this->processing) {
            $this->processQueue();
        }
    }

    /**
     * Subscribe to an event type
     *
     * @param string $eventClass The event class name or '*' for all events
     * @param callable $listener The callback to invoke when event is emitted
     */
    public function on(string $eventClass, callable $listener): void
    {
        if (! isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }

        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Unsubscribe from an event type
     */
    public function off(string $eventClass, callable $listener): void
    {
        if (! isset($this->listeners[$eventClass])) {
            return;
        }

        $this->listeners[$eventClass] = array_filter(
            $this->listeners[$eventClass],
            fn ($l) => $l !== $listener
        );

        // Clean up empty listener arrays
        if (empty($this->listeners[$eventClass])) {
            unset($this->listeners[$eventClass]);
        }
    }

    /**
     * Remove all listeners for an event type
     */
    public function removeAllListeners(?string $eventClass = null): void
    {
        if ($eventClass === null) {
            $this->listeners = [];
        } else {
            unset($this->listeners[$eventClass]);
        }
    }

    /**
     * Get the number of listeners for an event type
     */
    public function listenerCount(string $eventClass): int
    {
        return isset($this->listeners[$eventClass])
            ? count($this->listeners[$eventClass])
            : 0;
    }

    /**
     * Static convenience method to emit an event
     */
    public static function fire(Event $event): void
    {
        self::getInstance()->emit($event);
    }

    /**
     * Static convenience method to subscribe to an event
     */
    public static function subscribe(string $eventClass, callable $listener): void
    {
        self::getInstance()->on($eventClass, $listener);
    }

    /**
     * Static convenience method to unsubscribe from an event
     */
    public static function unsubscribe(string $eventClass, callable $listener): void
    {
        self::getInstance()->off($eventClass, $listener);
    }

    /**
     * Process queued events
     */
    protected function processQueue(): void
    {
        $this->processing = true;

        while (! empty($this->eventQueue)) {
            $event = array_shift($this->eventQueue);
            $this->dispatch($event);
        }

        $this->processing = false;
    }

    /**
     * Dispatch an event to its listeners
     */
    protected function dispatch(Event $event): void
    {
        $eventName = $event->getName();

        // Check for exact match listeners
        if (isset($this->listeners[$eventName])) {
            foreach ($this->listeners[$eventName] as $listener) {
                try {
                    $listener($event);
                } catch (Exception $e) {
                    // Log but don't stop other listeners
                    error_log("Event listener failed for {$eventName}: " . $e->getMessage());
                }
            }
        }

        // Check for wildcard listeners
        if (isset($this->listeners['*'])) {
            foreach ($this->listeners['*'] as $listener) {
                try {
                    $listener($event);
                } catch (Exception $e) {
                    error_log('Wildcard event listener failed: ' . $e->getMessage());
                }
            }
        }
    }
}
