<?php

namespace HelgeSverre\Swarm\Tools;

use HelgeSverre\Swarm\Router\ToolRouter;

class ToolRegistry
{
    /**
     * Register all available tools with the router
     */
    public static function registerAll(ToolRouter $router): void
    {
        FileTools::register($router);
        SearchTools::register($router);
        SystemTools::register($router);
    }
}
