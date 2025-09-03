<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Manual includes for the TUI lib classes (in dependency order)
require_once __DIR__ . '/Core/Constraints.php';
require_once __DIR__ . '/Core/BuildContext.php';
require_once __DIR__ . '/Core/Widget.php';
require_once __DIR__ . '/Core/Canvas.php';
require_once __DIR__ . '/Core/RenderPipeline.php';
require_once __DIR__ . '/Core/Style.php';
require_once __DIR__ . '/Core/Theme.php';
require_once __DIR__ . '/Core/TextMeasurement.php';
require_once __DIR__ . '/Core/Logger.php';
require_once __DIR__ . '/Core/FrameBuffer.php';

// SwarmMock classes
require_once __DIR__ . '/SwarmMock/SwarmMockData.php';
require_once __DIR__ . '/SwarmMock/SwarmHeader.php';
require_once __DIR__ . '/SwarmMock/SwarmActivityLog.php';
require_once __DIR__ . '/SwarmMock/SwarmSidebar.php';
require_once __DIR__ . '/SwarmMock/SwarmInput.php';
require_once __DIR__ . '/SwarmMock/SwarmMockApp.php';

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\FrameBuffer;
use Examples\TuiLib\Core\Logger;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\SwarmMock\SwarmMockApp;

/**
 * Terminal utilities for the Swarm mock demo
 */
class SwarmMockTerminal
{
    protected static bool $rawModeEnabled = false;

    protected static ?array $originalSettings = null;

    /**
     * Initialize terminal for TUI mode
     */
    public static function initialize(): void
    {
        // Save original settings
        if (function_exists('shell_exec')) {
            self::$originalSettings = [
                'stty' => trim(shell_exec('stty -g') ?? ''),
            ];
        }

        // Enable raw mode
        self::enableRawMode();

        // Clear screen and hide cursor
        echo "\033[2J\033[H\033[?25l";

        // Enable alternative screen buffer
        echo "\033[?1049h";
    }

    /**
     * Restore terminal to normal mode
     */
    public static function restore(): void
    {
        // Show cursor
        echo "\033[?25h";

        // Restore normal screen buffer
        echo "\033[?1049l";

        // Restore terminal settings
        self::disableRawMode();

        echo "\n";
    }

    /**
     * Get terminal size
     */
    public static function getTerminalSize(): Size
    {
        $width = 80;
        $height = 24;

        if (function_exists('shell_exec')) {
            $size = shell_exec('stty size 2>/dev/null');
            if ($size && preg_match('/(\d+) (\d+)/', trim($size), $matches)) {
                $height = (int) $matches[1];
                $width = (int) $matches[2];
            }
        }

        return new Size($width, $height);
    }

    /**
     * Read a single key from stdin (non-blocking)
     */
    public static function readKey(): ?string
    {
        $read = [STDIN];
        $write = [];
        $except = [];

        // Non-blocking select with 50ms timeout
        $result = stream_select($read, $write, $except, 0, 50000);

        if ($result > 0) {
            $key = fread(STDIN, 1);

            // Handle escape sequences
            if ($key === "\033") {
                $seq = $key;

                // Read the rest of the escape sequence
                for ($i = 0; $i < 10; $i++) {
                    $read = [STDIN];
                    $write = [];
                    $except = [];

                    if (stream_select($read, $write, $except, 0, 10000) > 0) {
                        $char = fread(STDIN, 1);
                        $seq .= $char;

                        // Stop if we have a complete sequence
                        if (preg_match('/\033\[[0-9;]*[A-Za-z~]/', $seq) ||
                            preg_match('/\033[A-Za-z]/', $seq)) {
                            break;
                        }
                    } else {
                        break;
                    }
                }

                // Convert to standard key names
                return match ($seq) {
                    "\033[A" => 'UP',
                    "\033[B" => 'DOWN',
                    "\033[C" => 'RIGHT',
                    "\033[D" => 'LEFT',
                    "\033[Z" => 'SHIFT_TAB',
                    default => $seq,
                };
            }

            // Handle tab
            if ($key === "\t") {
                return 'TAB';
            }

            // Handle Alt combinations (simplified)
            if (mb_strlen($seq ?? '') > 1 && str_starts_with($seq ?? '', "\033")) {
                $altKey = mb_substr($seq, 1, 1);
                if (ctype_alpha($altKey)) {
                    return 'ALT+' . mb_strtoupper($altKey);
                }
            }

            return $key;
        }

        return null;
    }

    /**
     * Enable raw terminal mode
     */
    protected static function enableRawMode(): void
    {
        if (self::$rawModeEnabled) {
            return;
        }

        if (function_exists('shell_exec')) {
            // Disable canonical mode and echo
            shell_exec('stty -icanon -echo');
            self::$rawModeEnabled = true;
        }
    }

    /**
     * Disable raw terminal mode
     */
    protected static function disableRawMode(): void
    {
        if (! self::$rawModeEnabled) {
            return;
        }

        if (function_exists('shell_exec') && self::$originalSettings !== null) {
            // Restore original settings
            shell_exec('stty ' . self::$originalSettings['stty']);
            self::$rawModeEnabled = false;
        }
    }
}

/**
 * Main demo application
 */
function runSwarmMock(): void
{
    try {
        // Initialize terminal
        SwarmMockTerminal::initialize();

        echo "Initializing Swarm Mock UI...\n";
        sleep(1);

        // Initialize logging if debug mode is enabled
        if (getenv('TUI_DEBUG')) {
            Logger::info('TUI Debug mode enabled - logging to /tmp/tui-debug.log');
        }

        // Create and setup the app
        $terminalSize = SwarmMockTerminal::getTerminalSize();
        $frameBuffer = new FrameBuffer($terminalSize);
        $context = new BuildContext($terminalSize, canvas: $frameBuffer->getBackBuffer());
        $app = new SwarmMockApp('swarm_mock');

        Logger::info('SwarmMock initialized', [
            'terminal_size' => ['width' => $terminalSize->width, 'height' => $terminalSize->height],
            'debug_enabled' => (bool) getenv('TUI_DEBUG'),
        ]);

        // Set up command handler
        $app->setOnCommand(function (string $command) use ($app) {
            // Handle commands like the real swarm would
            $app->addHistory('command', $command);
            $app->updateProcessingMessage("Processing: {$command}");

            // Simulate processing delay
            usleep(500000); // 0.5 seconds

            // Generate a response
            $responses = [
                "I'll help you with that request.",
                'Let me analyze the code and provide a solution.',
                "I'll implement the requested changes.",
                'I need to examine the current implementation first.',
                "I'll create the necessary files and modifications.",
            ];

            $app->addHistory('assistant', $responses[array_rand($responses)]);
            $app->updateProcessingMessage('Ready');
        });

        // Initial layout and render
        $bounds = new Rect(0, 0, $terminalSize->width, $terminalSize->height);
        $app->layout($bounds);

        // Force initial paint and present
        $app->paint($context);
        echo $frameBuffer->present();

        $running = true;
        $lastRenderTime = 0;
        $frameTime = 16; // 16ms = ~60fps
        $minFrameGap = 3; // Minimum 3ms between renders to prevent stress flicker

        // Main event loop
        while ($running) {
            $now = microtime(true) * 1000; // Get time in milliseconds

            // Handle input
            $key = SwarmMockTerminal::readKey();
            if ($key !== null) {
                if ($key === 'q' || $key === "\003") { // 'q' or Ctrl+C
                    $running = false;
                    break;
                }

                if ($key === 'ALT+Q') {
                    $running = false;
                    break;
                }

                // Handle input
                $app->handleKeyEvent($key);
            }

            // Check for terminal resize
            $newSize = SwarmMockTerminal::getTerminalSize();
            if ($newSize->width !== $terminalSize->width || $newSize->height !== $terminalSize->height) {
                $terminalSize = $newSize;
                $frameBuffer->resize($terminalSize);
                $context = new BuildContext($terminalSize, canvas: $frameBuffer->getBackBuffer());
                $bounds = new Rect(0, 0, $terminalSize->width, $terminalSize->height);
                $app->layout($bounds);

                // Force repaint for resize
                $frameBuffer->getBackBuffer()->clear();
                $app->paint($context);
                echo $frameBuffer->present();
                $lastRenderTime = $now;
            } elseif ($app->needsRepaint()) {
                // Frame budget: only render if enough time has passed
                $timeSinceLastRender = $now - $lastRenderTime;

                if ($timeSinceLastRender >= $minFrameGap) {
                    // Clear back buffer and paint
                    $frameBuffer->getBackBuffer()->clear();
                    $app->paint($context);

                    // Present changes
                    $output = $frameBuffer->present();
                    if (! empty($output)) {
                        echo $output;
                    }

                    $lastRenderTime = $now;
                }
            }

            // Cap frame rate to prevent excessive CPU usage
            usleep(1000); // 1ms sleep for input polling
        }
    } catch (Throwable $e) {
        SwarmMockTerminal::restore();
        echo 'Error: ' . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

/**
 * Show usage information
 */
function showSwarmMockUsage(): void
{
    echo "Swarm Mock Terminal UI\n";
    echo "======================\n";
    echo "\n";
    echo "This mock replicates the exact behavior of FullTerminalUI to test\n";
    echo "text wrapping, layout, and rendering issues.\n";
    echo "\n";
    echo "Navigation:\n";
    echo "  Tab              Switch between main/tasks/context\n";
    echo "  ⌥+1/2/3          Jump to main/tasks/context\n";
    echo "  Arrow keys       Navigate within focused area\n";
    echo "  h or ?           Show/hide help\n";
    echo "  ⌥+T              Toggle task overlay\n";
    echo "  ⌥+C              Clear history (main area only)\n";
    echo "  ⌥+R              Toggle thoughts/refresh display\n";
    echo "  q or ⌥+Q         Quit\n";
    echo "\n";
    echo "Focus Areas:\n";
    echo "  Main             Activity log with history and tool calls\n";
    echo "  Tasks            Task queue with status indicators\n";
    echo "  Context          Directory, files, and notes\n";
    echo "\n";
    echo "The mock uses the exact same rendering logic as FullTerminalUI\n";
    echo "to identify and fix text wrapping and positioning issues.\n";
    echo "\n";
    echo "Press Enter to start...\n";

    fgets(STDIN);
}

// Register cleanup handler
register_shutdown_function(function () {
    SwarmMockTerminal::restore();
});

// Handle signals for clean exit
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () {
        SwarmMockTerminal::restore();
        exit(0);
    });

    pcntl_signal(SIGTERM, function () {
        SwarmMockTerminal::restore();
        exit(0);
    });
}

// Main execution
if (php_sapi_name() !== 'cli') {
    echo "This demo must be run from the command line.\n";
    exit(1);
}

// Check if we should show usage
if (isset($argv[1]) && in_array($argv[1], ['--help', '-h', 'help'])) {
    showSwarmMockUsage();
} else {
    echo "Starting Swarm Mock UI...\n";
    echo "This replicates FullTerminalUI exactly to test rendering issues.\n";
    echo "Press ⌥+Q or 'q' to quit.\n";
    sleep(1);
}

// Run the mock
runSwarmMock();

// Cleanup
SwarmMockTerminal::restore();
echo "Swarm Mock ended. Framework testing complete.\n";
