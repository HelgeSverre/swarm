<?php

declare(strict_types=1);

namespace Examples\TuiLib\Widgets;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;

/**
 * Box widget with borders, title and padding support
 */
class Box extends Widget
{
    public function __construct(
        protected readonly ?Widget $child = null,
        protected readonly string $borderStyle = 'single', // single, double, rounded, none
        protected readonly ?string $title = null,
        protected readonly string $titleAlignment = 'left', // left, center, right
        protected readonly int $paddingTop = 0,
        protected readonly int $paddingRight = 0,
        protected readonly int $paddingBottom = 0,
        protected readonly int $paddingLeft = 0,
        protected readonly ?string $borderColor = null,
        protected readonly ?string $titleColor = null,
        ?string $id = null
    ) {
        parent::__construct($id);

        if ($this->child !== null) {
            $this->addChild($this->child);
        }
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        $borderWidth = $this->getBorderWidth();
        $totalPaddingWidth = $this->paddingLeft + $this->paddingRight;
        $totalPaddingHeight = $this->paddingTop + $this->paddingBottom;

        $innerWidth = max(0, $constraints->maxWidth - (2 * $borderWidth) - $totalPaddingWidth);
        $innerHeight = max(0, $constraints->maxHeight - (2 * $borderWidth) - $totalPaddingHeight);

        $childSize = new Size(0, 0);
        if ($this->child !== null) {
            $childConstraints = new Constraints(0, $innerWidth, 0, $innerHeight);
            $childSize = $this->child->measure($childConstraints);
        }

        $contentWidth = $childSize->width + $totalPaddingWidth + (2 * $borderWidth);
        $contentHeight = $childSize->height + $totalPaddingHeight + (2 * $borderWidth);

        // Consider title width
        if ($this->title !== null) {
            $titleWidth = mb_strlen($this->title) + 2; // Add space around title
            $contentWidth = max($contentWidth, $titleWidth + (2 * $borderWidth));
        }

        return new Size(
            min($constraints->maxWidth, $contentWidth),
            min($constraints->maxHeight, $contentHeight)
        );
    }

    public function layout(Rect $bounds): void
    {
        $this->setBounds($bounds);

        if ($this->child !== null) {
            $borderWidth = $this->getBorderWidth();
            $innerX = $bounds->x + $borderWidth + $this->paddingLeft;
            $innerY = $bounds->y + $borderWidth + $this->paddingTop;
            $innerWidth = max(0, $bounds->width - (2 * $borderWidth) - $this->paddingLeft - $this->paddingRight);
            $innerHeight = max(0, $bounds->height - (2 * $borderWidth) - $this->paddingTop - $this->paddingBottom);

            $childBounds = new Rect($innerX, $innerY, $innerWidth, $innerHeight);
            $this->child->layout($childBounds);
        }
    }

    public function paint(BuildContext $context): string
    {
        if ($this->bounds === null || $this->bounds->isEmpty()) {
            return '';
        }

        $output = [];

        // Paint border
        if ($this->borderStyle !== 'none') {
            $output[] = $this->paintBorder();
        }

        // Paint title
        if ($this->title !== null && $this->borderStyle !== 'none') {
            $output[] = $this->paintTitle();
        }

        // Paint child
        if ($this->child !== null) {
            $output[] = $this->child->paint($context);
        }

        return implode('', $output);
    }

    // Convenience constructor methods
    public static function withPadding(
        Widget $child,
        int $padding,
        string $borderStyle = 'single',
        ?string $title = null
    ): self {
        return new self(
            child: $child,
            borderStyle: $borderStyle,
            title: $title,
            paddingTop: $padding,
            paddingRight: $padding,
            paddingBottom: $padding,
            paddingLeft: $padding
        );
    }

    public static function withTitle(
        Widget $child,
        string $title,
        string $borderStyle = 'single',
        string $titleAlignment = 'left'
    ): self {
        return new self(
            child: $child,
            borderStyle: $borderStyle,
            title: $title,
            titleAlignment: $titleAlignment
        );
    }

    // Getters
    public function getChild(): ?Widget
    {
        return $this->child;
    }

    public function getBorderStyle(): string
    {
        return $this->borderStyle;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getTitleAlignment(): string
    {
        return $this->titleAlignment;
    }

    public function getPaddingTop(): int
    {
        return $this->paddingTop;
    }

    public function getPaddingRight(): int
    {
        return $this->paddingRight;
    }

    public function getPaddingBottom(): int
    {
        return $this->paddingBottom;
    }

    public function getPaddingLeft(): int
    {
        return $this->paddingLeft;
    }

    protected function paintBorder(): string
    {
        $chars = $this->getBorderChars();
        $output = [];

        $x = $this->bounds->x;
        $y = $this->bounds->y;
        $width = $this->bounds->width;
        $height = $this->bounds->height;

        $colorCode = $this->borderColor !== null ? $this->getColorCode($this->borderColor) : '';
        $resetCode = $this->borderColor !== null ? "\033[0m" : '';

        // Top border
        $topLine = $chars['top_left'] . str_repeat($chars['horizontal'], $width - 2) . $chars['top_right'];
        $output[] = "\033[{$y}H\033[{$x}G{$colorCode}{$topLine}{$resetCode}";

        // Side borders
        for ($i = 1; $i < $height - 1; $i++) {
            $lineY = $y + $i;
            $output[] = "\033[{$lineY}H\033[{$x}G{$colorCode}{$chars['vertical']}{$resetCode}";
            $output[] = "\033[{$lineY}H\033[" . ($x + $width - 1) . "G{$colorCode}{$chars['vertical']}{$resetCode}";
        }

        // Bottom border
        if ($height > 1) {
            $bottomY = $y + $height - 1;
            $bottomLine = $chars['bottom_left'] . str_repeat($chars['horizontal'], $width - 2) . $chars['bottom_right'];
            $output[] = "\033[{$bottomY}H\033[{$x}G{$colorCode}{$bottomLine}{$resetCode}";
        }

        return implode('', $output);
    }

    protected function paintTitle(): string
    {
        if ($this->title === null || $this->bounds->width <= 4) {
            return '';
        }

        $titleText = ' ' . $this->title . ' ';
        $maxTitleWidth = $this->bounds->width - 4; // Leave space for corners and borders

        if (mb_strlen($titleText) > $maxTitleWidth) {
            $titleText = mb_substr($titleText, 0, $maxTitleWidth - 3) . '...';
        }

        $titleX = match ($this->titleAlignment) {
            'center' => $this->bounds->x + intval(($this->bounds->width - mb_strlen($titleText)) / 2),
            'right' => $this->bounds->x + $this->bounds->width - mb_strlen($titleText) - 1,
            default => $this->bounds->x + 2,
        };

        $colorCode = $this->titleColor !== null ? $this->getColorCode($this->titleColor) : '';
        $resetCode = $this->titleColor !== null ? "\033[0m" : '';

        return "\033[{$this->bounds->y}H\033[{$titleX}G{$colorCode}{$titleText}{$resetCode}";
    }

    protected function getBorderChars(): array
    {
        return match ($this->borderStyle) {
            'double' => [
                'horizontal' => '═',
                'vertical' => '║',
                'top_left' => '╔',
                'top_right' => '╗',
                'bottom_left' => '╚',
                'bottom_right' => '╝',
            ],
            'rounded' => [
                'horizontal' => '─',
                'vertical' => '│',
                'top_left' => '╭',
                'top_right' => '╮',
                'bottom_left' => '╰',
                'bottom_right' => '╯',
            ],
            default => [ // single
                'horizontal' => '─',
                'vertical' => '│',
                'top_left' => '┌',
                'top_right' => '┐',
                'bottom_left' => '└',
                'bottom_right' => '┘',
            ],
        };
    }

    protected function getBorderWidth(): int
    {
        return $this->borderStyle === 'none' ? 0 : 1;
    }

    protected function getColorCode(string $color): string
    {
        return match (mb_strtolower($color)) {
            'black' => "\033[30m",
            'red' => "\033[31m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'blue' => "\033[34m",
            'magenta' => "\033[35m",
            'cyan' => "\033[36m",
            'white' => "\033[37m",
            'bright_black', 'gray' => "\033[90m",
            'bright_red' => "\033[91m",
            'bright_green' => "\033[92m",
            'bright_yellow' => "\033[93m",
            'bright_blue' => "\033[94m",
            'bright_magenta' => "\033[95m",
            'bright_cyan' => "\033[96m",
            'bright_white' => "\033[97m",
            default => '',
        };
    }
}
