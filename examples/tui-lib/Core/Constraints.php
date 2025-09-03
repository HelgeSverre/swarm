<?php

declare(strict_types=1);

namespace Examples\TuiLib\Core;

/**
 * Represents width and height dimensions
 */
readonly class Size
{
    public function __construct(
        public int $width,
        public int $height,
    ) {}

    public function withWidth(int $width): self
    {
        return new self($width, $this->height);
    }

    public function withHeight(int $height): self
    {
        return new self($this->width, $height);
    }

    public function isEmpty(): bool
    {
        return $this->width <= 0 || $this->height <= 0;
    }

    public function contains(Size $other): bool
    {
        return $this->width >= $other->width && $this->height >= $other->height;
    }
}

/**
 * Represents a rectangle with position and size
 */
readonly class Rect
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {}

    public static function fromSize(Size $size, int $x = 0, int $y = 0): self
    {
        return new self($x, $y, $size->width, $size->height);
    }

    public function getSize(): Size
    {
        return new Size($this->width, $this->height);
    }

    public function withPosition(int $x, int $y): self
    {
        return new self($x, $y, $this->width, $this->height);
    }

    public function withSize(Size $size): self
    {
        return new self($this->x, $this->y, $size->width, $size->height);
    }

    public function withX(int $x): self
    {
        return new self($x, $this->y, $this->width, $this->height);
    }

    public function withY(int $y): self
    {
        return new self($this->x, $y, $this->width, $this->height);
    }

    public function withWidth(int $width): self
    {
        return new self($this->x, $this->y, $width, $this->height);
    }

    public function withHeight(int $height): self
    {
        return new self($this->x, $this->y, $this->width, $height);
    }

    public function right(): int
    {
        return $this->x + $this->width;
    }

    public function bottom(): int
    {
        return $this->y + $this->height;
    }

    public function contains(int $x, int $y): bool
    {
        return $x >= $this->x && $x < $this->right() &&
               $y >= $this->y && $y < $this->bottom();
    }

    public function containsRect(Rect $other): bool
    {
        return $this->x <= $other->x &&
               $this->y <= $other->y &&
               $this->right() >= $other->right() &&
               $this->bottom() >= $other->bottom();
    }

    public function intersects(Rect $other): bool
    {
        return $this->x < $other->right() &&
               $this->right() > $other->x &&
               $this->y < $other->bottom() &&
               $this->bottom() > $other->y;
    }

    public function intersection(Rect $other): ?Rect
    {
        if (! $this->intersects($other)) {
            return null;
        }

        $x = max($this->x, $other->x);
        $y = max($this->y, $other->y);
        $right = min($this->right(), $other->right());
        $bottom = min($this->bottom(), $other->bottom());

        return new self($x, $y, $right - $x, $bottom - $y);
    }

    public function isEmpty(): bool
    {
        return $this->width <= 0 || $this->height <= 0;
    }
}

/**
 * Layout constraints for widget sizing
 */
readonly class Constraints
{
    public function __construct(
        public int $minWidth = 0,
        public int $maxWidth = PHP_INT_MAX,
        public int $minHeight = 0,
        public int $maxHeight = PHP_INT_MAX,
    ) {}

    public static function tight(int $width, int $height): self
    {
        return new self($width, $width, $height, $height);
    }

    public static function tightFor(?int $width = null, ?int $height = null): self
    {
        return new self(
            minWidth: $width ?? 0,
            maxWidth: $width ?? PHP_INT_MAX,
            minHeight: $height ?? 0,
            maxHeight: $height ?? PHP_INT_MAX,
        );
    }

    public static function loose(int $maxWidth, int $maxHeight): self
    {
        return new self(0, $maxWidth, 0, $maxHeight);
    }

    public static function expand(): self
    {
        return new self(PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX);
    }

    public function tighten(?int $width = null, ?int $height = null): self
    {
        return new self(
            minWidth: $width ?? $this->minWidth,
            maxWidth: $width ?? $this->maxWidth,
            minHeight: $height ?? $this->minHeight,
            maxHeight: $height ?? $this->maxHeight,
        );
    }

    public function loosen(): self
    {
        return new self(
            minWidth: 0,
            maxWidth: $this->maxWidth,
            minHeight: 0,
            maxHeight: $this->maxHeight,
        );
    }

    public function constrain(Size $size): Size
    {
        return new Size(
            width: max($this->minWidth, min($this->maxWidth, $size->width)),
            height: max($this->minHeight, min($this->maxHeight, $size->height)),
        );
    }

    public function enforce(Size $size): Size
    {
        return $this->constrain($size);
    }

    public function isTight(): bool
    {
        return $this->minWidth == $this->maxWidth && $this->minHeight == $this->maxHeight;
    }

    public function isNormalized(): bool
    {
        return $this->minWidth >= 0 &&
               $this->minHeight >= 0 &&
               $this->minWidth <= $this->maxWidth &&
               $this->minHeight <= $this->maxHeight;
    }

    public function hasBoundedWidth(): bool
    {
        return $this->maxWidth < PHP_INT_MAX;
    }

    public function hasBoundedHeight(): bool
    {
        return $this->maxHeight < PHP_INT_MAX;
    }

    public function hasInfiniteWidth(): bool
    {
        return $this->maxWidth == PHP_INT_MAX;
    }

    public function hasInfiniteHeight(): bool
    {
        return $this->maxHeight == PHP_INT_MAX;
    }

    public function biggest(): Size
    {
        return new Size($this->maxWidth, $this->maxHeight);
    }

    public function smallest(): Size
    {
        return new Size($this->minWidth, $this->minHeight);
    }
}
