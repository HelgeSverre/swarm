<?php

declare(strict_types=1);

namespace Examples\TuiLib\Layout;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;

/**
 * Scroll direction options
 */
enum ScrollDirection: string
{
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';
    case Both = 'both';
}

/**
 * Scrollbar style options
 */
enum ScrollbarStyle: string
{
    case Auto = 'auto';       // Show when needed
    case Always = 'always';   // Always visible
    case Never = 'never';     // Never visible
}

/**
 * Scrollable container widget with viewport and scroll offset management
 */
class ScrollView extends Widget
{
    protected ?Widget $child = null;

    protected ScrollDirection $scrollDirection;

    protected ScrollbarStyle $scrollbarStyle;

    protected int $scrollOffsetX = 0;

    protected int $scrollOffsetY = 0;

    protected bool $showScrollbars = true;

    protected Size $viewportSize;

    protected Size $contentSize;

    protected int $scrollStep = 1;

    public function __construct(
        ?Widget $child = null,
        ScrollDirection $scrollDirection = ScrollDirection::Vertical,
        ScrollbarStyle $scrollbarStyle = ScrollbarStyle::Auto,
        bool $showScrollbars = true,
        int $scrollStep = 1,
        ?string $id = null
    ) {
        parent::__construct($id);
        $this->child = $child;
        $this->scrollDirection = $scrollDirection;
        $this->scrollbarStyle = $scrollbarStyle;
        $this->showScrollbars = $showScrollbars;
        $this->scrollStep = max(1, $scrollStep);
        $this->viewportSize = new Size(0, 0);
        $this->contentSize = new Size(0, 0);
        $this->focusable = true; // Enable keyboard navigation

        if ($child !== null) {
            $this->addChild($child);
        }
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

    public function getChild(): ?Widget
    {
        return $this->child;
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

        // Measure child without viewport constraints to get full content size
        $childConstraints = new Constraints(
            minWidth: 0,
            maxWidth: PHP_INT_MAX,
            minHeight: 0,
            maxHeight: PHP_INT_MAX
        );

        $this->contentSize = $this->child->measure($childConstraints);

        // Return the constrained viewport size
        return $constraints->constrain($constraints->biggest());
    }

    public function layout(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->clearLayoutFlag();

        // Calculate viewport size (excluding scrollbars if visible)
        $scrollbarWidth = $this->shouldShowVerticalScrollbar() ? 1 : 0;
        $scrollbarHeight = $this->shouldShowHorizontalScrollbar() ? 1 : 0;

        $this->viewportSize = new Size(
            max(0, $bounds->width - $scrollbarWidth),
            max(0, $bounds->height - $scrollbarHeight)
        );

        if ($this->child === null) {
            return;
        }

        // Remeasure child to get accurate content size
        $childConstraints = new Constraints(
            minWidth: 0,
            maxWidth: PHP_INT_MAX,
            minHeight: 0,
            maxHeight: PHP_INT_MAX
        );

        $this->contentSize = $this->child->measure($childConstraints);

        // Clamp scroll offsets to valid ranges
        $this->clampScrollOffsets();

        // Layout child at its natural size, offset by scroll position
        $childBounds = new Rect(
            $bounds->x - $this->scrollOffsetX,
            $bounds->y - $this->scrollOffsetY,
            $this->contentSize->width,
            $this->contentSize->height
        );

        $this->child->layout($childBounds);
    }

    public function paint(BuildContext $context): string
    {
        $this->clearRepaintFlag();

        if ($this->bounds === null) {
            return '';
        }

        $output = '';

        // Paint child content clipped to viewport
        if ($this->child !== null && $this->child->isVisible()) {
            $output .= $this->paintClippedChild($context);
        }

        // Paint scrollbars
        if ($this->showScrollbars) {
            $output .= $this->paintScrollbars($context);
        }

        return $output;
    }

    // Keyboard event handling for scroll navigation
    public function handleKeyEvent(string $key): bool
    {
        $handled = false;

        switch ($key) {
            case 'up':
            case 'k':
                if ($this->canScrollVertically()) {
                    $this->scrollUp();
                    $handled = true;
                }
                break;
            case 'down':
            case 'j':
                if ($this->canScrollVertically()) {
                    $this->scrollDown();
                    $handled = true;
                }
                break;
            case 'left':
            case 'h':
                if ($this->canScrollHorizontally()) {
                    $this->scrollLeft();
                    $handled = true;
                }
                break;
            case 'right':
            case 'l':
                if ($this->canScrollHorizontally()) {
                    $this->scrollRight();
                    $handled = true;
                }
                break;
            case 'page_up':
                if ($this->canScrollVertically()) {
                    $this->scrollUp($this->viewportSize->height);
                    $handled = true;
                }
                break;
            case 'page_down':
                if ($this->canScrollVertically()) {
                    $this->scrollDown($this->viewportSize->height);
                    $handled = true;
                }
                break;
            case 'home':
                $this->scrollToTop();
                $handled = true;
                break;
            case 'end':
                $this->scrollToBottom();
                $handled = true;
                break;
        }

        return $handled;
    }

    // Scroll control methods
    public function scrollUp(?int $amount = null): void
    {
        if (! $this->canScrollVertically()) {
            return;
        }

        $amount = $amount ?? $this->scrollStep;
        $this->scrollOffsetY = max(0, $this->scrollOffsetY - $amount);
        $this->markNeedsLayout();
    }

    public function scrollDown(?int $amount = null): void
    {
        if (! $this->canScrollVertically()) {
            return;
        }

        $amount = $amount ?? $this->scrollStep;
        $maxScroll = max(0, $this->contentSize->height - $this->viewportSize->height);
        $this->scrollOffsetY = min($maxScroll, $this->scrollOffsetY + $amount);
        $this->markNeedsLayout();
    }

    public function scrollLeft(?int $amount = null): void
    {
        if (! $this->canScrollHorizontally()) {
            return;
        }

        $amount = $amount ?? $this->scrollStep;
        $this->scrollOffsetX = max(0, $this->scrollOffsetX - $amount);
        $this->markNeedsLayout();
    }

    public function scrollRight(?int $amount = null): void
    {
        if (! $this->canScrollHorizontally()) {
            return;
        }

        $amount = $amount ?? $this->scrollStep;
        $maxScroll = max(0, $this->contentSize->width - $this->viewportSize->width);
        $this->scrollOffsetX = min($maxScroll, $this->scrollOffsetX + $amount);
        $this->markNeedsLayout();
    }

    public function scrollToTop(): void
    {
        $this->scrollOffsetY = 0;
        $this->markNeedsLayout();
    }

    public function scrollToBottom(): void
    {
        $this->scrollOffsetY = max(0, $this->contentSize->height - $this->viewportSize->height);
        $this->markNeedsLayout();
    }

    public function scrollToLeft(): void
    {
        $this->scrollOffsetX = 0;
        $this->markNeedsLayout();
    }

    public function scrollToRight(): void
    {
        $this->scrollOffsetX = max(0, $this->contentSize->width - $this->viewportSize->width);
        $this->markNeedsLayout();
    }

    public function scrollTo(?int $x = null, ?int $y = null): void
    {
        if ($x !== null && $this->canScrollHorizontally()) {
            $maxScrollX = max(0, $this->contentSize->width - $this->viewportSize->width);
            $this->scrollOffsetX = max(0, min($x, $maxScrollX));
        }

        if ($y !== null && $this->canScrollVertically()) {
            $maxScrollY = max(0, $this->contentSize->height - $this->viewportSize->height);
            $this->scrollOffsetY = max(0, min($y, $maxScrollY));
        }

        $this->markNeedsLayout();
    }

    // Getters and setters
    public function getScrollDirection(): ScrollDirection
    {
        return $this->scrollDirection;
    }

    public function setScrollDirection(ScrollDirection $direction): void
    {
        if ($this->scrollDirection !== $direction) {
            $this->scrollDirection = $direction;
            $this->markNeedsLayout();
        }
    }

    public function getScrollbarStyle(): ScrollbarStyle
    {
        return $this->scrollbarStyle;
    }

    public function setScrollbarStyle(ScrollbarStyle $style): void
    {
        if ($this->scrollbarStyle !== $style) {
            $this->scrollbarStyle = $style;
            $this->markNeedsRepaint();
        }
    }

    public function getScrollOffsetX(): int
    {
        return $this->scrollOffsetX;
    }

    public function getScrollOffsetY(): int
    {
        return $this->scrollOffsetY;
    }

    public function getViewportSize(): Size
    {
        return $this->viewportSize;
    }

    public function getContentSize(): Size
    {
        return $this->contentSize;
    }

    public function getScrollStep(): int
    {
        return $this->scrollStep;
    }

    public function setScrollStep(int $step): void
    {
        $this->scrollStep = max(1, $step);
    }

    public function isShowScrollbars(): bool
    {
        return $this->showScrollbars;
    }

    public function setShowScrollbars(bool $show): void
    {
        if ($this->showScrollbars !== $show) {
            $this->showScrollbars = $show;
            $this->markNeedsLayout();
        }
    }

    // Scroll position information
    public function getScrollPercentageX(): float
    {
        $maxScroll = max(1, $this->contentSize->width - $this->viewportSize->width);

        return $this->scrollOffsetX / $maxScroll;
    }

    public function getScrollPercentageY(): float
    {
        $maxScroll = max(1, $this->contentSize->height - $this->viewportSize->height);

        return $this->scrollOffsetY / $maxScroll;
    }

    public function canScrollUp(): bool
    {
        return $this->canScrollVertically() && $this->scrollOffsetY > 0;
    }

    public function canScrollDown(): bool
    {
        return $this->canScrollVertically() &&
               $this->scrollOffsetY < ($this->contentSize->height - $this->viewportSize->height);
    }

    public function canScrollLeft(): bool
    {
        return $this->canScrollHorizontally() && $this->scrollOffsetX > 0;
    }

    public function canScrollRight(): bool
    {
        return $this->canScrollHorizontally() &&
               $this->scrollOffsetX < ($this->contentSize->width - $this->viewportSize->width);
    }

    protected function paintClippedChild(BuildContext $context): string
    {
        if ($this->child === null || $this->bounds === null) {
            return '';
        }

        // Get child content
        $childOutput = $this->child->paint($context);

        // For simplicity, we'll let the child paint normally
        // In a more sophisticated implementation, we would clip the output
        // to the viewport bounds
        return $childOutput;
    }

    protected function paintScrollbars(BuildContext $context): string
    {
        if ($this->bounds === null) {
            return '';
        }

        $output = '';

        // Paint vertical scrollbar
        if ($this->shouldShowVerticalScrollbar()) {
            $output .= $this->paintVerticalScrollbar();
        }

        // Paint horizontal scrollbar
        if ($this->shouldShowHorizontalScrollbar()) {
            $output .= $this->paintHorizontalScrollbar();
        }

        return $output;
    }

    protected function paintVerticalScrollbar(): string
    {
        if ($this->bounds === null || $this->contentSize->height <= $this->viewportSize->height) {
            return '';
        }

        $output = '';
        $scrollbarX = $this->bounds->right() - 1;
        $scrollbarHeight = $this->viewportSize->height;

        // Calculate scrollbar thumb position and size
        $contentHeight = $this->contentSize->height;
        $thumbHeight = max(1, intval(($this->viewportSize->height / $contentHeight) * $scrollbarHeight));
        $thumbPos = intval(($this->scrollOffsetY / ($contentHeight - $this->viewportSize->height)) * ($scrollbarHeight - $thumbHeight));

        // Paint scrollbar track
        for ($y = 0; $y < $scrollbarHeight; $y++) {
            $screenY = $this->bounds->y + $y;
            $output .= "\033[{$screenY};{$scrollbarX}H";

            if ($y >= $thumbPos && $y < $thumbPos + $thumbHeight) {
                $output .= '█'; // Thumb
            } else {
                $output .= '░'; // Track
            }
        }

        return $output;
    }

    protected function paintHorizontalScrollbar(): string
    {
        if ($this->bounds === null || $this->contentSize->width <= $this->viewportSize->width) {
            return '';
        }

        $output = '';
        $scrollbarY = $this->bounds->bottom() - 1;
        $scrollbarWidth = $this->viewportSize->width;

        // Calculate scrollbar thumb position and size
        $contentWidth = $this->contentSize->width;
        $thumbWidth = max(1, intval(($this->viewportSize->width / $contentWidth) * $scrollbarWidth));
        $thumbPos = intval(($this->scrollOffsetX / ($contentWidth - $this->viewportSize->width)) * ($scrollbarWidth - $thumbWidth));

        // Paint scrollbar track
        $output .= "\033[{$scrollbarY};" . ($this->bounds->x + 1) . 'H';

        for ($x = 0; $x < $scrollbarWidth; $x++) {
            if ($x >= $thumbPos && $x < $thumbPos + $thumbWidth) {
                $output .= '█'; // Thumb
            } else {
                $output .= '░'; // Track
            }
        }

        return $output;
    }

    protected function shouldShowVerticalScrollbar(): bool
    {
        return $this->showScrollbars &&
               $this->canScrollVertically() &&
               ($this->scrollbarStyle === ScrollbarStyle::Always ||
                ($this->scrollbarStyle === ScrollbarStyle::Auto && $this->contentSize->height > $this->viewportSize->height));
    }

    protected function shouldShowHorizontalScrollbar(): bool
    {
        return $this->showScrollbars &&
               $this->canScrollHorizontally() &&
               ($this->scrollbarStyle === ScrollbarStyle::Always ||
                ($this->scrollbarStyle === ScrollbarStyle::Auto && $this->contentSize->width > $this->viewportSize->width));
    }

    protected function canScrollVertically(): bool
    {
        return $this->scrollDirection === ScrollDirection::Vertical ||
               $this->scrollDirection === ScrollDirection::Both;
    }

    protected function canScrollHorizontally(): bool
    {
        return $this->scrollDirection === ScrollDirection::Horizontal ||
               $this->scrollDirection === ScrollDirection::Both;
    }

    protected function clampScrollOffsets(): void
    {
        $maxScrollX = max(0, $this->contentSize->width - $this->viewportSize->width);
        $maxScrollY = max(0, $this->contentSize->height - $this->viewportSize->height);

        $this->scrollOffsetX = max(0, min($this->scrollOffsetX, $maxScrollX));
        $this->scrollOffsetY = max(0, min($this->scrollOffsetY, $maxScrollY));
    }
}
