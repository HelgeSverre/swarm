<?php

namespace HelgeSverre\Swarm\Tools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Tool
{
    public function __construct(
        public string $name,
        public string $description,
        public ?string $type = null,
    ) {}
}
