<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Exceptions;

/**
 * Exception thrown when environment file cannot be loaded or validated
 */
class EnvironmentLoadException extends ConfigurationException
{
    public function __construct(string $message)
    {
        parent::__construct('Error loading environment: ' . $message);
    }
}
