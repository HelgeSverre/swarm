<?php

declare(strict_types=1);

namespace MinimalTui\Components;

use MinimalTui\Core\Terminal;

/**
 * Text input field with cursor, scrolling, and selection support
 */
class InputField
{
    protected string $placeholder;

    protected string $value = '';

    protected int $cursorPos = 0;

    protected int $scrollOffset = 0;

    protected bool $focused = false;

    protected int $width = 50;

    protected int $height = 1;

    protected ?int $maxLength = null;

    protected bool $password = false;

    public function __construct(string $placeholder = '', ?int $maxLength = null, bool $password = false)
    {
        $this->placeholder = $placeholder;
        $this->maxLength = $maxLength;
        $this->password = $password;
    }

    /**
     * Set field size
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
     * Set the current value
     */
    public function setValue(string $value): self
    {
        $this->value = $value;
        $this->cursorPos = min($this->cursorPos, mb_strlen($this->value));
        $this->updateScrollOffset();

        return $this;
    }

    /**
     * Get the current value
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Clear the input
     */
    public function clear(): self
    {
        $this->value = '';
        $this->cursorPos = 0;
        $this->scrollOffset = 0;

        return $this;
    }

    /**
     * Get cursor position for display
     */
    public function getCursorPosition(): ?array
    {
        if (! $this->focused) {
            return null;
        }

        return [
            'x' => $this->cursorPos - $this->scrollOffset,
            'y' => 0,
        ];
    }

    /**
     * Handle keyboard input
     */
    public function handleInput(string $key): mixed
    {
        if (! $this->focused) {
            return null;
        }

        switch ($key) {
            case "\n": // Enter
                return $this->value; // Return current value
            case "\177": // Backspace
            case "\010": // Ctrl+H
                if ($this->cursorPos > 0) {
                    $before = mb_substr($this->value, 0, $this->cursorPos - 1);
                    $after = mb_substr($this->value, $this->cursorPos);
                    $this->value = $before . $after;
                    $this->cursorPos--;
                    $this->updateScrollOffset();
                }
                break;
            case 'LEFT':
                if ($this->cursorPos > 0) {
                    $this->cursorPos--;
                    $this->updateScrollOffset();
                }
                break;
            case 'RIGHT':
                if ($this->cursorPos < mb_strlen($this->value)) {
                    $this->cursorPos++;
                    $this->updateScrollOffset();
                }
                break;
            case "\001": // Ctrl+A - Home
                $this->cursorPos = 0;
                $this->updateScrollOffset();
                break;
            case "\005": // Ctrl+E - End
                $this->cursorPos = mb_strlen($this->value);
                $this->updateScrollOffset();
                break;
            case "\013": // Ctrl+K - Delete to end
                $this->value = mb_substr($this->value, 0, $this->cursorPos);
                break;
            case "\025": // Ctrl+U - Delete to beginning
                $after = mb_substr($this->value, $this->cursorPos);
                $this->value = $after;
                $this->cursorPos = 0;
                $this->updateScrollOffset();
                break;
            default:
                // Regular character input
                if (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
                    // Check max length
                    if ($this->maxLength === null || mb_strlen($this->value) < $this->maxLength) {
                        $before = mb_substr($this->value, 0, $this->cursorPos);
                        $after = mb_substr($this->value, $this->cursorPos);
                        $this->value = $before . $key . $after;
                        $this->cursorPos++;
                        $this->updateScrollOffset();
                    }
                }
                break;
        }

        return null;
    }

    /**
     * Render the input field
     */
    public function render(): string
    {
        $display = $this->getDisplayText();
        $lines = [];

        if ($this->focused) {
            // Show with focus styling
            $prompt = Terminal::BLUE . '> ' . Terminal::RESET;
            $lines[] = $prompt . $display;
        } else {
            // Show without focus
            $prompt = Terminal::DIM . '> ' . Terminal::RESET;
            $lines[] = $prompt . Terminal::DIM . $display . Terminal::RESET;
        }

        // Pad remaining lines if height > 1
        for ($i = 1; $i < $this->height; $i++) {
            $lines[] = str_repeat(' ', $this->width);
        }

        return implode("\n", $lines);
    }

    /**
     * Set placeholder text
     */
    public function setPlaceholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Set maximum input length
     */
    public function setMaxLength(?int $maxLength): self
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    /**
     * Enable/disable password mode
     */
    public function setPassword(bool $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Update scroll offset to keep cursor visible
     */
    protected function updateScrollOffset(): void
    {
        // Keep cursor in view
        if ($this->cursorPos < $this->scrollOffset) {
            $this->scrollOffset = $this->cursorPos;
        } elseif ($this->cursorPos >= $this->scrollOffset + $this->width) {
            $this->scrollOffset = $this->cursorPos - $this->width + 1;
        }

        // Don't scroll past the beginning
        $this->scrollOffset = max(0, $this->scrollOffset);
    }

    /**
     * Get the display text (visible portion)
     */
    protected function getDisplayText(): string
    {
        $text = $this->value;

        // Handle password mode
        if ($this->password) {
            $text = str_repeat('*', mb_strlen($text));
        }

        // Show placeholder if empty and not focused
        if (empty($text) && ! $this->focused && $this->placeholder) {
            return Terminal::DIM . $this->placeholder . Terminal::RESET;
        }

        // Show visible portion based on scroll offset
        $visibleWidth = $this->width - 2; // Account for prompt
        if (mb_strlen($text) <= $visibleWidth) {
            return $text;
        }

        $visible = mb_substr($text, $this->scrollOffset, $visibleWidth);

        // Add scroll indicators
        $indicators = '';
        if ($this->scrollOffset > 0) {
            $indicators = Terminal::DIM . '◀' . Terminal::RESET;
            $visible = $indicators . mb_substr($visible, 1);
        }

        if ($this->scrollOffset + $visibleWidth < mb_strlen($text)) {
            $indicators = Terminal::DIM . '▶' . Terminal::RESET;
            $visible = mb_substr($visible, 0, -1) . $indicators;
        }

        return $visible;
    }
}
