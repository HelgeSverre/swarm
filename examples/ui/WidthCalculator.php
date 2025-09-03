<?php

declare(strict_types=1);

/**
 * WidthCalculator - Utility class for accurate terminal width calculations
 *
 * Wraps the SoloTerm Grapheme library to provide consistent emoji-aware
 * width calculations for terminal UI applications.
 */
class WidthCalculator
{
    /**
     * Calculate the visual width of a string in terminal columns
     *
     * @param string $text The text to measure
     *
     * @return int The visual width in terminal columns
     */
    public static function width(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Remove ANSI escape sequences before calculating width
        $cleanText = self::stripAnsiCodes($text);

        // Use SoloTerm Grapheme for accurate width calculation
        if (class_exists('SoloTerm\Grapheme\Grapheme')) {
            $totalWidth = 0;
            $length = mb_strlen($cleanText);

            for ($i = 0; $i < $length; $i++) {
                $char = mb_substr($cleanText, $i, 1);
                $charWidth = SoloTerm\Grapheme\Grapheme::wcwidth($char);
                $totalWidth += max(0, $charWidth ?? 0);
            }

            return $totalWidth;
        }

        // Fallback to basic multibyte length calculation
        return mb_strlen($cleanText);
    }

    /**
     * Strip ANSI color codes and control sequences from text
     *
     * @param string $text Text that may contain ANSI codes
     *
     * @return string Text with ANSI codes removed
     */
    public static function stripAnsiCodes(string $text): string
    {
        // Remove ANSI escape sequences (colors, cursor movement, etc.)
        return preg_replace('/\033\[[0-9;]*[A-Za-z]/', '', $text);
    }

    /**
     * Truncate a string to fit within a specific visual width
     *
     * @param string $text The text to truncate
     * @param int $maxWidth Maximum visual width in terminal columns
     * @param string $suffix Suffix to add when truncating (default: '...')
     *
     * @return string The truncated text
     */
    public static function truncate(string $text, int $maxWidth, string $suffix = '...'): string
    {
        if ($maxWidth <= 0) {
            return '';
        }

        $textWidth = self::width($text);
        if ($textWidth <= $maxWidth) {
            return $text;
        }

        $suffixWidth = self::width($suffix);
        $targetWidth = $maxWidth - $suffixWidth;

        if ($targetWidth <= 0) {
            return mb_substr($suffix, 0, $maxWidth);
        }

        // Binary search to find the optimal truncation point
        $low = 0;
        $high = mb_strlen($text);
        $result = '';

        while ($low <= $high) {
            $mid = intval(($low + $high) / 2);
            $substring = mb_substr($text, 0, $mid);
            $substringWidth = self::width($substring);

            if ($substringWidth <= $targetWidth) {
                $result = $substring;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $result . $suffix;
    }

    /**
     * Pad a string to a specific visual width
     *
     * @param string $text The text to pad
     * @param int $totalWidth Total visual width desired
     * @param string $alignment 'left', 'center', or 'right'
     * @param string $padChar Character to use for padding (default: space)
     *
     * @return string The padded text
     */
    public static function pad(string $text, int $totalWidth, string $alignment = 'left', string $padChar = ' '): string
    {
        $textWidth = self::width($text);

        if ($textWidth >= $totalWidth) {
            return self::truncate($text, $totalWidth, '');
        }

        $padNeeded = $totalWidth - $textWidth;
        $padCharWidth = self::width($padChar);

        if ($padCharWidth <= 0) {
            return $text;
        }

        $padCount = intval($padNeeded / $padCharWidth);
        $padding = str_repeat($padChar, $padCount);

        return match ($alignment) {
            'center' => (function () use ($padCount, $padChar, $text) {
                $leftPadCount = intval($padCount / 2);
                $rightPadCount = $padCount - $leftPadCount;

                return str_repeat($padChar, $leftPadCount) . $text . str_repeat($padChar, $rightPadCount);
            })(),
            'right' => $padding . $text,
            default => $text . $padding,
        };
    }

    /**
     * Calculate visual width for each character in a string
     * Useful for precise cursor positioning and character-level operations
     *
     * @param string $text The text to analyze
     *
     * @return array Array of character widths
     */
    public static function characterWidths(string $text): array
    {
        $widths = [];
        $length = mb_strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            $widths[] = self::width($char);
        }

        return $widths;
    }

    /**
     * Substring with visual width awareness
     *
     * @param string $text The source text
     * @param int $start Start position in visual columns
     * @param int|null $length Length in visual columns (null = to end)
     *
     * @return string The substring
     */
    public static function substr(string $text, int $start, ?int $length = null): string
    {
        if ($start < 0 || empty($text)) {
            return '';
        }

        $totalLength = mb_strlen($text);
        $currentWidth = 0;
        $startPos = 0;
        $endPos = $totalLength;

        // Find start position
        for ($i = 0; $i < $totalLength; $i++) {
            if ($currentWidth >= $start) {
                $startPos = $i;
                break;
            }
            $char = mb_substr($text, $i, 1);
            $currentWidth += self::width($char);
        }

        // Find end position if length is specified
        if ($length !== null) {
            $targetWidth = $start + $length;
            $currentWidth = $start;

            for ($i = $startPos; $i < $totalLength; $i++) {
                if ($currentWidth >= $targetWidth) {
                    $endPos = $i;
                    break;
                }
                $char = mb_substr($text, $i, 1);
                $currentWidth += self::width($char);
            }
        }

        return mb_substr($text, $startPos, $endPos - $startPos);
    }

    /**
     * Split text into lines that fit within a maximum width
     *
     * @param string $text The text to wrap
     * @param int $maxWidth Maximum width per line
     * @param bool $breakWords Whether to break long words
     *
     * @return array Array of lines
     */
    public static function wordWrap(string $text, int $maxWidth, bool $breakWords = true): array
    {
        if ($maxWidth <= 0) {
            return [''];
        }

        $lines = [];
        $words = explode(' ', $text);
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            $testWidth = self::width($testLine);

            if ($testWidth <= $maxWidth) {
                $currentLine = $testLine;
            } else {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                    $currentLine = $word;
                } elseif ($breakWords) {
                    // Word is longer than max width, break it
                    $remaining = $word;
                    while (self::width($remaining) > $maxWidth) {
                        $chunk = self::substr($remaining, 0, $maxWidth);
                        $lines[] = $chunk;
                        $chunkLength = mb_strlen($chunk);
                        $remaining = mb_substr($remaining, $chunkLength);
                    }
                    $currentLine = $remaining;
                } else {
                    $currentLine = $word;
                }
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines ?: [''];
    }

    /**
     * Check if the Grapheme library is available
     *
     * @return bool True if SoloTerm\Grapheme is available
     */
    public static function hasGraphemeSupport(): bool
    {
        return class_exists('SoloTerm\Grapheme\Grapheme');
    }
}
