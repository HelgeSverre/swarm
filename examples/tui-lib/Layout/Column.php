<?php

declare(strict_types=1);

namespace Examples\TuiLib\Layout;

/**
 * Vertical flex layout - arranges children in a column
 */
class Column extends Flex
{
    public function __construct(
        MainAxisAlignment $mainAxisAlignment = MainAxisAlignment::Start,
        CrossAxisAlignment $crossAxisAlignment = CrossAxisAlignment::Start,
        int $spacing = 0,
        ?string $id = null
    ) {
        parent::__construct(
            direction: FlexDirection::Vertical,
            mainAxisAlignment: $mainAxisAlignment,
            crossAxisAlignment: $crossAxisAlignment,
            spacing: $spacing,
            id: $id
        );
    }
}
