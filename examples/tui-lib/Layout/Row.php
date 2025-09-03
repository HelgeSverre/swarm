<?php

declare(strict_types=1);

namespace Examples\TuiLib\Layout;

/**
 * Horizontal flex layout - arranges children in a row
 */
class Row extends Flex
{
    public function __construct(
        MainAxisAlignment $mainAxisAlignment = MainAxisAlignment::Start,
        CrossAxisAlignment $crossAxisAlignment = CrossAxisAlignment::Start,
        int $spacing = 0,
        ?string $id = null
    ) {
        parent::__construct(
            direction: FlexDirection::Horizontal,
            mainAxisAlignment: $mainAxisAlignment,
            crossAxisAlignment: $crossAxisAlignment,
            spacing: $spacing,
            id: $id
        );
    }
}
