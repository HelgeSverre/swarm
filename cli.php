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

use HelgeSverre\Swarm\CLI\SwarmCLI;

// Create and run the CLI
$cli = new SwarmCLI;
$cli->run();
