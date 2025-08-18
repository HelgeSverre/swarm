<?php

/**
 * Application bootstrap
 *
 * @author   Helge Sverre <helge.sverre@gmail.com>
 */

// Load composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

use HelgeSverre\Swarm\Core\Application;

// Create and return the Application instance
// Pass project directory from global variable set in CLI
$projectDir = $GLOBALS['projectDir'] ?? getcwd();

return new Application(dirname(__DIR__), $projectDir);
