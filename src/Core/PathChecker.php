<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Core;

use HelgeSverre\Swarm\Exceptions\PathNotAllowedException;
use InvalidArgumentException;

/**
 * Validates file system paths against project directory and allow-list
 */
class PathChecker
{
    protected string $projectPath;

    protected array $allowedPaths = [];

    public function __construct(string $projectPath, array $allowedPaths = [])
    {
        // Normalize project path
        $resolved = realpath($projectPath);
        if ($resolved === false) {
            throw new InvalidArgumentException("Project path does not exist: {$projectPath}");
        }

        $this->projectPath = rtrim($resolved, '/');
        $this->setAllowedPaths($allowedPaths);
    }

    /**
     * Check if a path is allowed (within project or in allow-list)
     */
    public function isAllowed(string $path): bool
    {
        $resolved = $this->resolvePath($path);

        if ($resolved === false) {
            return false;
        }

        // Always allow paths within project directory
        if (str_starts_with($resolved, $this->projectPath . '/') || $resolved === $this->projectPath) {
            return true;
        }

        // Check allow-list
        foreach ($this->allowedPaths as $allowedPath) {
            if (str_starts_with($resolved, $allowedPath . '/') || $resolved === $allowedPath) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate a path and throw exception if not allowed
     */
    public function validatePath(string $path): string
    {
        $resolved = $this->resolvePath($path);

        if ($resolved === false) {
            throw new PathNotAllowedException("Path does not exist or is not accessible: {$path}");
        }

        if (! $this->isAllowed($path)) {
            throw new PathNotAllowedException(
                "Path access denied. Only files within the project directory or allow-listed directories are permitted.\n" .
                "Project directory: {$this->projectPath}\n" .
                "Requested path: {$resolved}\n" .
                'Use /add-dir command to add trusted directories to the allow-list.'
            );
        }

        return $resolved;
    }

    /**
     * Add a directory to the allow-list
     */
    public function addAllowedPath(string $path): bool
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_dir($resolved)) {
            return false;
        }

        $normalized = rtrim($resolved, '/');
        if (! in_array($normalized, $this->allowedPaths, true)) {
            $this->allowedPaths[] = $normalized;
        }

        return true;
    }

    /**
     * Remove a directory from the allow-list
     */
    public function removeAllowedPath(string $path): bool
    {
        $resolved = realpath($path);
        if ($resolved === false) {
            return false;
        }

        $normalized = rtrim($resolved, '/');
        $index = array_search($normalized, $this->allowedPaths, true);

        if ($index !== false) {
            array_splice($this->allowedPaths, $index, 1);

            return true;
        }

        return false;
    }

    /**
     * Get all allowed paths (excluding project path)
     */
    public function getAllowedPaths(): array
    {
        return $this->allowedPaths;
    }

    /**
     * Get the project path
     */
    public function getProjectPath(): string
    {
        return $this->projectPath;
    }

    /**
     * Set allowed paths (for loading from state)
     */
    public function setAllowedPaths(array $paths): void
    {
        $this->allowedPaths = [];
        foreach ($paths as $path) {
            $resolved = realpath($path);
            if ($resolved !== false && is_dir($resolved)) {
                $this->allowedPaths[] = rtrim($resolved, '/');
            }
        }
    }

    /**
     * Resolve path using realpath() with proper error handling
     */
    protected function resolvePath(string $path): string|false
    {
        // Handle relative paths by making them absolute first
        if (! str_starts_with($path, '/')) {
            $path = $this->projectPath . '/' . ltrim($path, '/');
        }

        return realpath($path);
    }
}
