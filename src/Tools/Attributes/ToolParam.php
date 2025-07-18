<?php

namespace HelgeSverre\Swarm\Tools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class ToolParam
{
    public function __construct(
        public string $description,
        public bool $required = true,
        public ?string $type = null,
        public mixed $default = null,
        public ?array $enum = null,
    ) {}
}
