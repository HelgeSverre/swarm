<?php

namespace HelgeSverre\Swarm\Core;

use HelgeSverre\Swarm\Tools\Grep;
use HelgeSverre\Swarm\Tools\ReadFile;
use HelgeSverre\Swarm\Tools\Terminal;
use HelgeSverre\Swarm\Tools\WriteFile;

class Toolchain
{
    /**
     * Register all available tools with the executor
     */
    public static function registerAll(ToolExecutor $executor): void
    {
        // Register individual tool instances
        $executor->register(new ReadFile);
        $executor->register(new WriteFile);
        $executor->register(new Terminal);
        $executor->register(new Grep);
    }
}
