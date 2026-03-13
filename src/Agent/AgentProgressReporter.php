<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use Closure;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ProcessingEvent;

final class AgentProgressReporter
{
    private ?Closure $callback = null;

    public function __construct(
        private readonly EventBus $eventBus,
    ) {}

    public function setCallback(?callable $callback): void
    {
        $this->callback = $callback instanceof Closure
            ? $callback
            : ($callback !== null ? Closure::fromCallable($callback) : null);
    }

    public function report(string $operation, array $details = []): void
    {
        if ($this->callback !== null) {
            ($this->callback)($operation, $details);
        }

        $this->eventBus->emit(new ProcessingEvent($operation, $details));
    }

    public function asCallback(): Closure
    {
        return fn (string $operation, array $details = []) => $this->report($operation, $details);
    }
}
