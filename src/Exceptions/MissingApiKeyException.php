<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Exceptions;

/**
 * Exception thrown when OpenAI API key is not configured
 */
class MissingApiKeyException extends ConfigurationException
{
    public function __construct()
    {
        parent::__construct(
            "OpenAI API key not found.\n" .
            'Please create a .env file with OPENAI_API_KEY or set it in your environment'
        );
    }
}
