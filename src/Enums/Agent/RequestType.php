<?php

namespace HelgeSverre\Swarm\Enums\Agent;

/**
 * Types of requests that the agent can handle
 */
enum RequestType: string
{
    /**
     * Check if this request type typically requires tools
     */
    public function requiresTools(): bool
    {
        return match ($this) {
            self::Implementation => true,
            self::Demonstration, self::Explanation, self::Query, self::Conversation => false,
        };
    }

    /**
     * Get a description of this request type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Demonstration => 'Code demonstration or example',
            self::Implementation => 'Task implementation requiring tools',
            self::Explanation => 'Explanation or educational content',
            self::Query => 'Information query',
            self::Conversation => 'General conversation',
        };
    }

    /**
     * Create from string with null fallback
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Get all values as strings for JSON schema
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    case Demonstration = 'demonstration';
    case Implementation = 'implementation';
    case Explanation = 'explanation';
    case Query = 'query';
    case Conversation = 'conversation';
}
