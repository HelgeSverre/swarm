<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

/**
 * Event emitted when user provides input
 */
class UserInputEvent extends Event
{
    public function __construct(
        public readonly string $input,
        public readonly string $source = 'terminal'
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'input' => $this->input,
            'source' => $this->source,
        ]);
    }
}
