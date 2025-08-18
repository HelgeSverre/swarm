#!/usr/bin/env php
<?php

/**
 * Test key detection in alternate screen mode
 */

echo "Testing key detection with alternate screen buffer...\n";
echo "Press Option+T (or Alt+T) to test, 'q' to quit\n\n";

// Save terminal state
$originalState = trim(shell_exec('stty -g') ?? '');

// Enter alternate screen
echo "\033[?1049h";
echo "\033[2J\033[H";

// Set raw mode
system('stty -echo -icanon min 1 time 0');
stream_set_blocking(STDIN, false);

echo "Ready for input. Press keys to see what's detected:\n\n";

$running = true;
while ($running) {
    $read = [STDIN];
    $write = null;
    $except = null;
    $result = stream_select($read, $write, $except, 0, 100000); // 100ms timeout
    
    if ($result > 0) {
        $key = fgetc(STDIN);
        
        if ($key === false || $key === '') {
            continue;
        }
        
        // Show raw byte value
        echo "Raw: " . ord($key) . " (0x" . dechex(ord($key)) . ") char: '" . $key . "'\n";
        
        if ($key === 'q') {
            $running = false;
            break;
        }
        
        // Handle escape sequences
        if ($key === "\033") {
            $seq = $key;
            
            // Try to read next character with timeout
            $read2 = [STDIN];
            $result2 = stream_select($read2, $write, $except, 0, 10000); // 10ms timeout
            
            if ($result2 > 0) {
                $next = fgetc(STDIN);
                if ($next !== false) {
                    $seq .= $next;
                    echo "  -> Escape sequence: " . bin2hex($seq) . "\n";
                    
                    // Check for Alt key combinations
                    if ($next !== '[' && $next !== false && $next !== "\033") {
                        echo "  -> Detected: ALT+" . strtoupper($next) . "\n";
                        if (strtoupper($next) === 'T') {
                            echo "  *** ALT+T DETECTED! ***\n";
                        }
                    }
                }
            } else {
                echo "  -> Just ESC key\n";
            }
        }
    }
}

// Exit alternate screen and restore terminal
echo "\033[?1049l";
system("stty {$originalState}");

echo "\nTest complete.\n";