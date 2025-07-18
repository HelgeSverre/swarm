<?php

/**
 * Dependency Injection Architecture Tests
 *
 * Enforce proper dependency injection patterns
 */

// All dependencies should be injected via constructor
arch('Classes should use constructor injection')
    ->expect([
        'HelgeSverre\Swarm\Agent\CodingAgent',
        'HelgeSverre\Swarm\CLI\SwarmCLI',
        'HelgeSverre\Swarm\Core\ToolRouter',
        'HelgeSverre\Swarm\Tools\Search',
    ])
    ->toHaveConstructor();

// Logger should always be optional/nullable
arch('Logger dependency should be nullable')
    ->expect([
        'HelgeSverre\Swarm\Agent\CodingAgent',
        'HelgeSverre\Swarm\Core\ToolRouter',
    ])
    ->toHaveConstructor();

// No service locator pattern
arch('Should not use service locator pattern')
    ->expect('HelgeSverre\Swarm')
    ->not->toUse([
        'Container::get',
        'Container::make',
        'app()',
        'resolve()',
    ]);

// No static method calls for dependencies
arch('Should not use static dependency resolution')
    ->expect('HelgeSverre\Swarm')
    ->not->toUse([
        'getInstance',
        'getService',
        '::create',
    ])
    ->ignoring([
        'HelgeSverre\Swarm\Core\ToolRegistry::registerAll', // Static factory method is OK
        'HelgeSverre\Swarm\Agent\AgentResponse', // Static factory methods for value objects
        'HelgeSverre\Swarm\Core\ToolResponse', // Static factory methods for value objects
    ]);

// Dependency inversion principle - depend on abstractions
arch('Tools should depend on Tool abstraction')
    ->expect('HelgeSverre\Swarm\Tools')
    ->classes()
    ->toExtend('HelgeSverre\Swarm\Contracts\Tool')
    ->ignoring([
        'HelgeSverre\Swarm\Tools\ToolDefinition', // Value object
        'HelgeSverre\Swarm\Tools\ToolRegistry', // Registry class
    ]);

// No global state usage
arch('Should not use global state')
    ->expect('HelgeSverre\Swarm')
    ->not->toUse([
        'global',
        '$GLOBALS',
        'static::$',
    ]);

// Factories should be used appropriately
arch('ToolRegistry should act as factory')
    ->expect('HelgeSverre\Swarm\Core\ToolRegistry')
    ->toHaveMethod('registerAll');

// No hard-coded class instantiation in business logic
arch('Router should not hard-code tool instantiation')
    ->expect('HelgeSverre\Swarm\Core\ToolRouter')
    ->not->toUse('new')
    ->ignoring([
        'HelgeSverre\Swarm\Core\ToolResponse', // Creating response objects is OK
        'HelgeSverre\Swarm\Exceptions', // Creating exceptions is OK
    ]);
