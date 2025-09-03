<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Manual includes for the TUI lib classes
require_once __DIR__ . '/Core/Constraints.php';
require_once __DIR__ . '/Core/BuildContext.php';
require_once __DIR__ . '/Core/Widget.php';
require_once __DIR__ . '/Core/Canvas.php';
require_once __DIR__ . '/Core/RenderPipeline.php';
require_once __DIR__ . '/Focus/FocusNode.php';
require_once __DIR__ . '/Focus/FocusScope.php';
require_once __DIR__ . '/Focus/FocusManager.php';
require_once __DIR__ . '/App/MockData.php';
require_once __DIR__ . '/App/ActivityLog.php';
require_once __DIR__ . '/App/TaskList.php';
require_once __DIR__ . '/App/SwarmApp.php';

use Examples\TuiLib\App\Activity;
use Examples\TuiLib\App\ActivityType;
use Examples\TuiLib\App\SwarmApp;
use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Size;

/**
 * Terminal utilities for the demo
 */
class TerminalDemo
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

                return $seq;
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
 * Activity simulator for generating realistic activities
 */
class ActivitySimulator
{
    protected SwarmApp $app;

    protected float $lastActivityTime = 0;

    protected int $activityCounter = 0;

    public function __construct(SwarmApp $app)
    {
        $this->app = $app;
        $this->lastActivityTime = microtime(true);
    }

    /**
     * Update simulator - maybe generate new activities
     */
    public function update(): void
    {
        $now = microtime(true);

        // Generate new activity every 5-15 seconds
        if ($now - $this->lastActivityTime > rand(5, 15)) {
            $this->generateActivity();
            $this->lastActivityTime = $now;
        }
    }

    /**
     * Generate a realistic activity
     */
    protected function generateActivity(): void
    {
        $commands = [
            'find . -name "*.php" -type f',
            'grep -r "function" src/',
            'composer update',
            'php artisan cache:clear',
            'npm run build',
            'git status',
            'docker-compose up -d',
            'php vendor/bin/phpunit',
            'tail -f storage/logs/laravel.log',
            'mysql -u root -p database',
        ];

        $responses = [
            'Found 23 PHP files in project',
            'Search completed: 45 matches found',
            'Dependencies updated successfully',
            'Application cache cleared',
            'Build completed in 2.3s',
            'Working directory clean',
            'Containers started successfully',
            'Tests passed: 15/15',
            'Log monitoring active',
            'Database connection established',
        ];

        $errors = [
            'File not found: config/app.php',
            'Syntax error in routes/web.php:42',
            'Connection timeout to database',
            'Permission denied: storage/logs',
            'Invalid JSON in composer.json',
            'Port 3306 already in use',
            'Class not found: App\\Models\\User',
        ];

        $type = ActivityType::cases()[array_rand(ActivityType::cases())];

        $message = match ($type) {
            ActivityType::Command => $commands[array_rand($commands)],
            ActivityType::Response => $responses[array_rand($responses)],
            ActivityType::Error => $errors[array_rand($errors)],
            ActivityType::Success => 'Operation completed successfully',
            ActivityType::Warning => 'Deprecated method used: ' . $commands[array_rand($commands)],
            ActivityType::Info => 'System information: ' . $responses[array_rand($responses)],
        };

        $activity = new Activity(
            id: 'sim_' . ++$this->activityCounter,
            type: $type,
            message: $message,
            timestamp: new DateTimeImmutable,
            metadata: ['source' => 'simulator']
        );

        $this->app->addActivity($activity);
    }
}

/**
 * Main demo application
 */
function runDemo(): void
{
    try {
        // Initialize terminal
        TerminalDemo::initialize();

        // Create and setup the app
        $terminalSize = TerminalDemo::getTerminalSize();
        $context = new BuildContext($terminalSize);
        $app = new SwarmApp('demo_app');

        // Setup activity simulator
        $simulator = new ActivitySimulator($app);

        // Initial layout and render
        $bounds = new Examples\TuiLib\Core\Rect(1, 1, $terminalSize->width, $terminalSize->height);
        $app->layout($bounds);

        echo $app->paint($context);

        $running = true;
        $lastUpdate = microtime(true);
        $frameCount = 0;

        // Main event loop
        while ($running) {
            $now = microtime(true);

            // Update simulator
            $simulator->update();

            // Handle input
            $key = TerminalDemo::readKey();
            if ($key !== null) {
                if ($key === 'q' || $key === "\003") { // 'q' or Ctrl+C
                    $running = false;
                    break;
                }

                if ($app->isShowingHelp() && $key !== 'h' && $key !== '?') {
                    // Close help on any key
                    $app->toggleHelp();
                } else {
                    // Handle normal input
                    $app->handleKeyEvent($key);
                }
            }

            // Check for terminal resize
            $newSize = TerminalDemo::getTerminalSize();
            if ($newSize->width !== $terminalSize->width || $newSize->height !== $terminalSize->height) {
                $terminalSize = $newSize;
                $context = new BuildContext($terminalSize);
                $bounds = new Examples\TuiLib\Core\Rect(1, 1, $terminalSize->width, $terminalSize->height);
                $app->layout($bounds);

                // Clear screen and repaint
                echo "\033[2J\033[H";
                echo $app->paint($context);
            } elseif ($app->needsRepaint()) {
                // Repaint if needed
                echo $app->paint($context);
            }

            $frameCount++;

            // Update status every 60 frames (roughly 1 second)
            if ($frameCount % 60 === 0) {
                $stats = $app->getStats();
                $fps = intval(60 / max(0.001, $now - $lastUpdate));
                $app->setStatusMessage(
                    "Activities: {$stats['activities']} | " .
                    "Tasks: {$stats['tasks']['completed']}/{$stats['tasks']['total']} | " .
                    "FPS: {$fps}"
                );
                $lastUpdate = $now;
            }

            // Small delay to prevent 100% CPU usage
            usleep(16667); // ~60 FPS
        }
    } catch (Throwable $e) {
        TerminalDemo::restore();
        echo 'Error: ' . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

/**
 * Show usage information
 */
function showUsage(): void
{
    echo "Swarm TUI Framework Demo\n";
    echo "========================\n";
    echo "\n";
    echo "This demo showcases the terminal UI framework with:\n";
    echo "- Activity log with real-time updates\n";
    echo "- Task list with filtering and navigation\n";
    echo "- Focus management between panels\n";
    echo "- Responsive layout and keyboard controls\n";
    echo "\n";
    echo "Navigation:\n";
    echo "  Tab/Shift+Tab    Switch between panels\n";
    echo "  Arrow keys       Navigate within panels\n";
    echo "  h or ?           Show/hide help\n";
    echo "  q                Quit demo\n";
    echo "\n";
    echo "Press Enter to start the demo...\n";

    fgets(STDIN);
}

// Register cleanup handler
register_shutdown_function(function () {
    TerminalDemo::restore();
});

// Handle signals for clean exit
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () {
        TerminalDemo::restore();
        exit(0);
    });

    pcntl_signal(SIGTERM, function () {
        TerminalDemo::restore();
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
    showUsage();
} else {
    echo "Starting Swarm TUI Demo...\n";
    echo "Press Ctrl+C or 'q' to quit.\n";
    sleep(1);
}

// Run the demo
runDemo();

// Cleanup
TerminalDemo::restore();
echo "Demo ended. Thanks for trying the Swarm TUI Framework!\n";
