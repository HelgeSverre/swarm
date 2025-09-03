<?php

declare(strict_types=1);

namespace Examples\TuiLib\Core;

/**
 * Context information passed during widget building phase
 */
readonly class BuildContext
{
    public function __construct(
        public Size $terminalSize,
        public ?Widget $parent = null,
        public array $theme = [],
        public int $depth = 0,
        public bool $hasFocus = false,
        public ?Canvas $canvas = null,
    ) {}

    public function withParent(Widget $parent): self
    {
        return new self(
            terminalSize: $this->terminalSize,
            parent: $parent,
            theme: $this->theme,
            depth: $this->depth + 1,
            hasFocus: $this->hasFocus,
            canvas: $this->canvas,
        );
    }

    public function withFocus(bool $hasFocus): self
    {
        return new self(
            terminalSize: $this->terminalSize,
            parent: $this->parent,
            theme: $this->theme,
            depth: $this->depth,
            hasFocus: $hasFocus,
            canvas: $this->canvas,
        );
    }

    public function withTheme(array $theme): self
    {
        return new self(
            terminalSize: $this->terminalSize,
            parent: $this->parent,
            theme: array_merge($this->theme, $theme),
            depth: $this->depth,
            hasFocus: $this->hasFocus,
            canvas: $this->canvas,
        );
    }

    public function withCanvas(Canvas $canvas): self
    {
        return new self(
            terminalSize: $this->terminalSize,
            parent: $this->parent,
            theme: $this->theme,
            depth: $this->depth,
            hasFocus: $this->hasFocus,
            canvas: $canvas,
        );
    }

    public function getCanvas(): ?Canvas
    {
        return $this->canvas;
    }

    public function getThemeValue(string $key, mixed $default = null): mixed
    {
        return $this->theme[$key] ?? $default;
    }

    public function hasThemeValue(string $key): bool
    {
        return array_key_exists($key, $this->theme);
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    public function getMaxAvailableSize(): Size
    {
        return $this->terminalSize;
    }

    public function createConstraints(): Constraints
    {
        return Constraints::loose($this->terminalSize->width, $this->terminalSize->height);
    }
}
