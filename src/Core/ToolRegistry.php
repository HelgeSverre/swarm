<?php

namespace HelgeSverre\Swarm\Core;

use HelgeSverre\Swarm\Tools\FindFiles;
use HelgeSverre\Swarm\Tools\ReadFile;
use HelgeSverre\Swarm\Tools\Search;
use HelgeSverre\Swarm\Tools\Terminal;
use HelgeSverre\Swarm\Tools\WriteFile;

class ToolRegistry
{
    /**
     * Register all available tools with the router
     */
    public static function registerAll(ToolRouter $router): void
    {
        // Register individual tool instances
        $router->register(new ReadFile);
        $router->register(new WriteFile);
        $router->register(new Terminal);
        $router->register(new FindFiles);

        // Search tool needs router injected
        $router->register(new Search($router));
    }
}
