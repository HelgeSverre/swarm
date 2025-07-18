<?php

/**
 * Value Object Architecture Tests
 *
 * Enforce immutability and proper value object patterns
 */

// Response objects should be immutable
arch('Response objects should not have setters')
    ->expect([
        'HelgeSverre\Swarm\Agent\AgentResponse',
        'HelgeSverre\Swarm\Core\ToolResponse',
    ])
    ->not->toHavePublicMethodsBesides([
        'success',
        'error',
        'isSuccess',
        'getData',
        'getError',
        'getMessage',
        'toArray',
    ]);

// Value objects should use static factory methods
arch('Response objects should use static factory methods')
    ->expect([
        'HelgeSverre\Swarm\Agent\AgentResponse',
        'HelgeSverre\Swarm\Core\ToolResponse',
    ])
    ->toHaveMethod('success');

// Tool definitions should be immutable
arch('ToolDefinition should be immutable')
    ->expect('HelgeSverre\Swarm\Tools\ToolDefinition')
    ->toBeClass()
    ->not->toHavePublicMethodsBesides([
        'getName',
        'getDescription',
        'getParameters',
        'toArray',
    ]);

// Value objects should not depend on services
arch('Value objects should not have service dependencies')
    ->expect([
        'HelgeSverre\Swarm\Agent\AgentResponse',
        'HelgeSverre\Swarm\Core\ToolResponse',
        'HelgeSverre\Swarm\Tools\ToolDefinition',
    ])
    ->not->toUse([
        'HelgeSverre\Swarm\Core\ToolRouter',
        'HelgeSverre\Swarm\Agent\CodingAgent',
        'Psr\Log\LoggerInterface',
    ]);

// Attributes should be value objects
arch('Tool attributes should be immutable')
    ->expect('HelgeSverre\Swarm\Tools\Attributes')
    ->toBeClasses();

// No mutable state in value objects
arch('Response objects should have protected properties')
    ->expect([
        'HelgeSverre\Swarm\Agent\AgentResponse',
        'HelgeSverre\Swarm\Core\ToolResponse',
    ])
    ->toBeClasses();

// Value objects should be self-contained
arch('Value objects should not use external services')
    ->expect([
        'HelgeSverre\Swarm\Agent\AgentResponse',
        'HelgeSverre\Swarm\Core\ToolResponse',
        'HelgeSverre\Swarm\Tools\ToolDefinition',
    ])
    ->not->toUse([
        'file_get_contents',
        'file_put_contents',
        'curl_',
        'fopen',
        'DB::',
    ]);
