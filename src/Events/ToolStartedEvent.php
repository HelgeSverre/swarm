<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

/**
 * Event emitted when a tool execution starts
 */
class ToolStartedEvent extends ToolEvent
{
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'tool' => $this->tool,
            'params' => $this->params,
        ]);
    }
}
