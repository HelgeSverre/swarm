<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Events;

/**
 * Base class for tool-related events
 */
abstract class ToolEvent extends Event
{
    public function __construct(
        public readonly string $tool,
        public readonly array $params = []
    ) {
        parent::__construct();
    }
}
