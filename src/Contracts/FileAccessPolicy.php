<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Contracts;

interface FileAccessPolicy
{
    /**
     * Validate a readable file path and return its normalized absolute path.
     */
    public function validateReadPath(string $path): string;

    /**
     * Validate a writable file path and return its normalized absolute path.
     *
     * The file itself may not exist yet, but the parent directory must exist and be allowed.
     */
    public function validateWritePath(string $path): string;

    /**
     * Validate a searchable directory path and return its normalized absolute path.
     */
    public function validateSearchPath(string $path): string;
}
