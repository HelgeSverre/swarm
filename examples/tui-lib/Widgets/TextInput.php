<?php

declare(strict_types=1);

namespace Examples\TuiLib\Widgets;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;
use Examples\TuiLib\Focus\FocusNode;

/**
 * Interactive text input widget with cursor and selection support
 */
class TextInput extends Widget
{
    protected string $value = '';

    protected int $cursorPosition = 0;

    protected ?int $selectionStart = null;

    protected ?int $selectionEnd = null;

    protected int $scrollOffset = 0;

    protected ?FocusNode $focusNode = null;

    public function __construct(
        protected readonly string $placeholder = '',
        protected readonly ?string $initialValue = null,
        protected readonly int $maxLength = 1000,
        protected readonly bool $password = false,
        protected readonly ?string $borderColor = null,
        protected readonly ?string $focusedBorderColor = 'blue',
        protected readonly ?string $textColor = null,
        protected readonly ?string $placeholderColor = 'gray',
        protected readonly ?string $selectionColor = 'blue',
        ?string $id = null
    ) {
        parent::__construct($id);

        if ($initialValue !== null) {
            $this->value = mb_substr($initialValue, 0, $this->maxLength);
            $this->cursorPosition = mb_strlen($this->value);
        }

        $this->focusable = true;
        $this->focusNode = new FocusNode($this->getId());
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        // Text input needs at least 3 characters width (for cursor and content)
        $width = max(3, min($constraints->maxWidth, 20)); // Default width

        return new Size($width, 1); // Single line input
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

        // Determine what to display
        $displayText = $this->getDisplayText();
        $visibleText = $this->getVisibleText($displayText);

        // Apply colors and selection
        $styledText = $this->applyTextStyling($visibleText, $hasFocus);

        // Position cursor and draw
        $y = $this->bounds->y;
        $x = $this->bounds->x;

        $output[] = "\033[{$y};{$x}H" . $styledText;

        // Draw cursor if focused
        if ($hasFocus) {
            $cursorX = $this->getCursorDisplayPosition();
            $output[] = "\033[{$y};" . ($x + $cursorX) . 'H';
        }

        return implode('', $output);
    }

    public function handleKeyEvent(string $key): bool
    {
        if (! $this->focusNode?->hasFocus()) {
            return false;
        }

        $handled = true;

        match ($key) {
            // Navigation
            'Left', "\033[D" => $this->moveCursorLeft(),
            'Right', "\033[C" => $this->moveCursorRight(),
            'Home', "\033[H" => $this->moveCursorToStart(),
            'End', "\033[F" => $this->moveCursorToEnd(),

            // Selection (Shift + navigation)
            'Shift+Left' => $this->extendSelectionLeft(),
            'Shift+Right' => $this->extendSelectionRight(),
            'Shift+Home' => $this->selectToStart(),
            'Shift+End' => $this->selectToEnd(),
            'Ctrl+a' => $this->selectAll(),

            // Editing
            'Backspace', "\177", "\010" => $this->handleBackspace(),
            'Delete', "\033[3~" => $this->handleDelete(),
            'Ctrl+x' => $this->cut(),
            'Ctrl+c' => $this->copy(),
            'Ctrl+v' => $this->paste(),

            // Escape clears selection
            'Escape', "\033" => $this->clearSelection(),

            default => $handled = $this->handleCharacterInput($key),
        };

        if ($handled) {
            $this->markNeedsRepaint();
        }

        return $handled;
    }

    public function handleFocusChange(bool $focused): void
    {
        parent::handleFocusChange($focused);

        if (! $focused) {
            $this->clearSelection();
        }
    }

    // Getters and setters
    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = mb_substr($value, 0, $this->maxLength);
        $this->cursorPosition = min($this->cursorPosition, mb_strlen($this->value));
        $this->clearSelection();
        $this->markNeedsRepaint();
    }

    public function getCursorPosition(): int
    {
        return $this->cursorPosition;
    }

    public function setCursorPosition(int $position): void
    {
        $this->cursorPosition = max(0, min(mb_strlen($this->value), $position));
        $this->clearSelection();
        $this->markNeedsRepaint();
    }

    public function getFocusNode(): ?FocusNode
    {
        return $this->focusNode;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    public function isPassword(): bool
    {
        return $this->password;
    }

    protected function getDisplayText(): string
    {
        if ($this->value === '' && ! empty($this->placeholder)) {
            return $this->placeholder;
        }

        return $this->password ? str_repeat('*', mb_strlen($this->value)) : $this->value;
    }

    protected function getVisibleText(string $text): string
    {
        $maxWidth = $this->bounds->width;

        // Adjust scroll offset to keep cursor visible
        $this->adjustScrollOffset($maxWidth);

        return mb_substr($text, $this->scrollOffset, $maxWidth);
    }

    protected function adjustScrollOffset(int $maxWidth): void
    {
        $textLength = mb_strlen($this->value);

        // If cursor is beyond visible area, scroll right
        if ($this->cursorPosition - $this->scrollOffset >= $maxWidth) {
            $this->scrollOffset = $this->cursorPosition - $maxWidth + 1;
        }

        // If cursor is before visible area, scroll left
        if ($this->cursorPosition < $this->scrollOffset) {
            $this->scrollOffset = $this->cursorPosition;
        }

        // Don't scroll past the beginning
        $this->scrollOffset = max(0, $this->scrollOffset);

        // Don't scroll past the end unnecessarily
        if ($textLength <= $maxWidth) {
            $this->scrollOffset = 0;
        }
    }

    protected function getCursorDisplayPosition(): int
    {
        return $this->cursorPosition - $this->scrollOffset;
    }

    protected function applyTextStyling(string $text, bool $hasFocus): string
    {
        $styled = $text;

        // Handle placeholder styling
        if ($this->value === '' && ! empty($this->placeholder)) {
            $color = $this->placeholderColor ?? 'gray';
            $styled = $this->getColorCode($color) . $styled . "\033[0m";

            return $styled;
        }

        // Apply selection highlighting
        if ($this->hasSelection()) {
            $styled = $this->applySelectionStyling($styled);
        }

        // Apply text color
        if ($this->textColor !== null) {
            $styled = $this->getColorCode($this->textColor) . $styled . "\033[0m";
        }

        return $styled;
    }

    protected function applySelectionStyling(string $text): string
    {
        if (! $this->hasSelection() || $this->selectionStart === null || $this->selectionEnd === null) {
            return $text;
        }

        $start = min($this->selectionStart, $this->selectionEnd) - $this->scrollOffset;
        $end = max($this->selectionStart, $this->selectionEnd) - $this->scrollOffset;

        $start = max(0, $start);
        $end = min(mb_strlen($text), $end);

        if ($start >= $end) {
            return $text;
        }

        $before = mb_substr($text, 0, $start);
        $selected = mb_substr($text, $start, $end - $start);
        $after = mb_substr($text, $end);

        $selectionBg = $this->getBackgroundColorCode($this->selectionColor ?? 'blue');

        return $before . $selectionBg . $selected . "\033[0m" . $after;
    }

    protected function moveCursorLeft(): void
    {
        $this->clearSelection();
        $this->cursorPosition = max(0, $this->cursorPosition - 1);
    }

    protected function moveCursorRight(): void
    {
        $this->clearSelection();
        $this->cursorPosition = min(mb_strlen($this->value), $this->cursorPosition + 1);
    }

    protected function moveCursorToStart(): void
    {
        $this->clearSelection();
        $this->cursorPosition = 0;
    }

    protected function moveCursorToEnd(): void
    {
        $this->clearSelection();
        $this->cursorPosition = mb_strlen($this->value);
    }

    protected function extendSelectionLeft(): void
    {
        if (! $this->hasSelection()) {
            $this->selectionStart = $this->cursorPosition;
        }
        $this->cursorPosition = max(0, $this->cursorPosition - 1);
        $this->selectionEnd = $this->cursorPosition;
    }

    protected function extendSelectionRight(): void
    {
        if (! $this->hasSelection()) {
            $this->selectionStart = $this->cursorPosition;
        }
        $this->cursorPosition = min(mb_strlen($this->value), $this->cursorPosition + 1);
        $this->selectionEnd = $this->cursorPosition;
    }

    protected function selectToStart(): void
    {
        $this->selectionStart = $this->cursorPosition;
        $this->cursorPosition = 0;
        $this->selectionEnd = $this->cursorPosition;
    }

    protected function selectToEnd(): void
    {
        $this->selectionStart = $this->cursorPosition;
        $this->cursorPosition = mb_strlen($this->value);
        $this->selectionEnd = $this->cursorPosition;
    }

    protected function selectAll(): void
    {
        $this->selectionStart = 0;
        $this->selectionEnd = mb_strlen($this->value);
        $this->cursorPosition = $this->selectionEnd;
    }

    protected function clearSelection(): void
    {
        $this->selectionStart = null;
        $this->selectionEnd = null;
    }

    protected function hasSelection(): bool
    {
        return $this->selectionStart !== null &&
               $this->selectionEnd !== null &&
               $this->selectionStart !== $this->selectionEnd;
    }

    protected function handleBackspace(): void
    {
        if ($this->hasSelection()) {
            $this->deleteSelection();
        } elseif ($this->cursorPosition > 0) {
            $this->value = mb_substr($this->value, 0, $this->cursorPosition - 1) .
                          mb_substr($this->value, $this->cursorPosition);
            $this->cursorPosition--;
        }
    }

    protected function handleDelete(): void
    {
        if ($this->hasSelection()) {
            $this->deleteSelection();
        } elseif ($this->cursorPosition < mb_strlen($this->value)) {
            $this->value = mb_substr($this->value, 0, $this->cursorPosition) .
                          mb_substr($this->value, $this->cursorPosition + 1);
        }
    }

    protected function deleteSelection(): void
    {
        if (! $this->hasSelection() || $this->selectionStart === null || $this->selectionEnd === null) {
            return;
        }

        $start = min($this->selectionStart, $this->selectionEnd);
        $end = max($this->selectionStart, $this->selectionEnd);

        $this->value = mb_substr($this->value, 0, $start) . mb_substr($this->value, $end);
        $this->cursorPosition = $start;
        $this->clearSelection();
    }

    protected function handleCharacterInput(string $char): bool
    {
        // Filter out control characters
        if (mb_strlen($char) === 1 && ord($char) < 32) {
            return false;
        }

        // Check max length
        if (mb_strlen($this->value) >= $this->maxLength) {
            return false;
        }

        if ($this->hasSelection()) {
            $this->deleteSelection();
        }

        $this->value = mb_substr($this->value, 0, $this->cursorPosition) .
                      $char .
                      mb_substr($this->value, $this->cursorPosition);
        $this->cursorPosition++;

        return true;
    }

    protected function cut(): void
    {
        if ($this->hasSelection()) {
            $this->copy();
            $this->deleteSelection();
        }
    }

    protected function copy(): void
    {
        // Note: Actual clipboard implementation would depend on the system
        // This is a placeholder for the copy functionality
    }

    protected function paste(): void
    {
        // Note: Actual clipboard implementation would depend on the system
        // This is a placeholder for the paste functionality
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
