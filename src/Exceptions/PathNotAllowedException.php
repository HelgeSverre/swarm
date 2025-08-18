<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Exceptions;

use Exception;

/**
 * Exception thrown when file access is blocked by path restrictions
 */
class PathNotAllowedException extends Exception
{
    public function __construct(string $message = 'File access denied by path restrictions', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
