<?php

namespace HelgeSverre\Swarm\CLI;

use Psy\Readline\GNUReadline;
use Psy\Readline\Libedit;
use Psy\Readline\Readline;
use Psy\Readline\Transient;

/**
 * Handles terminal input using PsySH's robust readline implementation
 */
class InputHandler
{
    protected static ?Readline $readline = null;

    /**
     * Read input with a protected prompt that cannot be erased
     */
    public static function readLine(string $prompt = '', string $promptColor = ''): string
    {
        $readline = self::getReadline();

        // Build the full prompt with color
        $fullPrompt = '';
        if ($prompt !== '') {
            $fullPrompt = $promptColor . $prompt . "\033[0m";
        }

        // Use PsySH's readline which handles all the complex input scenarios
        $input = $readline->readline($fullPrompt);

        // Handle false return (Ctrl+D)
        if ($input === false) {
            return '';
        }

        return $input;
    }

    /**
     * Add a line to the command history
     */
    public static function addHistory(string $line): void
    {
        if (trim($line) !== '') {
            self::getReadline()->addHistory($line);
        }
    }

    /**
     * Clear the command history
     */
    public static function clearHistory(): void
    {
        self::getReadline()->clearHistory();
    }

    /**
     * Initialize the readline handler
     */
    protected static function getReadline(): Readline
    {
        if (self::$readline === null) {
            // Try different readline implementations in order of preference
            if (GNUReadline::isSupported()) {
                self::$readline = new GNUReadline;
            } elseif (Libedit::isSupported()) {
                self::$readline = new Libedit;
            } else {
                // Fallback to transient (basic) implementation
                self::$readline = new Transient;
            }
        }

        return self::$readline;
    }
}
