<?php

declare(strict_types=1);

namespace Examples\TuiLib\Widgets;

use Closure;
use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;
use Examples\TuiLib\Focus\FocusNode;

/**
 * List view widget with item rendering, selection and keyboard navigation
 */
class ListView extends Widget
{
    protected array $items = [];

    protected int $selectedIndex = -1;

    protected int $scrollOffset = 0;

    protected ?Closure $itemBuilder = null;

    protected ?FocusNode $focusNode = null;

    public function __construct(
        array $items = [],
        ?callable $itemBuilder = null,
        protected readonly bool $showScrollbar = true,
        protected readonly ?string $selectionColor = 'blue',
        protected readonly ?string $textColor = null,
        protected readonly ?string $borderColor = null,
        protected readonly ?string $scrollbarColor = 'gray',
        ?string $id = null
    ) {
        parent::__construct($id);

        $this->items = array_values($items); // Ensure numeric indexing
        $this->itemBuilder = $itemBuilder ?? $this->defaultItemBuilder(...);
        $this->focusable = true;
        $this->focusNode = new FocusNode($this->getId());

        if (! empty($this->items)) {
            $this->selectedIndex = 0;
        }
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        // List view can expand to fill available space
        return new Size(
            min($constraints->maxWidth, 20), // Default minimum width
            min($constraints->maxHeight, count($this->items) + 2) // Items + border
        );
    }

    public function layout(Rect $bounds): void
    {
        $this->setBounds($bounds);
    }

    public function paint(BuildContext $context): string
    {
        if ($this->bounds === null || $this->bounds->isEmpty()) {
            return '';
        }

        $output = [];
        $hasFocus = $context->hasFocus;

        // Calculate visible area
        $visibleHeight = $this->bounds->height;
        $visibleItems = $this->getVisibleItems($visibleHeight);

        // Adjust scroll offset to keep selected item visible
        $this->adjustScrollOffset($visibleHeight);

        // Paint items
        for ($i = 0; $i < $visibleHeight; $i++) {
            $itemIndex = $this->scrollOffset + $i;
            $y = $this->bounds->y + $i;

            if ($itemIndex < count($this->items)) {
                $item = $this->items[$itemIndex];
                $isSelected = $itemIndex === $this->selectedIndex && $hasFocus;
                $itemText = $this->renderItem($item, $itemIndex, $isSelected);

                $output[] = "\033[{$y};{$this->bounds->x}H" . $itemText;
            } else {
                // Clear empty lines
                $output[] = "\033[{$y};{$this->bounds->x}H" . str_repeat(' ', $this->bounds->width);
            }
        }

        // Paint scrollbar if needed
        if ($this->showScrollbar && $this->needsScrollbar()) {
            $output[] = $this->paintScrollbar($hasFocus);
        }

        return implode('', $output);
    }

    public function handleKeyEvent(string $key): bool
    {
        if (! $this->focusNode?->hasFocus() || empty($this->items)) {
            return false;
        }

        $handled = true;

        match ($key) {
            'Up', "\033[A" => $this->moveSelectionUp(),
            'Down', "\033[B" => $this->moveSelectionDown(),
            'Home', "\033[H" => $this->moveSelectionToFirst(),
            'End', "\033[F" => $this->moveSelectionToLast(),
            'PageUp', "\033[5~" => $this->pageUp(),
            'PageDown', "\033[6~" => $this->pageDown(),
            default => $handled = false,
        };

        if ($handled) {
            $this->markNeedsRepaint();
        }

        return $handled;
    }

    // Public API methods
    public function addItem(mixed $item): void
    {
        $this->items[] = $item;

        // Select first item if this is the first one
        if (count($this->items) === 1) {
            $this->selectedIndex = 0;
        }

        $this->markNeedsRepaint();
    }

    public function removeItem(int $index): void
    {
        if ($index >= 0 && $index < count($this->items)) {
            array_splice($this->items, $index, 1);

            // Adjust selection
            if ($this->selectedIndex >= count($this->items)) {
                $this->selectedIndex = max(-1, count($this->items) - 1);
            }

            $this->markNeedsRepaint();
        }
    }

    public function setItems(array $items): void
    {
        $this->items = array_values($items);
        $this->selectedIndex = empty($this->items) ? -1 : 0;
        $this->scrollOffset = 0;
        $this->markNeedsRepaint();
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getSelectedIndex(): int
    {
        return $this->selectedIndex;
    }

    public function setSelectedIndex(int $index): void
    {
        if ($index >= 0 && $index < count($this->items)) {
            $this->selectedIndex = $index;
            $this->markNeedsRepaint();
        }
    }

    public function getSelectedItem(): mixed
    {
        return $this->selectedIndex >= 0 ? $this->items[$this->selectedIndex] : null;
    }

    public function setItemBuilder(callable $builder): void
    {
        $this->itemBuilder = $builder;
        $this->markNeedsRepaint();
    }

    public function getFocusNode(): ?FocusNode
    {
        return $this->focusNode;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function getItemCount(): int
    {
        return count($this->items);
    }

    public function clear(): void
    {
        $this->items = [];
        $this->selectedIndex = -1;
        $this->scrollOffset = 0;
        $this->markNeedsRepaint();
    }

    // Convenience factory methods
    public static function withStringItems(array $items): self
    {
        return new self($items);
    }

    public static function withCustomBuilder(array $items, callable $itemBuilder): self
    {
        return new self($items, $itemBuilder);
    }

    protected function defaultItemBuilder(mixed $item, int $index, bool $isSelected): string
    {
        $text = is_string($item) ? $item : (string) $item;

        // Truncate to fit width (leave space for scrollbar if needed)
        $maxWidth = $this->bounds->width - ($this->needsScrollbar() && $this->showScrollbar ? 1 : 0);

        if (mb_strlen($text) > $maxWidth) {
            $text = mb_substr($text, 0, $maxWidth - 3) . '...';
        } else {
            $text = mb_str_pad($text, $maxWidth, ' ');
        }

        return $text;
    }

    protected function renderItem(mixed $item, int $index, bool $isSelected): string
    {
        $text = ($this->itemBuilder)($item, $index, $isSelected);

        // Apply selection styling
        if ($isSelected) {
            $selectionBg = $this->getBackgroundColorCode($this->selectionColor ?? 'blue');
            $text = $selectionBg . $text . "\033[0m";
        } elseif ($this->textColor !== null) {
            $textFg = $this->getColorCode($this->textColor);
            $text = $textFg . $text . "\033[0m";
        }

        return $text;
    }

    protected function getVisibleItems(int $visibleHeight): array
    {
        $start = $this->scrollOffset;
        $end = min($start + $visibleHeight, count($this->items));

        return array_slice($this->items, $start, $end - $start);
    }

    protected function adjustScrollOffset(int $visibleHeight): void
    {
        if ($this->selectedIndex < 0 || empty($this->items)) {
            return;
        }

        // If selected item is above visible area, scroll up
        if ($this->selectedIndex < $this->scrollOffset) {
            $this->scrollOffset = $this->selectedIndex;
        }

        // If selected item is below visible area, scroll down
        if ($this->selectedIndex >= $this->scrollOffset + $visibleHeight) {
            $this->scrollOffset = $this->selectedIndex - $visibleHeight + 1;
        }

        // Ensure we don't scroll past the end
        $maxOffset = max(0, count($this->items) - $visibleHeight);
        $this->scrollOffset = min($this->scrollOffset, $maxOffset);

        // Ensure we don't scroll past the beginning
        $this->scrollOffset = max(0, $this->scrollOffset);
    }

    protected function needsScrollbar(): bool
    {
        return count($this->items) > $this->bounds->height;
    }

    protected function paintScrollbar(bool $hasFocus): string
    {
        if (! $this->needsScrollbar() || $this->bounds->width < 2) {
            return '';
        }

        $output = [];
        $scrollbarX = $this->bounds->x + $this->bounds->width - 1;
        $scrollbarHeight = $this->bounds->height;
        $totalItems = count($this->items);

        // Calculate scrollbar thumb position and size
        $thumbSize = max(1, intval($scrollbarHeight * $scrollbarHeight / $totalItems));
        $thumbPosition = intval($this->scrollOffset * ($scrollbarHeight - $thumbSize) / max(1, $totalItems - $scrollbarHeight));

        $scrollbarFg = $this->getColorCode($this->scrollbarColor ?? 'gray');
        $resetCode = "\033[0m";

        for ($i = 0; $i < $scrollbarHeight; $i++) {
            $y = $this->bounds->y + $i;
            $char = ($i >= $thumbPosition && $i < $thumbPosition + $thumbSize) ? '█' : '│';

            $output[] = "\033[{$y};{$scrollbarX}H{$scrollbarFg}{$char}{$resetCode}";
        }

        return implode('', $output);
    }

    protected function moveSelectionUp(): void
    {
        if ($this->selectedIndex > 0) {
            $this->selectedIndex--;
        }
    }

    protected function moveSelectionDown(): void
    {
        if ($this->selectedIndex < count($this->items) - 1) {
            $this->selectedIndex++;
        }
    }

    protected function moveSelectionToFirst(): void
    {
        if (! empty($this->items)) {
            $this->selectedIndex = 0;
        }
    }

    protected function moveSelectionToLast(): void
    {
        if (! empty($this->items)) {
            $this->selectedIndex = count($this->items) - 1;
        }
    }

    protected function pageUp(): void
    {
        $pageSize = $this->bounds->height;
        $this->selectedIndex = max(0, $this->selectedIndex - $pageSize);
    }

    protected function pageDown(): void
    {
        $pageSize = $this->bounds->height;
        $this->selectedIndex = min(count($this->items) - 1, $this->selectedIndex + $pageSize);
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

    protected function getBackgroundColorCode(string $color): string
    {
        return match (mb_strtolower($color)) {
            'black' => "\033[40m",
            'red' => "\033[41m",
            'green' => "\033[42m",
            'yellow' => "\033[43m",
            'blue' => "\033[44m",
            'magenta' => "\033[45m",
            'cyan' => "\033[46m",
            'white' => "\033[47m",
            'bright_black', 'gray' => "\033[100m",
            'bright_red' => "\033[101m",
            'bright_green' => "\033[102m",
            'bright_yellow' => "\033[103m",
            'bright_blue' => "\033[104m",
            'bright_magenta' => "\033[105m",
            'bright_cyan' => "\033[106m",
            'bright_white' => "\033[107m",
            default => '',
        };
    }
}
