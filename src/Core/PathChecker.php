<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Core;

use HelgeSverre\Swarm\Contracts\FileAccessPolicy;
use HelgeSverre\Swarm\Exceptions\PathNotAllowedException;
use InvalidArgumentException;

/**
 * Validates file system paths against project directory and allow-list
 */
class PathChecker implements FileAccessPolicy
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

        return $this->isResolvedPathAllowed($resolved);
    }

    /**
     * Validate a path and throw exception if not allowed
     */
    public function validatePath(string $path): string
    {
        return $this->validateReadPath($path);
    }

    public function validateReadPath(string $path): string
    {
        $resolved = $this->resolvePath($path);

        if ($resolved === false) {
            throw new PathNotAllowedException("Path does not exist or is not accessible: {$path}");
        }

        if (! $this->isResolvedPathAllowed($resolved)) {
            throw $this->pathAccessDenied($resolved);
        }

        return $resolved;
    }

    public function validateWritePath(string $path): string
    {
        $normalized = $this->normalizePath($path);
        $existingTarget = realpath($normalized);

        if ($existingTarget !== false) {
            if (! $this->isResolvedPathAllowed($existingTarget)) {
                throw $this->pathAccessDenied($existingTarget);
            }

            return $existingTarget;
        }

        $parentDirectory = realpath(dirname($normalized));

        if ($parentDirectory === false || ! is_dir($parentDirectory) || ! is_writable($parentDirectory)) {
            throw new PathNotAllowedException('Parent directory does not exist or is not writable: ' . dirname($normalized));
        }

        if (! $this->isResolvedPathAllowed($parentDirectory)) {
            throw $this->pathAccessDenied($normalized);
        }

        return $normalized;
    }

    public function validateSearchPath(string $path): string
    {
        $resolved = $this->resolvePath($path);

        if ($resolved === false || ! is_dir($resolved)) {
            throw new PathNotAllowedException("Path does not exist or is not accessible: {$path}");
        }

        if (! $this->isResolvedPathAllowed($resolved)) {
            throw $this->pathAccessDenied($resolved);
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

    protected function normalizePath(string $path): string
    {
        $absolute = str_starts_with($path, '/')
            ? $path
            : $this->projectPath . '/' . ltrim($path, '/');

        $segments = explode('/', str_replace('\\', '/', $absolute));
        $normalized = [];

        foreach ($segments as $index => $segment) {
            if ($segment === '' && $index === 0) {
                continue;
            }

            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalized);

                continue;
            }

            $normalized[] = $segment;
        }

        return '/' . implode('/', $normalized);
    }

    protected function isResolvedPathAllowed(string $resolved): bool
    {
        if (str_starts_with($resolved, $this->projectPath . '/') || $resolved === $this->projectPath) {
            return true;
        }

        foreach ($this->allowedPaths as $allowedPath) {
            if (str_starts_with($resolved, $allowedPath . '/') || $resolved === $allowedPath) {
                return true;
            }
        }

        return false;
    }

    protected function pathAccessDenied(string $requestedPath): PathNotAllowedException
    {
        return new PathNotAllowedException(
            "Path access denied. Only files within the project directory or allow-listed directories are permitted.\n" .
            "Project directory: {$this->projectPath}\n" .
            "Requested path: {$requestedPath}\n" .
            'Use /add-dir command to add trusted directories to the allow-list.'
        );
    }
}
