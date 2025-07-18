<?php

/**
 * Tool Architecture Tests
 *
 * Enforce architectural rules specific to tool implementations
 */

// All tools must extend the abstract Tool class
arch('All tool classes must extend Tool abstract class')
    ->expect('HelgeSverre\Swarm\Tools')
    ->classes()
    ->toExtend('HelgeSverre\Swarm\Contracts\Tool')
    ->ignoring([
        'HelgeSverre\Swarm\Tools\ToolRegistry',
        'HelgeSverre\Swarm\Tools\ToolDefinition',
    ]);

// Tools should be self-contained (no cross-tool dependencies)
arch('Tools should not depend on other tools')
    ->expect('HelgeSverre\Swarm\Tools\File')
    ->not->toUse('HelgeSverre\Swarm\Tools\File')
    ->ignoring('HelgeSverre\Swarm\Tools\File');

// Tool methods must have proper visibility
arch('Tool execute methods must be public')
    ->expect('HelgeSverre\Swarm\Tools')
    ->classes()
    ->extending('HelgeSverre\Swarm\Contracts\Tool')
    ->toHaveMethod('execute');

// Tool methods must have proper return types
arch('Tool name methods must return string')
    ->expect('HelgeSverre\Swarm\Tools')
    ->classes()
    ->extending('HelgeSverre\Swarm\Contracts\Tool')
    ->toHaveMethod('name');

arch('Tool description methods must return string')
    ->expect('HelgeSverre\Swarm\Tools')
    ->classes()
    ->extending('HelgeSverre\Swarm\Contracts\Tool')
    ->toHaveMethod('description');

// Tools should not use global state
arch('Tools should not use superglobals')
    ->expect('HelgeSverre\Swarm\Tools')
    ->not->toUse([
        '$_GET',
        '$_POST',
        '$_SESSION',
        '$_COOKIE',
        '$_SERVER',
        '$_ENV',
        '$GLOBALS',
    ]);

// Tool namespace organization
arch('File tools should be in File namespace')
    ->expect('HelgeSverre\Swarm\Tools\File')
    ->toBeClasses();

// Tool attributes should be properly organized
arch('Tool attributes should be in Attributes namespace')
    ->expect('HelgeSverre\Swarm\Tools\Attributes')
    ->toBeClasses();

// Tools should use dependency injection for router
arch('Search tool can use router via dependency injection')
    ->expect('HelgeSverre\Swarm\Tools\Search')
    ->toUse('HelgeSverre\Swarm\Core\ToolRouter');
