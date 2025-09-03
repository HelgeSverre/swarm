<?php

declare(strict_types=1);

namespace Examples\TuiLib\Layout;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;

/**
 * Layout direction for flex containers
 */
enum FlexDirection: string
{
    case Horizontal = 'horizontal';
    case Vertical = 'vertical';
}

/**
 * Main axis alignment options
 */
enum MainAxisAlignment: string
{
    case Start = 'start';
    case End = 'end';
    case Center = 'center';
    case SpaceBetween = 'space_between';
    case SpaceAround = 'space_around';
    case SpaceEvenly = 'space_evenly';
}

/**
 * Cross axis alignment options
 */
enum CrossAxisAlignment: string
{
    case Start = 'start';
    case End = 'end';
    case Center = 'center';
    case Stretch = 'stretch';
    case Baseline = 'baseline';
}

/**
 * Flexible box layout widget that arranges children in a single direction
 */
class Flex extends Widget
{
    protected FlexDirection $direction;

    protected MainAxisAlignment $mainAxisAlignment;

    protected CrossAxisAlignment $crossAxisAlignment;

    protected int $spacing;

    protected array $flexValues = [];

    public function __construct(
        FlexDirection $direction = FlexDirection::Horizontal,
        MainAxisAlignment $mainAxisAlignment = MainAxisAlignment::Start,
        CrossAxisAlignment $crossAxisAlignment = CrossAxisAlignment::Start,
        int $spacing = 0,
        ?string $id = null
    ) {
        parent::__construct($id);
        $this->direction = $direction;
        $this->mainAxisAlignment = $mainAxisAlignment;
        $this->crossAxisAlignment = $crossAxisAlignment;
        $this->spacing = $spacing;
    }

    public function addChild(Widget $child, int $flex = 0): void
    {
        parent::addChild($child);
        $this->flexValues[$child->getId()] = $flex;
    }

    public function removeChild(Widget $child): void
    {
        unset($this->flexValues[$child->getId()]);
        parent::removeChild($child);
    }

    public function setFlex(Widget $child, int $flex): void
    {
        if ($this->hasChild($child)) {
            $this->flexValues[$child->getId()] = $flex;
            $this->markNeedsLayout();
        }
    }

    public function getFlex(Widget $child): int
    {
        return $this->flexValues[$child->getId()] ?? 0;
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        if (empty($this->children)) {
            return $constraints->smallest();
        }

        $isHorizontal = $this->direction === FlexDirection::Horizontal;
        $totalFlex = array_sum($this->flexValues);
        $totalSpacing = max(0, count($this->children) - 1) * $this->spacing;

        $mainAxisSize = 0;
        $crossAxisSize = 0;
        $flexibleSpace = 0;

        // Calculate available space for flexible children
        if ($isHorizontal) {
            $availableSpace = max(0, $constraints->maxWidth - $totalSpacing);
        } else {
            $availableSpace = max(0, $constraints->maxHeight - $totalSpacing);
        }

        // First pass: measure non-flexible children
        foreach ($this->children as $child) {
            $flex = $this->getFlex($child);

            if ($flex === 0) {
                $childConstraints = $this->createChildConstraints($constraints, $isHorizontal);
                $childSize = $child->measure($childConstraints);

                if ($isHorizontal) {
                    $mainAxisSize += $childSize->width;
                    $crossAxisSize = max($crossAxisSize, $childSize->height);
                    $availableSpace -= $childSize->width;
                } else {
                    $mainAxisSize += $childSize->height;
                    $crossAxisSize = max($crossAxisSize, $childSize->width);
                    $availableSpace -= $childSize->height;
                }
            }
        }

        // Calculate space for flexible children
        if ($totalFlex > 0 && $availableSpace > 0) {
            $flexibleSpace = $availableSpace;
            $unitSpace = $flexibleSpace / $totalFlex;

            // Second pass: measure flexible children
            foreach ($this->children as $child) {
                $flex = $this->getFlex($child);

                if ($flex > 0) {
                    $flexSpace = intval($unitSpace * $flex);
                    $childConstraints = $this->createFlexChildConstraints(
                        $constraints,
                        $isHorizontal,
                        $flexSpace
                    );
                    $childSize = $child->measure($childConstraints);

                    if ($isHorizontal) {
                        $mainAxisSize += $flexSpace;
                        $crossAxisSize = max($crossAxisSize, $childSize->height);
                    } else {
                        $mainAxisSize += $flexSpace;
                        $crossAxisSize = max($crossAxisSize, $childSize->width);
                    }
                }
            }
        }

        $mainAxisSize += $totalSpacing;

        if ($isHorizontal) {
            return $constraints->constrain(new Size($mainAxisSize, $crossAxisSize));
        }

        return $constraints->constrain(new Size($crossAxisSize, $mainAxisSize));
    }

    public function layout(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->clearLayoutFlag();

        if (empty($this->children)) {
            return;
        }

        $isHorizontal = $this->direction === FlexDirection::Horizontal;
        $totalFlex = array_sum($this->flexValues);
        $totalSpacing = max(0, count($this->children) - 1) * $this->spacing;

        // Calculate available space
        $availableMainAxis = $isHorizontal ? $bounds->width : $bounds->height;
        $availableCrossAxis = $isHorizontal ? $bounds->height : $bounds->width;
        $availableMainAxis -= $totalSpacing;

        // Measure all children to determine their natural sizes
        $childSizes = [];
        $nonFlexSize = 0;

        foreach ($this->children as $child) {
            $flex = $this->getFlex($child);
            $childConstraints = $this->createChildConstraints(
                new Constraints(0, $bounds->width, 0, $bounds->height),
                $isHorizontal
            );

            if ($flex === 0) {
                $size = $child->measure($childConstraints);
                $childSizes[$child->getId()] = $size;
                $nonFlexSize += $isHorizontal ? $size->width : $size->height;
            }
        }

        // Calculate space for flexible children
        $flexSpace = max(0, $availableMainAxis - $nonFlexSize);
        $unitSpace = $totalFlex > 0 ? $flexSpace / $totalFlex : 0;

        // Measure flexible children
        foreach ($this->children as $child) {
            $flex = $this->getFlex($child);

            if ($flex > 0) {
                $allocatedSpace = intval($unitSpace * $flex);
                $childConstraints = $this->createFlexChildConstraints(
                    new Constraints(0, $bounds->width, 0, $bounds->height),
                    $isHorizontal,
                    $allocatedSpace
                );
                $childSizes[$child->getId()] = $child->measure($childConstraints);
            }
        }

        // Position children based on alignment
        $this->positionChildren($bounds, $childSizes, $isHorizontal, $availableMainAxis, $availableCrossAxis);
    }

    public function paint(BuildContext $context): string
    {
        $this->clearRepaintFlag();

        $output = '';
        foreach ($this->children as $child) {
            if ($child->isVisible()) {
                $output .= $child->paint($context);
            }
        }

        return $output;
    }

    // Getters and setters
    public function getDirection(): FlexDirection
    {
        return $this->direction;
    }

    public function setDirection(FlexDirection $direction): void
    {
        if ($this->direction !== $direction) {
            $this->direction = $direction;
            $this->markNeedsLayout();
        }
    }

    public function getMainAxisAlignment(): MainAxisAlignment
    {
        return $this->mainAxisAlignment;
    }

    public function setMainAxisAlignment(MainAxisAlignment $alignment): void
    {
        if ($this->mainAxisAlignment !== $alignment) {
            $this->mainAxisAlignment = $alignment;
            $this->markNeedsLayout();
        }
    }

    public function getCrossAxisAlignment(): CrossAxisAlignment
    {
        return $this->crossAxisAlignment;
    }

    public function setCrossAxisAlignment(CrossAxisAlignment $alignment): void
    {
        if ($this->crossAxisAlignment !== $alignment) {
            $this->crossAxisAlignment = $alignment;
            $this->markNeedsLayout();
        }
    }

    public function getSpacing(): int
    {
        return $this->spacing;
    }

    public function setSpacing(int $spacing): void
    {
        if ($this->spacing !== $spacing) {
            $this->spacing = max(0, $spacing);
            $this->markNeedsLayout();
        }
    }

    protected function createChildConstraints(Constraints $parentConstraints, bool $isHorizontal): Constraints
    {
        if ($isHorizontal) {
            return new Constraints(
                minWidth: 0,
                maxWidth: $parentConstraints->maxWidth,
                minHeight: $parentConstraints->minHeight,
                maxHeight: $parentConstraints->maxHeight
            );
        }

        return new Constraints(
            minWidth: $parentConstraints->minWidth,
            maxWidth: $parentConstraints->maxWidth,
            minHeight: 0,
            maxHeight: $parentConstraints->maxHeight
        );
    }

    protected function createFlexChildConstraints(Constraints $parentConstraints, bool $isHorizontal, int $flexSpace): Constraints
    {
        if ($isHorizontal) {
            return new Constraints(
                minWidth: $flexSpace,
                maxWidth: $flexSpace,
                minHeight: $parentConstraints->minHeight,
                maxHeight: $parentConstraints->maxHeight
            );
        }

        return new Constraints(
            minWidth: $parentConstraints->minWidth,
            maxWidth: $parentConstraints->maxWidth,
            minHeight: $flexSpace,
            maxHeight: $flexSpace
        );
    }

    protected function positionChildren(Rect $bounds, array $childSizes, bool $isHorizontal, int $availableMainAxis, int $availableCrossAxis): void
    {
        $totalChildMainAxis = 0;
        foreach ($this->children as $child) {
            $size = $childSizes[$child->getId()];
            $totalChildMainAxis += $isHorizontal ? $size->width : $size->height;
        }
        $totalChildMainAxis += max(0, count($this->children) - 1) * $this->spacing;

        // Calculate main axis start position
        $mainAxisStart = $this->calculateMainAxisStart($bounds, $availableMainAxis, $totalChildMainAxis, $isHorizontal);
        $currentMainAxis = $mainAxisStart;

        foreach ($this->children as $index => $child) {
            $size = $childSizes[$child->getId()];

            // Calculate cross axis position
            $crossAxisPos = $this->calculateCrossAxisPosition($bounds, $size, $availableCrossAxis, $isHorizontal);

            // Position the child
            if ($isHorizontal) {
                $childBounds = new Rect($currentMainAxis, $crossAxisPos, $size->width, $size->height);
                $currentMainAxis += $size->width + ($index < count($this->children) - 1 ? $this->spacing : 0);
            } else {
                $childBounds = new Rect($crossAxisPos, $currentMainAxis, $size->width, $size->height);
                $currentMainAxis += $size->height + ($index < count($this->children) - 1 ? $this->spacing : 0);
            }

            $child->layout($childBounds);
        }
    }

    protected function calculateMainAxisStart(Rect $bounds, int $availableMainAxis, int $totalChildMainAxis, bool $isHorizontal): int
    {
        $start = $isHorizontal ? $bounds->x : $bounds->y;
        $remainingSpace = max(0, $availableMainAxis - $totalChildMainAxis + max(0, count($this->children) - 1) * $this->spacing);

        return match ($this->mainAxisAlignment) {
            MainAxisAlignment::Start => $start,
            MainAxisAlignment::End => $start + $remainingSpace,
            MainAxisAlignment::Center => $start + intval($remainingSpace / 2),
            MainAxisAlignment::SpaceBetween,
            MainAxisAlignment::SpaceAround,
            MainAxisAlignment::SpaceEvenly => $start, // Handled separately in spacing calculation
        };
    }

    protected function calculateCrossAxisPosition(Rect $bounds, Size $childSize, int $availableCrossAxis, bool $isHorizontal): int
    {
        $start = $isHorizontal ? $bounds->y : $bounds->x;
        $childCrossAxis = $isHorizontal ? $childSize->height : $childSize->width;

        return match ($this->crossAxisAlignment) {
            CrossAxisAlignment::Start => $start,
            CrossAxisAlignment::End => $start + $availableCrossAxis - $childCrossAxis,
            CrossAxisAlignment::Center => $start + intval(($availableCrossAxis - $childCrossAxis) / 2),
            CrossAxisAlignment::Stretch => $start,
            CrossAxisAlignment::Baseline => $start, // Simplified for terminal UI
        };
    }
}
