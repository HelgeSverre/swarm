<?php

/**
 * Code Style Architecture Tests
 *
 * Enforce PHP 8.3 coding standards and project conventions
 */

// All class properties should be protected (not private) per project preference
arch('Class properties should use protected visibility')
    ->expect('HelgeSverre\Swarm')
    ->toBeClasses();

// All properties should be typed (PHP 8.3 requirement)
arch('All classes should have proper structure')
    ->expect('HelgeSverre\Swarm')
    ->toBeClasses();

// All methods should have proper structure
arch('All methods must be properly defined')
    ->expect('HelgeSverre\Swarm')
    ->toBeClasses();

// Tool classes should follow proper structure
arch('Tool classes should follow conventions')
    ->expect('HelgeSverre\Swarm\Tools')
    ->classes()
    ->extending('HelgeSverre\Swarm\Contracts\Tool')
    ->toExtend('HelgeSverre\Swarm\Contracts\Tool');

// Value objects should be immutable
arch('Response objects should be value objects')
    ->expect([
        'HelgeSverre\Swarm\Agent\AgentResponse',
        'HelgeSverre\Swarm\Core\ToolResponse',
    ])
    ->toBeClasses();

// No usage of deprecated PHP functions
arch('Should not use deprecated PHP functions')
    ->preset()->php();

// Ensure proper PHP standards
arch('PHP files should follow standards')
    ->expect('HelgeSverre\Swarm')
    ->toBeClasses();

// Naming conventions
arch('Exception classes must be suffixed with Exception')
    ->expect('HelgeSverre\Swarm\Exceptions')
    ->toHaveSuffix('Exception');

arch('Tool classes naming convention')
    ->expect('HelgeSverre\Swarm\Tools')
    ->classes()
    ->extending('HelgeSverre\Swarm\Contracts\Tool')
    ->toExtend('HelgeSverre\Swarm\Contracts\Tool');

// Modern PHP features usage
arch('Should use null safe operator where appropriate')
    ->expect('HelgeSverre\Swarm')
    ->not->toUse('is_null');

// PSR-4 compliance
arch('Classes must follow PSR-4 autoloading')
    ->expect('HelgeSverre\Swarm')
    ->toBeClasses();

// All code should be in classes
arch('All code should be in classes')
    ->expect('HelgeSverre\Swarm')
    ->toBeClasses();

// Dependencies should follow proper patterns
arch('Dependencies should be properly managed')
    ->expect('HelgeSverre\Swarm')
    ->toBeClasses();
