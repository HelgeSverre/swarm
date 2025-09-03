<?php

declare(strict_types=1);

namespace Examples\TuiLib\Layout;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;

/**
 * Edge insets for padding and margin
 */
readonly class EdgeInsets
{
    public function __construct(
        public int $top = 0,
        public int $right = 0,
        public int $bottom = 0,
        public int $left = 0,
    ) {}

    public static function all(int $value): self
    {
        return new self($value, $value, $value, $value);
    }

    public static function symmetric(int $vertical = 0, int $horizontal = 0): self
    {
        return new self($vertical, $horizontal, $vertical, $horizontal);
    }

    public static function only(int $top = 0, int $right = 0, int $bottom = 0, int $left = 0): self
    {
        return new self($top, $right, $bottom, $left);
    }

    public function horizontal(): int
    {
        return $this->left + $this->right;
    }

    public function vertical(): int
    {
        return $this->top + $this->bottom;
    }

    public function total(): Size
    {
        return new Size($this->horizontal(), $this->vertical());
    }
}

/**
 * Box decoration for borders and styling
 */
readonly class BoxDecoration
{
    public function __construct(
        public ?string $backgroundColor = null,
        public ?BorderStyle $border = null,
        public ?string $color = null,
    ) {}
}

/**
 * Border styling options
 */
readonly class BorderStyle
{
    public function __construct(
        public int $width = 1,
        public string $color = 'default',
        public BorderType $type = BorderType::Solid,
    ) {}
}

/**
 * Border type enumeration
 */
enum BorderType: string
{
    case None = 'none';
    case Solid = 'solid';
    case Dashed = 'dashed';
    case Dotted = 'dotted';
    case Double = 'double';
    case Rounded = 'rounded';
}

/**
 * Container widget with decoration, padding, and margin support
 */
class Container extends Widget
{
    protected ?Widget $child = null;

    protected ?int $width = null;

    protected ?int $height = null;

    protected EdgeInsets $padding;

    protected EdgeInsets $margin;

    protected ?BoxDecoration $decoration = null;

    public function __construct(
        ?Widget $child = null,
        ?int $width = null,
        ?int $height = null,
        ?EdgeInsets $padding = null,
        ?EdgeInsets $margin = null,
        ?BoxDecoration $decoration = null,
        ?string $id = null
    ) {
        parent::__construct($id);
        $this->child = $child;
        $this->width = $width;
        $this->height = $height;
        $this->padding = $padding ?? new EdgeInsets;
        $this->margin = $margin ?? new EdgeInsets;
        $this->decoration = $decoration;

        if ($child !== null) {
            $this->addChild($child);
        }
    }

    public function setChild(?Widget $child): void
    {
        // Remove existing child
        if ($this->child !== null) {
            $this->removeChild($this->child);
        }

        // Add new child
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
        // Account for margin in available space
        $marginSize = $this->margin->total();
        $availableConstraints = new Constraints(
            minWidth: max(0, $constraints->minWidth - $marginSize->width),
            maxWidth: max(0, $constraints->maxWidth - $marginSize->width),
            minHeight: max(0, $constraints->minHeight - $marginSize->height),
            maxHeight: max(0, $constraints->maxHeight - $marginSize->height),
        );

        // Apply explicit width/height if set
        if ($this->width !== null) {
            $availableConstraints = new Constraints(
                minWidth: $this->width,
                maxWidth: $this->width,
                minHeight: $availableConstraints->minHeight,
                maxHeight: $availableConstraints->maxHeight,
            );
        }

        if ($this->height !== null) {
            $availableConstraints = new Constraints(
                minWidth: $availableConstraints->minWidth,
                maxWidth: $availableConstraints->maxWidth,
                minHeight: $this->height,
                maxHeight: $this->height,
            );
        }

        // Account for padding and border
        $decorationSize = $this->getDecorationSize();
        $paddingSize = $this->padding->total();
        $totalDecorationSize = new Size(
            $decorationSize->width + $paddingSize->width,
            $decorationSize->height + $paddingSize->height
        );

        $childConstraints = new Constraints(
            minWidth: max(0, $availableConstraints->minWidth - $totalDecorationSize->width),
            maxWidth: max(0, $availableConstraints->maxWidth - $totalDecorationSize->width),
            minHeight: max(0, $availableConstraints->minHeight - $totalDecorationSize->height),
            maxHeight: max(0, $availableConstraints->maxHeight - $totalDecorationSize->height),
        );

        // Measure child
        $childSize = $this->child?->measure($childConstraints) ?? new Size(0, 0);

        // Calculate total size including padding, border, and margin
        $containerSize = new Size(
            $childSize->width + $totalDecorationSize->width,
            $childSize->height + $totalDecorationSize->height
        );

        $finalSize = new Size(
            $containerSize->width + $marginSize->width,
            $containerSize->height + $marginSize->height
        );

        return $constraints->constrain($finalSize);
    }

    public function layout(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->clearLayoutFlag();

        if ($this->child === null) {
            return;
        }

        // Account for margin
        $contentBounds = new Rect(
            $bounds->x + $this->margin->left,
            $bounds->y + $this->margin->top,
            max(0, $bounds->width - $this->margin->horizontal()),
            max(0, $bounds->height - $this->margin->vertical())
        );

        // Account for border
        $borderSize = $this->getBorderSize();
        $contentBounds = new Rect(
            $contentBounds->x + $borderSize->width,
            $contentBounds->y + $borderSize->height,
            max(0, $contentBounds->width - $borderSize->width * 2),
            max(0, $contentBounds->height - $borderSize->height * 2)
        );

        // Account for padding
        $childBounds = new Rect(
            $contentBounds->x + $this->padding->left,
            $contentBounds->y + $this->padding->top,
            max(0, $contentBounds->width - $this->padding->horizontal()),
            max(0, $contentBounds->height - $this->padding->vertical())
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

        // Paint background and border
        $output .= $this->paintDecoration($context);

        // Paint child
        if ($this->child !== null && $this->child->isVisible()) {
            $output .= $this->child->paint($context);
        }

        return $output;
    }

    // Getters and setters
    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): void
    {
        if ($this->width !== $width) {
            $this->width = $width;
            $this->markNeedsLayout();
        }
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): void
    {
        if ($this->height !== $height) {
            $this->height = $height;
            $this->markNeedsLayout();
        }
    }

    public function getPadding(): EdgeInsets
    {
        return $this->padding;
    }

    public function setPadding(EdgeInsets $padding): void
    {
        $this->padding = $padding;
        $this->markNeedsLayout();
    }

    public function getMargin(): EdgeInsets
    {
        return $this->margin;
    }

    public function setMargin(EdgeInsets $margin): void
    {
        $this->margin = $margin;
        $this->markNeedsLayout();
    }

    public function getDecoration(): ?BoxDecoration
    {
        return $this->decoration;
    }

    public function setDecoration(?BoxDecoration $decoration): void
    {
        $this->decoration = $decoration;
        $this->markNeedsRepaint();
    }

    protected function paintDecoration(BuildContext $context): string
    {
        if ($this->decoration === null || $this->bounds === null) {
            return '';
        }

        $output = '';

        // Account for margin to get the actual container bounds
        $containerBounds = new Rect(
            $this->bounds->x + $this->margin->left,
            $this->bounds->y + $this->margin->top,
            max(0, $this->bounds->width - $this->margin->horizontal()),
            max(0, $this->bounds->height - $this->margin->vertical())
        );

        // Paint background
        if ($this->decoration->backgroundColor !== null) {
            $output .= $this->paintBackground($containerBounds, $this->decoration->backgroundColor);
        }

        // Paint border
        if ($this->decoration->border !== null) {
            $output .= $this->paintBorder($containerBounds, $this->decoration->border);
        }

        return $output;
    }

    protected function paintBackground(Rect $bounds, string $color): string
    {
        $output = '';
        $bgChar = ' ';

        // Apply background color escape sequence if available
        $colorCode = $this->getColorCode($color);
        if ($colorCode !== null) {
            $bgChar = "\033[{$colorCode}m \033[0m";
        }

        for ($y = $bounds->y; $y < $bounds->y + $bounds->height; $y++) {
            $output .= "\033[{$y};{$bounds->x}H"; // Position cursor
            $output .= str_repeat($bgChar, $bounds->width);
        }

        return $output;
    }

    protected function paintBorder(Rect $bounds, BorderStyle $border): string
    {
        if ($border->type === BorderType::None || $border->width === 0) {
            return '';
        }

        $output = '';
        $chars = $this->getBorderChars($border->type);
        $colorCode = $this->getColorCode($border->color);

        $colorStart = $colorCode !== null ? "\033[{$colorCode}m" : '';
        $colorEnd = $colorCode !== null ? "\033[0m" : '';

        // Top border
        if ($bounds->height > 0) {
            $output .= "\033[{$bounds->y};{$bounds->x}H"; // Position cursor
            $output .= $colorStart . $chars['top_left'] .
                      str_repeat($chars['horizontal'], max(0, $bounds->width - 2)) .
                      $chars['top_right'] . $colorEnd;
        }

        // Side borders
        for ($y = $bounds->y + 1; $y < $bounds->y + $bounds->height - 1; $y++) {
            // Left border
            $output .= "\033[{$y};{$bounds->x}H";
            $output .= $colorStart . $chars['vertical'] . $colorEnd;

            // Right border
            if ($bounds->width > 1) {
                $output .= "\033[{$y};" . ($bounds->x + $bounds->width - 1) . 'H';
                $output .= $colorStart . $chars['vertical'] . $colorEnd;
            }
        }

        // Bottom border
        if ($bounds->height > 1) {
            $bottomY = $bounds->y + $bounds->height - 1;
            $output .= "\033[{$bottomY};{$bounds->x}H";
            $output .= $colorStart . $chars['bottom_left'] .
                      str_repeat($chars['horizontal'], max(0, $bounds->width - 2)) .
                      $chars['bottom_right'] . $colorEnd;
        }

        return $output;
    }

    protected function getBorderChars(BorderType $type): array
    {
        return match ($type) {
            BorderType::Solid => [
                'horizontal' => '─',
                'vertical' => '│',
                'top_left' => '┌',
                'top_right' => '┐',
                'bottom_left' => '└',
                'bottom_right' => '┘',
            ],
            BorderType::Double => [
                'horizontal' => '═',
                'vertical' => '║',
                'top_left' => '╔',
                'top_right' => '╗',
                'bottom_left' => '╚',
                'bottom_right' => '╝',
            ],
            BorderType::Rounded => [
                'horizontal' => '─',
                'vertical' => '│',
                'top_left' => '╭',
                'top_right' => '╮',
                'bottom_left' => '╰',
                'bottom_right' => '╯',
            ],
            BorderType::Dashed => [
                'horizontal' => '┄',
                'vertical' => '┊',
                'top_left' => '┌',
                'top_right' => '┐',
                'bottom_left' => '└',
                'bottom_right' => '┘',
            ],
            BorderType::Dotted => [
                'horizontal' => '┈',
                'vertical' => '┋',
                'top_left' => '┌',
                'top_right' => '┐',
                'bottom_left' => '└',
                'bottom_right' => '┘',
            ],
            default => [
                'horizontal' => ' ',
                'vertical' => ' ',
                'top_left' => ' ',
                'top_right' => ' ',
                'bottom_left' => ' ',
                'bottom_right' => ' ',
            ],
        };
    }

    protected function getColorCode(string $color): ?string
    {
        // Basic color mapping - can be extended
        return match (mb_strtolower($color)) {
            'black' => '30',
            'red' => '31',
            'green' => '32',
            'yellow' => '33',
            'blue' => '34',
            'magenta' => '35',
            'cyan' => '36',
            'white' => '37',
            'bright_black', 'gray' => '90',
            'bright_red' => '91',
            'bright_green' => '92',
            'bright_yellow' => '93',
            'bright_blue' => '94',
            'bright_magenta' => '95',
            'bright_cyan' => '96',
            'bright_white' => '97',
            default => null,
        };
    }

    protected function getDecorationSize(): Size
    {
        $borderSize = $this->getBorderSize();

        return new Size($borderSize->width * 2, $borderSize->height * 2);
    }

    protected function getBorderSize(): Size
    {
        if ($this->decoration?->border === null || $this->decoration->border->type === BorderType::None) {
            return new Size(0, 0);
        }

        return new Size($this->decoration->border->width, $this->decoration->border->width);
    }
}
