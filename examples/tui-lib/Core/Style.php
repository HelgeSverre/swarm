<?php

declare(strict_types=1);

namespace Examples\TuiLib\Core;

/**
 * Color enumeration for terminal colors
 */
enum Color: string
{
    // Standard colors
    case Black = '30';
    case Red = '31';
    case Green = '32';
    case Yellow = '33';
    case Blue = '34';
    case Magenta = '35';
    case Cyan = '36';
    case White = '37';

    // Bright colors
    case BrightBlack = '90';
    case BrightRed = '91';
    case BrightGreen = '92';
    case BrightYellow = '93';
    case BrightBlue = '94';
    case BrightMagenta = '95';
    case BrightCyan = '96';
    case BrightWhite = '97';

    // Background colors
    case BgBlack = '40';
    case BgRed = '41';
    case BgGreen = '42';
    case BgYellow = '43';
    case BgBlue = '44';
    case BgMagenta = '45';
    case BgCyan = '46';
    case BgWhite = '47';
    case BgDark = '48;5;235'; // Dark gray background

    // Special colors
    case Reset = '0';
    case Default = '39';
}

/**
 * Text decoration enumeration
 */
enum TextDecoration: string
{
    case Bold = '1';
    case Dim = '2';
    case Italic = '3';
    case Underline = '4';
    case Reverse = '7';
    case Strikethrough = '9';
    case Reset = '0';
}

/**
 * Style class for combining colors and decorations
 */
class Style
{
    protected ?Color $foreground = null;

    protected ?Color $background = null;

    protected array $decorations = [];

    public function __construct(
        ?Color $foreground = null,
        ?Color $background = null,
        array $decorations = []
    ) {
        $this->foreground = $foreground;
        $this->background = $background;
        $this->decorations = $decorations;
    }

    /**
     * Create a new style with foreground color
     */
    public static function fg(Color $color): self
    {
        return new self($color);
    }

    /**
     * Create a new style with background color
     */
    public static function bg(Color $color): self
    {
        return new self(null, $color);
    }

    /**
     * Create a new style with decoration
     */
    public static function decoration(TextDecoration $decoration): self
    {
        return new self(null, null, [$decoration]);
    }

    /**
     * Add foreground color
     */
    public function withForeground(Color $color): self
    {
        return new self($color, $this->background, $this->decorations);
    }

    /**
     * Add background color
     */
    public function withBackground(Color $color): self
    {
        return new self($this->foreground, $color, $this->decorations);
    }

    /**
     * Add decoration
     */
    public function withDecoration(TextDecoration $decoration): self
    {
        $decorations = $this->decorations;
        $decorations[] = $decoration;

        return new self($this->foreground, $this->background, $decorations);
    }

    /**
     * Apply style to text
     */
    public function apply(string $text): string
    {
        $codes = [];

        if ($this->foreground !== null) {
            $codes[] = $this->foreground->value;
        }

        if ($this->background !== null) {
            $codes[] = $this->background->value;
        }

        foreach ($this->decorations as $decoration) {
            $codes[] = $decoration->value;
        }

        if (empty($codes)) {
            return $text;
        }

        $start = "\033[" . implode(';', $codes) . 'm';
        $end = "\033[0m";

        return $start . $text . $end;
    }

    /**
     * Get the opening ANSI sequence
     */
    public function start(): string
    {
        $codes = [];

        if ($this->foreground !== null) {
            $codes[] = $this->foreground->value;
        }

        if ($this->background !== null) {
            $codes[] = $this->background->value;
        }

        foreach ($this->decorations as $decoration) {
            $codes[] = $decoration->value;
        }

        if (empty($codes)) {
            return '';
        }

        return "\033[" . implode(';', $codes) . 'm';
    }

    /**
     * Get the closing ANSI sequence
     */
    public function end(): string
    {
        return "\033[0m";
    }

    /**
     * Combine this style with another
     */
    public function merge(Style $other): self
    {
        return new self(
            $other->foreground ?? $this->foreground,
            $other->background ?? $this->background,
            array_merge($this->decorations, $other->decorations)
        );
    }
}

/**
 * Predefined common styles
 */
class Styles
{
    // Basic colors
    public static function red(): Style
    {
        return Style::fg(Color::Red);
    }

    public static function green(): Style
    {
        return Style::fg(Color::Green);
    }

    public static function yellow(): Style
    {
        return Style::fg(Color::Yellow);
    }

    public static function blue(): Style
    {
        return Style::fg(Color::Blue);
    }

    public static function magenta(): Style
    {
        return Style::fg(Color::Magenta);
    }

    public static function cyan(): Style
    {
        return Style::fg(Color::Cyan);
    }

    public static function white(): Style
    {
        return Style::fg(Color::White);
    }

    // Bright colors
    public static function brightRed(): Style
    {
        return Style::fg(Color::BrightRed);
    }

    public static function brightGreen(): Style
    {
        return Style::fg(Color::BrightGreen);
    }

    public static function brightYellow(): Style
    {
        return Style::fg(Color::BrightYellow);
    }

    public static function brightBlue(): Style
    {
        return Style::fg(Color::BrightBlue);
    }

    public static function brightMagenta(): Style
    {
        return Style::fg(Color::BrightMagenta);
    }

    public static function brightCyan(): Style
    {
        return Style::fg(Color::BrightCyan);
    }

    public static function brightWhite(): Style
    {
        return Style::fg(Color::BrightWhite);
    }

    // Text decorations
    public static function bold(): Style
    {
        return Style::decoration(TextDecoration::Bold);
    }

    public static function dim(): Style
    {
        return Style::decoration(TextDecoration::Dim);
    }

    public static function italic(): Style
    {
        return Style::decoration(TextDecoration::Italic);
    }

    public static function underline(): Style
    {
        return Style::decoration(TextDecoration::Underline);
    }

    public static function reverse(): Style
    {
        return Style::decoration(TextDecoration::Reverse);
    }

    // Common combinations
    public static function error(): Style
    {
        return Style::fg(Color::Red)->withDecoration(TextDecoration::Bold);
    }

    public static function success(): Style
    {
        return Style::fg(Color::Green)->withDecoration(TextDecoration::Bold);
    }

    public static function warning(): Style
    {
        return Style::fg(Color::Yellow)->withDecoration(TextDecoration::Bold);
    }

    public static function info(): Style
    {
        return Style::fg(Color::Cyan);
    }

    public static function muted(): Style
    {
        return Style::decoration(TextDecoration::Dim);
    }

    public static function highlight(): Style
    {
        return Style::decoration(TextDecoration::Reverse);
    }

    public static function headerDark(): Style
    {
        return Style::bg(Color::BgDark)->withForeground(Color::White);
    }

    // Focus states
    public static function focused(): Style
    {
        return Style::fg(Color::BrightCyan)->withDecoration(TextDecoration::Bold);
    }

    public static function unfocused(): Style
    {
        return Style::decoration(TextDecoration::Dim);
    }
}

/**
 * Helper class for text with embedded styling
 */
class StyledText
{
    protected string $text;

    protected ?Style $style;

    public function __construct(string $text, ?Style $style = null)
    {
        $this->text = $text;
        $this->style = $style;
    }

    /**
     * Create styled text
     */
    public static function make(string $text, ?Style $style = null): self
    {
        return new self($text, $style);
    }

    /**
     * Get the raw text without styling
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Get the text length without ANSI codes
     */
    public function getLength(): int
    {
        return mb_strlen($this->text);
    }

    /**
     * Render the styled text
     */
    public function render(): string
    {
        if ($this->style === null) {
            return $this->text;
        }

        return $this->style->apply($this->text);
    }

    /**
     * Apply additional style
     */
    public function withStyle(Style $style): self
    {
        $combinedStyle = $this->style ? $this->style->merge($style) : $style;

        return new self($this->text, $combinedStyle);
    }

    /**
     * Truncate text to specified length
     */
    public function truncate(int $length, string $suffix = '...'): self
    {
        if (mb_strlen($this->text) <= $length) {
            return $this;
        }

        $truncated = mb_substr($this->text, 0, $length - mb_strlen($suffix)) . $suffix;

        return new self($truncated, $this->style);
    }

    /**
     * Pad text to specified length
     */
    public function pad(int $length, string $pad = ' ', int $type = STR_PAD_RIGHT): self
    {
        $padded = mb_str_pad($this->text, $length, $pad, $type);

        return new self($padded, $this->style);
    }
}
