<?php

declare(strict_types=1);

namespace MinimalTui\Components;

use MinimalTui\Core\Terminal;

/**
 * Scrollable list component with selection support
 */
class ListWidget
{
    protected array $items = [];

    protected int $selectedIndex = 0;

    protected int $scrollOffset = 0;

    protected bool $focused = false;

    protected int $width = 40;

    protected int $height = 10;

    protected bool $numbered = false;

    protected string $emptyMessage = 'No items';

    public function __construct(array $items = [], bool $numbered = false)
    {
        $this->setItems($items);
        $this->numbered = $numbered;
    }

    /**
     * Set list items
     */
    public function setItems(array $items): self
    {
        $this->items = array_values($items); // Reindex
        $this->selectedIndex = min($this->selectedIndex, count($this->items) - 1);
        $this->selectedIndex = max(0, $this->selectedIndex);
        $this->updateScrollOffset();

        return $this;
    }

    /**
     * Add an item to the list
     */
    public function addItem(mixed $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Remove an item by index
     */
    public function removeItem(int $index): self
    {
        if (isset($this->items[$index])) {
            array_splice($this->items, $index, 1);
            $this->selectedIndex = min($this->selectedIndex, count($this->items) - 1);
            $this->selectedIndex = max(0, $this->selectedIndex);
            $this->updateScrollOffset();
        }

        return $this;
    }

    /**
     * Get all items
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get selected item
     */
    public function getSelectedItem(): mixed
    {
        return $this->items[$this->selectedIndex] ?? null;
    }

    /**
     * Get selected index
     */
    public function getSelectedIndex(): int
    {
        return $this->selectedIndex;
    }

    /**
     * Set selected index
     */
    public function setSelectedIndex(int $index): self
    {
        $this->selectedIndex = max(0, min($index, count($this->items) - 1));
        $this->updateScrollOffset();

        return $this;
    }

    /**
     * Set list size
     */
    public function setSize(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;
        $this->updateScrollOffset();

        return $this;
    }

    /**
     * Set focused state
     */
    public function setFocused(bool $focused): self
    {
        $this->focused = $focused;

        return $this;
    }

    /**
     * Set whether to show numbers
     */
    public function setNumbered(bool $numbered): self
    {
        $this->numbered = $numbered;

        return $this;
    }

    /**
     * Set empty message
     */
    public function setEmptyMessage(string $message): self
    {
        $this->emptyMessage = $message;

        return $this;
    }

    /**
     * Handle keyboard input
     */
    public function handleInput(string $key): mixed
    {
        if (! $this->focused || empty($this->items)) {
            return null;
        }

        switch ($key) {
            case 'UP':
            case 'k':
                if ($this->selectedIndex > 0) {
                    $this->selectedIndex--;
                    $this->updateScrollOffset();
                }
                break;
            case 'DOWN':
            case 'j':
                if ($this->selectedIndex < count($this->items) - 1) {
                    $this->selectedIndex++;
                    $this->updateScrollOffset();
                }
                break;
            case "\n": // Enter
                return $this->getSelectedItem();
            case ' ': // Space - also select
                return $this->getSelectedItem();
            case 'HOME':
            case 'g':
                $this->selectedIndex = 0;
                $this->updateScrollOffset();
                break;
            case 'END':
            case 'G':
                $this->selectedIndex = count($this->items) - 1;
                $this->updateScrollOffset();
                break;
            default:
                // Number keys for quick selection
                if (mb_strlen($key) === 1 && $key >= '1' && $key <= '9') {
                    $index = intval($key) - 1;
                    if ($index < count($this->items)) {
                        $this->selectedIndex = $index;
                        $this->updateScrollOffset();

                        return $this->getSelectedItem();
                    }
                }
                break;
        }

        return null;
    }

    /**
     * Render the list
     */
    public function render(): string
    {
        $lines = [];

        if (empty($this->items)) {
            // Show empty message
            $lines[] = Terminal::DIM . $this->emptyMessage . Terminal::RESET;

            // Pad remaining lines
            for ($i = 1; $i < $this->height; $i++) {
                $lines[] = str_repeat(' ', $this->width);
            }

            return implode("\n", $lines);
        }

        // Render visible items
        $visibleItems = array_slice($this->items, $this->scrollOffset, $this->height);

        foreach ($visibleItems as $i => $item) {
            $actualIndex = $this->scrollOffset + $i;
            $isSelected = $actualIndex === $this->selectedIndex;

            $line = $this->renderItem($item, $actualIndex, $isSelected);
            $lines[] = $line;
        }

        // Pad remaining lines
        while (count($lines) < $this->height) {
            $lines[] = str_repeat(' ', $this->width);
        }

        return implode("\n", $lines);
    }

    /**
     * Get scroll indicators for display
     */
    public function getScrollIndicators(): array
    {
        return [
            'can_scroll_up' => $this->scrollOffset > 0,
            'can_scroll_down' => $this->scrollOffset + $this->height < count($this->items),
            'current_page' => (int) ($this->scrollOffset / $this->height) + 1,
            'total_pages' => (int) ceil(count($this->items) / $this->height),
        ];
    }

    /**
     * Update scroll offset to keep selection visible
     */
    protected function updateScrollOffset(): void
    {
        if (empty($this->items)) {
            $this->scrollOffset = 0;

            return;
        }

        // Keep selected item in view
        if ($this->selectedIndex < $this->scrollOffset) {
            $this->scrollOffset = $this->selectedIndex;
        } elseif ($this->selectedIndex >= $this->scrollOffset + $this->height) {
            $this->scrollOffset = $this->selectedIndex - $this->height + 1;
        }

        // Don't scroll past bounds
        $this->scrollOffset = max(0, $this->scrollOffset);
        $this->scrollOffset = min($this->scrollOffset, max(0, count($this->items) - $this->height));
    }

    /**
     * Render a single item
     */
    protected function renderItem(mixed $item, int $index, bool $selected): string
    {
        $text = $this->formatItem($item, $index);

        // Calculate available width
        $availableWidth = $this->width;
        $indicator = '';

        if ($this->numbered) {
            $number = ($index + 1) . '. ';
            $indicator = $number;
            $availableWidth -= mb_strlen($number);
        }

        if ($selected && $this->focused) {
            $indicator = '▶ ';
            if (! $this->numbered) {
                $availableWidth -= 2;
            }
        } elseif ($selected) {
            $indicator = '> ';
            if (! $this->numbered) {
                $availableWidth -= 2;
            }
        }

        // Truncate text if needed
        $text = Terminal::truncate($text, $availableWidth);

        // Apply styling
        if ($selected) {
            if ($this->focused) {
                return Terminal::REVERSE . $indicator . $text . Terminal::RESET .
                       str_repeat(' ', max(0, $this->width - mb_strlen(Terminal::stripAnsi($indicator . $text))));
            }

            return Terminal::DIM . Terminal::REVERSE . $indicator . $text . Terminal::RESET .
                   str_repeat(' ', max(0, $this->width - mb_strlen(Terminal::stripAnsi($indicator . $text))));
        }
        $line = $indicator . $text;

        return Terminal::pad($line, $this->width);
    }

    /**
     * Format an item for display
     */
    protected function formatItem(mixed $item, int $index): string
    {
        if (is_array($item)) {
            // Try common array keys for display
            return $item['title'] ?? $item['name'] ?? $item['description'] ?? $item['text'] ?? json_encode($item);
        }

        if (is_object($item)) {
            // Try common object properties
            if (method_exists($item, '__toString')) {
                return (string) $item;
            }
            if (property_exists($item, 'title')) {
                return $item->title;
            }
            if (property_exists($item, 'name')) {
                return $item->name;
            }
            if (property_exists($item, 'description')) {
                return $item->description;
            }

            return get_class($item);
        }

        return (string) $item;
    }
}
