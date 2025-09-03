<?php

declare(strict_types=1);

namespace Examples\TuiLib\Layout;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;

/**
 * Stack alignment options
 */
enum StackAlignment: string
{
    case TopLeft = 'top_left';
    case TopCenter = 'top_center';
    case TopRight = 'top_right';
    case CenterLeft = 'center_left';
    case Center = 'center';
    case CenterRight = 'center_right';
    case BottomLeft = 'bottom_left';
    case BottomCenter = 'bottom_center';
    case BottomRight = 'bottom_right';
}

/**
 * Positioned child widget for Stack layout
 */
class Positioned extends Widget
{
    protected ?Widget $child = null;

    protected ?int $top = null;

    protected ?int $right = null;

    protected ?int $bottom = null;

    protected ?int $left = null;

    protected ?int $width = null;

    protected ?int $height = null;

    public function __construct(
        ?Widget $child = null,
        ?int $top = null,
        ?int $right = null,
        ?int $bottom = null,
        ?int $left = null,
        ?int $width = null,
        ?int $height = null,
        ?string $id = null
    ) {
        parent::__construct($id);
        $this->child = $child;
        $this->top = $top;
        $this->right = $right;
        $this->bottom = $bottom;
        $this->left = $left;
        $this->width = $width;
        $this->height = $height;

        if ($child !== null) {
            $this->addChild($child);
        }
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        if ($this->child === null) {
            return $constraints->smallest();
        }

        // Create constraints for positioned child
        $childConstraints = $this->createChildConstraints($constraints);

        return $this->child->measure($childConstraints);
    }

    public function layout(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->clearLayoutFlag();

        if ($this->child === null) {
            return;
        }

        $childBounds = $this->calculateChildBounds($bounds);
        $this->child->layout($childBounds);
    }

    public function paint(BuildContext $context): string
    {
        $this->clearRepaintFlag();

        if ($this->child === null || ! $this->child->isVisible()) {
            return '';
        }

        return $this->child->paint($context);
    }

    // Getters and setters
    public function getChild(): ?Widget
    {
        return $this->child;
    }

    public function setChild(?Widget $child): void
    {
        if ($this->child !== null) {
            $this->removeChild($this->child);
        }

        $this->child = $child;
        if ($child !== null) {
            $this->addChild($child);
        }

        $this->markNeedsLayout();
    }

    public function getTop(): ?int
    {
        return $this->top;
    }

    public function getRight(): ?int
    {
        return $this->right;
    }

    public function getBottom(): ?int
    {
        return $this->bottom;
    }

    public function getLeft(): ?int
    {
        return $this->left;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setTop(?int $top): void
    {
        if ($this->top !== $top) {
            $this->top = $top;
            $this->markNeedsLayout();
        }
    }

    public function setRight(?int $right): void
    {
        if ($this->right !== $right) {
            $this->right = $right;
            $this->markNeedsLayout();
        }
    }

    public function setBottom(?int $bottom): void
    {
        if ($this->bottom !== $bottom) {
            $this->bottom = $bottom;
            $this->markNeedsLayout();
        }
    }

    public function setLeft(?int $left): void
    {
        if ($this->left !== $left) {
            $this->left = $left;
            $this->markNeedsLayout();
        }
    }

    public function setWidth(?int $width): void
    {
        if ($this->width !== $width) {
            $this->width = $width;
            $this->markNeedsLayout();
        }
    }

    public function setHeight(?int $height): void
    {
        if ($this->height !== $height) {
            $this->height = $height;
            $this->markNeedsLayout();
        }
    }

    protected function createChildConstraints(Constraints $parentConstraints): Constraints
    {
        $minWidth = 0;
        $maxWidth = $parentConstraints->maxWidth;
        $minHeight = 0;
        $maxHeight = $parentConstraints->maxHeight;

        // Apply explicit width constraint
        if ($this->width !== null) {
            $minWidth = $maxWidth = $this->width;
        }

        // Apply explicit height constraint
        if ($this->height !== null) {
            $minHeight = $maxHeight = $this->height;
        }

        // Apply positional constraints
        if ($this->left !== null && $this->right !== null) {
            $availableWidth = max(0, $parentConstraints->maxWidth - $this->left - $this->right);
            $minWidth = $maxWidth = $availableWidth;
        }

        if ($this->top !== null && $this->bottom !== null) {
            $availableHeight = max(0, $parentConstraints->maxHeight - $this->top - $this->bottom);
            $minHeight = $maxHeight = $availableHeight;
        }

        return new Constraints($minWidth, $maxWidth, $minHeight, $maxHeight);
    }

    protected function calculateChildBounds(Rect $parentBounds): Rect
    {
        $childSize = $this->child->measure($this->createChildConstraints(
            new Constraints(0, $parentBounds->width, 0, $parentBounds->height)
        ));

        $x = $parentBounds->x;
        $y = $parentBounds->y;
        $width = $childSize->width;
        $height = $childSize->height;

        // Calculate X position
        if ($this->left !== null) {
            $x = $parentBounds->x + $this->left;
        } elseif ($this->right !== null) {
            $x = $parentBounds->right() - $this->right - $width;
        }

        // Calculate Y position
        if ($this->top !== null) {
            $y = $parentBounds->y + $this->top;
        } elseif ($this->bottom !== null) {
            $y = $parentBounds->bottom() - $this->bottom - $height;
        }

        // Apply explicit dimensions
        if ($this->width !== null) {
            $width = $this->width;
        }
        if ($this->height !== null) {
            $height = $this->height;
        }

        // Handle both left and right specified
        if ($this->left !== null && $this->right !== null) {
            $width = max(0, $parentBounds->width - $this->left - $this->right);
        }

        // Handle both top and bottom specified
        if ($this->top !== null && $this->bottom !== null) {
            $height = max(0, $parentBounds->height - $this->top - $this->bottom);
        }

        return new Rect($x, $y, $width, $height);
    }
}

/**
 * Stack layout for overlapping widgets with z-ordering
 */
class Stack extends Widget
{
    protected StackAlignment $alignment;

    protected array $childAlignments = [];

    public function __construct(
        StackAlignment $alignment = StackAlignment::TopLeft,
        ?string $id = null
    ) {
        parent::__construct($id);
        $this->alignment = $alignment;
    }

    public function addChild(Widget $child, ?StackAlignment $alignment = null): void
    {
        parent::addChild($child);
        $this->childAlignments[$child->getId()] = $alignment ?? $this->alignment;
    }

    public function removeChild(Widget $child): void
    {
        unset($this->childAlignments[$child->getId()]);
        parent::removeChild($child);
    }

    public function setChildAlignment(Widget $child, StackAlignment $alignment): void
    {
        if ($this->hasChild($child)) {
            $this->childAlignments[$child->getId()] = $alignment;
            $this->markNeedsLayout();
        }
    }

    public function getChildAlignment(Widget $child): StackAlignment
    {
        return $this->childAlignments[$child->getId()] ?? $this->alignment;
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

        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($this->children as $child) {
            if ($child instanceof Positioned) {
                // Positioned children determine their own size
                $childSize = $child->measure($constraints);
            } else {
                // Non-positioned children can use full available space
                $childSize = $child->measure($constraints->loosen());
            }

            $maxWidth = max($maxWidth, $childSize->width);
            $maxHeight = max($maxHeight, $childSize->height);
        }

        return $constraints->constrain(new Size($maxWidth, $maxHeight));
    }

    public function layout(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->clearLayoutFlag();

        foreach ($this->children as $child) {
            if ($child instanceof Positioned) {
                // Positioned children handle their own layout
                $child->layout($bounds);
            } else {
                // Non-positioned children are aligned within the stack
                $this->layoutNonPositionedChild($child, $bounds);
            }
        }
    }

    public function paint(BuildContext $context): string
    {
        $this->clearRepaintFlag();

        $output = '';

        // Paint children in order (first child is bottom-most, last child is top-most)
        foreach ($this->children as $child) {
            if ($child->isVisible()) {
                $output .= $child->paint($context);
            }
        }

        return $output;
    }

    // Z-order management
    public function bringToFront(Widget $child): void
    {
        if (! $this->hasChild($child)) {
            return;
        }

        // Remove and re-add to move to end of array (top of z-order)
        $alignment = $this->getChildAlignment($child);
        $this->removeChild($child);
        $this->addChild($child, $alignment);
    }

    public function sendToBack(Widget $child): void
    {
        if (! $this->hasChild($child)) {
            return;
        }

        $alignment = $this->getChildAlignment($child);
        $this->removeChild($child);

        // Insert at beginning of children array
        array_unshift($this->children, $child);
        $child->setParent($this);
        $this->childAlignments[$child->getId()] = $alignment;

        $this->markNeedsLayout();
    }

    public function moveUp(Widget $child): void
    {
        $index = array_search($child, $this->children, true);
        if ($index === false || $index >= count($this->children) - 1) {
            return;
        }

        // Swap with next child (higher z-order)
        $temp = $this->children[$index];
        $this->children[$index] = $this->children[$index + 1];
        $this->children[$index + 1] = $temp;

        $this->markNeedsLayout();
    }

    public function moveDown(Widget $child): void
    {
        $index = array_search($child, $this->children, true);
        if ($index === false || $index <= 0) {
            return;
        }

        // Swap with previous child (lower z-order)
        $temp = $this->children[$index];
        $this->children[$index] = $this->children[$index - 1];
        $this->children[$index - 1] = $temp;

        $this->markNeedsLayout();
    }

    public function getZOrder(Widget $child): int
    {
        $index = array_search($child, $this->children, true);

        return $index !== false ? $index : -1;
    }

    // Getters and setters
    public function getAlignment(): StackAlignment
    {
        return $this->alignment;
    }

    public function setAlignment(StackAlignment $alignment): void
    {
        if ($this->alignment !== $alignment) {
            $this->alignment = $alignment;
            $this->markNeedsLayout();
        }
    }

    protected function layoutNonPositionedChild(Widget $child, Rect $bounds): void
    {
        $constraints = new Constraints(0, $bounds->width, 0, $bounds->height);
        $childSize = $child->measure($constraints);
        $alignment = $this->getChildAlignment($child);

        $childBounds = $this->calculateAlignedBounds($bounds, $childSize, $alignment);
        $child->layout($childBounds);
    }

    protected function calculateAlignedBounds(Rect $parentBounds, Size $childSize, StackAlignment $alignment): Rect
    {
        $x = $parentBounds->x;
        $y = $parentBounds->y;

        switch ($alignment) {
            case StackAlignment::TopLeft:
                // Already set to top-left
                break;
            case StackAlignment::TopCenter:
                $x = $parentBounds->x + intval(($parentBounds->width - $childSize->width) / 2);
                break;
            case StackAlignment::TopRight:
                $x = $parentBounds->right() - $childSize->width;
                break;
            case StackAlignment::CenterLeft:
                $y = $parentBounds->y + intval(($parentBounds->height - $childSize->height) / 2);
                break;
            case StackAlignment::Center:
                $x = $parentBounds->x + intval(($parentBounds->width - $childSize->width) / 2);
                $y = $parentBounds->y + intval(($parentBounds->height - $childSize->height) / 2);
                break;
            case StackAlignment::CenterRight:
                $x = $parentBounds->right() - $childSize->width;
                $y = $parentBounds->y + intval(($parentBounds->height - $childSize->height) / 2);
                break;
            case StackAlignment::BottomLeft:
                $y = $parentBounds->bottom() - $childSize->height;
                break;
            case StackAlignment::BottomCenter:
                $x = $parentBounds->x + intval(($parentBounds->width - $childSize->width) / 2);
                $y = $parentBounds->bottom() - $childSize->height;
                break;
            case StackAlignment::BottomRight:
                $x = $parentBounds->right() - $childSize->width;
                $y = $parentBounds->bottom() - $childSize->height;
                break;
        }

        return new Rect($x, $y, $childSize->width, $childSize->height);
    }
}
