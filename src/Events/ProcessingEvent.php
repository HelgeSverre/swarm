<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

/**
 * Event emitted during agent processing operations
 */
class ProcessingEvent extends Event
{
    public function __construct(
        public readonly string $operation,
        public readonly array $details = [],
        public readonly ?string $phase = null
    ) {
        parent::__construct();
    }

    public function getMessage(): string
    {
        return $this->details['message'] ?? ucfirst(str_replace('_', ' ', $this->operation));
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'operation' => $this->operation,
            'details' => $this->details,
            'phase' => $this->phase,
        ]);
    }
}
