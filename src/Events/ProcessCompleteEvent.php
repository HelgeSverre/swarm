<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

use HelgeSverre\Swarm\Agent\AgentResponse;

class ProcessCompleteEvent extends Event
{
    public function __construct(
        public readonly string $processId,
        public readonly AgentResponse $response
    ) {}
}
