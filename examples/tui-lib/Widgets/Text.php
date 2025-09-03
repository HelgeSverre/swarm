<?php

declare(strict_types=1);

namespace Examples\TuiLib\Widgets;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;

/**
 * Text display widget with styling and alignment support
 */
class Text extends Widget
{
    public function __construct(
        protected readonly string $text,
        protected readonly ?string $color = null,
        protected readonly ?string $backgroundColor = null,
        protected readonly bool $bold = false,
        protected readonly bool $italic = false,
        protected readonly bool $underline = false,
        protected readonly string $alignment = 'left', // left, center, right
        protected readonly bool $wordWrap = true,
        ?string $id = null
    ) {
        parent::__construct($id);
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        $lines = $this->getWrappedLines($constraints->maxWidth);
        $width = min($constraints->maxWidth, $this->getMaxLineLength($lines));
        $height = min($constraints->maxHeight, count($lines));

        return new Size($width, $height);
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

        $lines = $this->getWrappedLines($this->bounds->width);
        $output = [];

        $y = $this->bounds->y;
        foreach ($lines as $line) {
            if ($y >= $this->bounds->y + $this->bounds->height) {
                break;
            }

            $alignedLine = $this->alignLine($line, $this->bounds->width);
            $styledLine = $this->applyStyles($alignedLine);

            // Position cursor and draw line
            $output[] = "\033[{$y};{$this->bounds->x}H" . $styledLine;
            $y++;
        }

        return implode('', $output);
    }

    // Getters
    public function getText(): string
    {
        return $this->text;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function isBold(): bool
    {
        return $this->bold;
    }

    public function isItalic(): bool
    {
        return $this->italic;
    }

    public function isUnderline(): bool
    {
        return $this->underline;
    }

    public function getAlignment(): string
    {
        return $this->alignment;
    }

    public function isWordWrap(): bool
    {
        return $this->wordWrap;
    }

    protected function getWrappedLines(int $maxWidth): array
    {
        if (! $this->wordWrap || $maxWidth <= 0) {
            return [$this->text];
        }

        $lines = [];
        $words = explode(' ', $this->text);
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;

            if (mb_strlen($testLine) <= $maxWidth) {
                $currentLine = $testLine;
            } else {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                    $currentLine = $word;
                } else {
                    // Word is longer than max width, break it
                    $lines[] = mb_substr($word, 0, $maxWidth);
                    $remaining = mb_substr($word, $maxWidth);
                    while (mb_strlen($remaining) > 0) {
                        $chunk = mb_substr($remaining, 0, $maxWidth);
                        $lines[] = $chunk;
                        $remaining = mb_substr($remaining, $maxWidth);
                    }
                    $currentLine = '';
                }
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines ?: [''];
    }

    protected function getMaxLineLength(array $lines): int
    {
        $maxLength = 0;
        foreach ($lines as $line) {
            $maxLength = max($maxLength, mb_strlen($line));
        }

        return $maxLength;
    }

    protected function alignLine(string $line, int $width): string
    {
        $lineLength = mb_strlen($line);

        if ($lineLength >= $width) {
            return mb_substr($line, 0, $width);
        }

        return match ($this->alignment) {
            'center' => str_repeat(' ', intval(($width - $lineLength) / 2)) . $line,
            'right' => str_repeat(' ', $width - $lineLength) . $line,
            default => $line . str_repeat(' ', $width - $lineLength),
        };
    }

    protected function applyStyles(string $text): string
    {
        $styled = $text;

        // Apply text formatting
        if ($this->bold) {
            $styled = "\033[1m" . $styled;
        }
        if ($this->italic) {
            $styled = "\033[3m" . $styled;
        }
        if ($this->underline) {
            $styled = "\033[4m" . $styled;
        }

        // Apply colors
        if ($this->color !== null) {
            $styled = $this->getColorCode($this->color) . $styled;
        }
        if ($this->backgroundColor !== null) {
            $styled = $this->getBackgroundColorCode($this->backgroundColor) . $styled;
        }

        // Reset styles at the end
        if ($this->bold || $this->italic || $this->underline || $this->color !== null || $this->backgroundColor !== null) {
            $styled .= "\033[0m";
        }

        return $styled;
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
