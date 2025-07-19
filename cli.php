<?php

/**
 * Swarm - A CLI tool for managing AI agents and tasks
 *
 * @author   Helge Sverre <helge.sverre@gmail.com>
 */
define('SWARM_START', microtime(true));

// Define the project root directory
define('SWARM_ROOT', __DIR__);
define('SWARM_VERSION', '1.0.0');

require __DIR__ . '/vendor/autoload.php';

use HelgeSverre\Swarm\CLI\Swarm;
use HelgeSverre\Swarm\Core\ExceptionHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;

// Set unlimited execution time for the main CLI process
set_time_limit(0);

// Ensure we can handle large memory usage
ini_set('memory_limit', '4G');

// Create logger for exception handler
$logger = null;
if ($_ENV['LOG_ENABLED'] ?? false) {
    try {
        $logger = new Logger('swarm');
        $logPath = $_ENV['LOG_PATH'] ?? 'storage/logs';

        if (! is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $logLevel = match (mb_strtolower($_ENV['LOG_LEVEL'] ?? 'info')) {
            'debug' => Level::Debug,
            'warning', 'warn' => Level::Warning,
            'error', 'err', 'danger' => Level::Error,
            default => Level::Info,
        };

        $logger->pushHandler(
            new RotatingFileHandler("{$logPath}/swarm.log", 7, $logLevel)
        );
    } catch (Exception $e) {
        // Ignore logging setup errors
    }
}

// Set up exception handler
$exceptionHandler = new ExceptionHandler($logger);
$exceptionHandler->register();

// Create and run the CLI
try {
    $cli = Swarm::createFromEnvironment();
    $cli->run();
} catch (Throwable $e) {
    // Let the exception handler deal with it
    throw $e;
}
