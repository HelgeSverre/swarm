<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Exceptions;

use Exception;

/**
 * Base exception for configuration-related errors
 */
class ConfigurationException extends Exception
{
    protected int $exitCode = 1;

    /**
     * Get the exit code for this exception
     */
    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
