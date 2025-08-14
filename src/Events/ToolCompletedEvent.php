<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

use HelgeSverre\Swarm\Core\ToolResponse;

/**
 * Event emitted when a tool execution completes
 */
class ToolCompletedEvent extends ToolEvent
{
    public function __construct(
        string $tool,
        array $params,
        public readonly ToolResponse $result,
        public readonly float $duration = 0.0
    ) {
        parent::__construct($tool, $params);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'tool' => $this->tool,
            'params' => $this->params,
            'success' => $this->result->isSuccess(),
            'duration' => $this->duration,
        ]);
    }
}
