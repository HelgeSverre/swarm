<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

class ProcessProgressEvent extends Event
{
    public function __construct(
        public readonly string $processId,
        public readonly string $type,
        public readonly array $data
    ) {}
}
