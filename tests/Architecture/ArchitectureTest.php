<?php

use HelgeSverre\Swarm\Agent;
use HelgeSverre\Swarm\CLI;
use HelgeSverre\Swarm\Core;
use HelgeSverre\Swarm\Task;
use HelgeSverre\Swarm\Tools;

/**
 * Layer Boundary Tests
 *
 * Enforce architectural boundaries between layers to maintain separation of concerns
 */

// CLI layer should not directly depend on Tools layer
arch('CLI layer should not directly use Tools')
    ->expect('HelgeSverre\Swarm\CLI')
    ->not->toUse('HelgeSverre\Swarm\Tools');

// Tools should be self-contained and not depend on higher layers
arch('Tools should not depend on CLI or Agent layers')
    ->expect('HelgeSverre\Swarm\Tools')
    ->not->toUse([
        'HelgeSverre\Swarm\CLI',
        'HelgeSverre\Swarm\Agent',
    ]);

// Core layer (Router) should only be used by Agent layer
arch('Core Router should only be used by Agent layer')
    ->expect('HelgeSverre\Swarm\Core\ToolRouter')
    ->toOnlyBeUsedIn([
        'HelgeSverre\Swarm\Agent',
        'HelgeSverre\Swarm\CLI\SwarmCLI', // CLI needs to inject it
        'HelgeSverre\Swarm\CLI\AsyncProcessor', // AsyncProcessor needs it for async operations
        'HelgeSverre\Swarm\Core\ToolRegistry', // Registry needs to use it
        'HelgeSverre\Swarm\Tools\Search', // Search tool needs router for sub-searches
    ]);

// Agent layer dependencies
arch('Agent layer should not depend on CLI layer')
    ->expect('HelgeSverre\Swarm\Agent')
    ->not->toUse('HelgeSverre\Swarm\CLI');

// Task layer should be independent
arch('Task layer should not depend on other layers except Core')
    ->expect('HelgeSverre\Swarm\Task')
    ->not->toUse([
        'HelgeSverre\Swarm\CLI',
        'HelgeSverre\Swarm\Agent',
        'HelgeSverre\Swarm\Tools',
    ]);

// Exception classes should be in Exceptions namespace
arch('Exception classes must be in Exceptions namespace')
    ->expect('HelgeSverre\Swarm\Exceptions')
    ->toBeClasses();

// Response objects should be in their respective layers
arch('Response objects should be properly placed')
    ->expect('HelgeSverre\Swarm\Agent\AgentResponse')
    ->toBeClasses()
    ->expect('HelgeSverre\Swarm\Core\ToolResponse')
    ->toBeClasses();
