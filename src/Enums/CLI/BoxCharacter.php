<?php

namespace HelgeSverre\Swarm\Enums\CLI;

/**
 * Unicode box drawing characters for TUI
 */
enum BoxCharacter: string
{
    /**
     * Get the Unicode character for this box element
     */
    public function getChar(): string
    {
        return match ($this) {
            self::Horizontal => '─',
            self::Vertical => '│',
            self::TopLeft => '┌',
            self::TopRight => '┐',
            self::BottomLeft => '└',
            self::BottomRight => '┘',
            self::Cross => '┼',
            self::TeeDown => '┬',
            self::TeeUp => '┴',
            self::TeeRight => '├',
            self::TeeLeft => '┤',
        };
    }
    case Horizontal = 'horizontal';
    case Vertical = 'vertical';
    case TopLeft = 'top_left';
    case TopRight = 'top_right';
    case BottomLeft = 'bottom_left';
    case BottomRight = 'bottom_right';
    case Cross = 'cross';
    case TeeDown = 'tee_down';
    case TeeUp = 'tee_up';
    case TeeRight = 'tee_right';
    case TeeLeft = 'tee_left';
}
