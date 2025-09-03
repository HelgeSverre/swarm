<?php

declare(strict_types=1);

namespace Examples\TuiLib\Core;

/**
 * Represents a character cell with content and styling
 */
readonly class Cell
{
    public function __construct(
        public string $char = ' ',
        public string $style = '',
    ) {}

    public function withChar(string $char): self
    {
        return new self($char, $this->style);
    }

    public function withStyle(string $style): self
    {
        return new self($this->char, $style);
    }

    public function isEmpty(): bool
    {
        return $this->char === ' ' && $this->style === '';
    }
}

/**
 * Represents the saved state of a canvas for save/restore operations
 */
readonly class CanvasState
{
    public function __construct(
        public ?Rect $clipRect,
        public string $defaultStyle = '',
    ) {}
}

/**
 * Drawing canvas abstraction for terminal UI rendering
 *
 * Provides a buffer-based drawing surface with support for:
 * - Text rendering with ANSI color codes
 * - Box and line drawing
 * - Clipping regions for nested rendering
 * - Save/restore state for hierarchical rendering
 */
class Canvas
{
    protected array $buffer = [];

    protected ?Rect $clipRect = null;

    protected string $defaultStyle = '';

    protected array $stateStack = [];

    public function __construct(
        protected readonly Size $size,
    ) {
        $this->clear();
    }

    /**
     * Clear the entire canvas with the specified character and style
     */
    public function clear(string $char = ' ', string $style = ''): void
    {
        $cell = new Cell($char, $style);
        $this->buffer = [];
        for ($y = 0; $y < $this->size->height; $y++) {
            for ($x = 0; $x < $this->size->width; $x++) {
                $this->buffer[$y][$x] = $cell;
            }
        }
    }

    /**
     * Draw text at the specified position
     */
    public function drawText(int $x, int $y, string $text, string $style = ''): void
    {
        $chars = mb_str_split($text);
        $currentX = $x;

        foreach ($chars as $char) {
            if ($this->isWithinBounds($currentX, $y)) {
                $this->setCell($currentX, $y, new Cell($char, $style ?: $this->defaultStyle));
            }
            $currentX++;
        }
    }

    /**
     * Draw a box with optional border characters
     */
    public function drawBox(Rect $rect, string $style = '', ?array $borderChars = null): void
    {
        $borderChars = $borderChars ?? [
            'top' => '─',
            'bottom' => '─',
            'left' => '│',
            'right' => '│',
            'topLeft' => '┌',
            'topRight' => '┐',
            'bottomLeft' => '└',
            'bottomRight' => '┘',
        ];

        $finalStyle = $style ?: $this->defaultStyle;

        // Draw corners
        $this->setCell($rect->x, $rect->y, new Cell($borderChars['topLeft'], $finalStyle));
        $this->setCell($rect->x + $rect->width - 1, $rect->y, new Cell($borderChars['topRight'], $finalStyle));
        $this->setCell($rect->x, $rect->y + $rect->height - 1, new Cell($borderChars['bottomLeft'], $finalStyle));
        $this->setCell($rect->x + $rect->width - 1, $rect->y + $rect->height - 1, new Cell($borderChars['bottomRight'], $finalStyle));

        // Draw horizontal borders
        for ($x = $rect->x + 1; $x < $rect->x + $rect->width - 1; $x++) {
            $this->setCell($x, $rect->y, new Cell($borderChars['top'], $finalStyle));
            $this->setCell($x, $rect->y + $rect->height - 1, new Cell($borderChars['bottom'], $finalStyle));
        }

        // Draw vertical borders
        for ($y = $rect->y + 1; $y < $rect->y + $rect->height - 1; $y++) {
            $this->setCell($rect->x, $y, new Cell($borderChars['left'], $finalStyle));
            $this->setCell($rect->x + $rect->width - 1, $y, new Cell($borderChars['right'], $finalStyle));
        }
    }

    /**
     * Draw a horizontal line
     */
    public function drawHorizontalLine(int $x, int $y, int $length, string $char = '─', string $style = ''): void
    {
        $finalStyle = $style ?: $this->defaultStyle;
        for ($i = 0; $i < $length; $i++) {
            $this->setCell($x + $i, $y, new Cell($char, $finalStyle));
        }
    }

    /**
     * Draw a vertical line
     */
    public function drawVerticalLine(int $x, int $y, int $length, string $char = '│', string $style = ''): void
    {
        $finalStyle = $style ?: $this->defaultStyle;
        for ($i = 0; $i < $length; $i++) {
            $this->setCell($x, $y + $i, new Cell($char, $finalStyle));
        }
    }

    /**
     * Fill a rectangle with a character
     */
    public function fillRect(Rect $rect, string $char = ' ', string $style = ''): void
    {
        $finalStyle = $style ?: $this->defaultStyle;
        for ($y = $rect->y; $y < $rect->y + $rect->height; $y++) {
            for ($x = $rect->x; $x < $rect->x + $rect->width; $x++) {
                $this->setCell($x, $y, new Cell($char, $finalStyle));
            }
        }
    }

    /**
     * Set clipping rectangle for subsequent drawing operations
     */
    public function clip(Rect $rect): void
    {
        $this->clipRect = $this->clipRect?->intersection($rect) ?? $rect;
    }

    /**
     * Clear the clipping rectangle
     */
    public function clearClip(): void
    {
        $this->clipRect = null;
    }

    /**
     * Save the current canvas state
     */
    public function save(): void
    {
        $this->stateStack[] = new CanvasState($this->clipRect, $this->defaultStyle);
    }

    /**
     * Restore the last saved canvas state
     */
    public function restore(): void
    {
        if (empty($this->stateStack)) {
            return;
        }

        $state = array_pop($this->stateStack);
        $this->clipRect = $state->clipRect;
        $this->defaultStyle = $state->defaultStyle;
    }

    /**
     * Set the default style for subsequent drawing operations
     */
    public function setDefaultStyle(string $style): void
    {
        $this->defaultStyle = $style;
    }

    /**
     * Get the current default style
     */
    public function getDefaultStyle(): string
    {
        return $this->defaultStyle;
    }

    /**
     * Get the current clipping rectangle
     */
    public function getClipRect(): ?Rect
    {
        return $this->clipRect;
    }

    /**
     * Get the canvas size
     */
    public function getSize(): Size
    {
        return $this->size;
    }

    /**
     * Get a cell at the specified position
     */
    public function getCell(int $x, int $y): ?Cell
    {
        if (! $this->isWithinBounds($x, $y)) {
            return null;
        }

        return $this->buffer[$y][$x];
    }

    /**
     * Render the canvas to a string representation
     */
    public function render(): string
    {
        $output = '';
        $lastStyle = '';

        for ($y = 0; $y < $this->size->height; $y++) {
            for ($x = 0; $x < $this->size->width; $x++) {
                $cell = $this->buffer[$y][$x];

                // Only output style changes when needed
                if ($cell->style !== $lastStyle) {
                    if ($lastStyle !== '') {
                        $output .= "\033[0m"; // Reset previous style
                    }
                    if ($cell->style !== '') {
                        $output .= $cell->style;
                    }
                    $lastStyle = $cell->style;
                }

                $output .= $cell->char;
            }

            // Don't add newline after the last row
            if ($y < $this->size->height - 1) {
                $output .= "\n";
            }
        }

        // Reset styles at the end
        if ($lastStyle !== '') {
            $output .= "\033[0m";
        }

        return $output;
    }

    /**
     * Get memory usage of the canvas buffer
     */
    public function getMemoryUsage(): int
    {
        $totalMemory = 0;

        for ($y = 0; $y < $this->size->height; $y++) {
            for ($x = 0; $x < $this->size->width; $x++) {
                $cell = $this->buffer[$y][$x];
                // Approximate memory usage per cell (char + style strings)
                $totalMemory += mb_strlen($cell->char) + mb_strlen($cell->style) + 16; // Object overhead
            }
        }

        return $totalMemory;
    }

    /**
     * Set a cell at the specified position (internal method)
     */
    protected function setCell(int $x, int $y, Cell $cell): void
    {
        if (! $this->isWithinBounds($x, $y)) {
            return;
        }

        if ($this->clipRect && ! $this->clipRect->contains($x, $y)) {
            return;
        }

        $this->buffer[$y][$x] = $cell;
    }

    /**
     * Check if coordinates are within canvas bounds
     */
    protected function isWithinBounds(int $x, int $y): bool
    {
        return $x >= 0 && $x < $this->size->width &&
               $y >= 0 && $y < $this->size->height;
    }
}
