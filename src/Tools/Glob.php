<?php

namespace HelgeSverre\Swarm\Tools;

use Exception;
use FilesystemIterator;
use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Glob extends Tool
{
    public function __construct(
        protected readonly int $maxResults = 10000,
        protected readonly array $excludePatterns = ['.git', 'node_modules', 'vendor', '.cache', 'tmp']
    ) {}

    public function name(): string
    {
        return 'glob';
    }

    public function description(): string
    {
        return 'Fast file pattern matching tool that works with any codebase size. Supports glob patterns like "**/*.js" or "src/**/*.ts". Returns matching file paths sorted by modification time.';
    }

    public function parameters(): array
    {
        return [
            'pattern' => [
                'type' => 'string',
                'description' => 'The glob pattern to match files against (e.g., "*.js", "src/**/*.ts")',
            ],
            'path' => [
                'type' => 'string',
                'description' => 'The directory to search in. If not specified, the current working directory will be used.',
            ],
        ];
    }

    public function required(): array
    {
        return ['pattern'];
    }

    public function execute(array $params): ToolResponse
    {
        $pattern = $params['pattern'] ?? throw new InvalidArgumentException('pattern is required');
        $path = $params['path'] ?? getcwd();

        // Validate and normalize the search path
        $searchPath = $this->validateAndNormalizePath($path);
        if (! $searchPath) {
            return ToolResponse::error("Invalid or inaccessible path: {$path}");
        }

        // Validate the pattern
        if (! $this->isValidGlobPattern($pattern)) {
            return ToolResponse::error("Invalid glob pattern: {$pattern}");
        }

        try {
            $files = $this->findFiles($pattern, $searchPath);

            // Sort by modification time (newest first)
            usort($files, fn (array $a, array $b) => $b['mtime'] <=> $a['mtime']);

            // Extract just the paths for the response
            $filePaths = array_column($files, 'path');

            return ToolResponse::success([
                'pattern' => $pattern,
                'path' => $searchPath,
                'files' => $filePaths,
                'count' => count($filePaths),
                'truncated' => count($filePaths) >= $this->maxResults,
            ]);
        } catch (Exception $e) {
            return ToolResponse::error("Failed to search files: {$e->getMessage()}");
        }
    }

    protected function validateAndNormalizePath(string $path): ?string
    {
        // Resolve the real path to prevent directory traversal
        $realPath = realpath($path);

        if (! $realPath || ! is_dir($realPath) || ! is_readable($realPath)) {
            return null;
        }

        // Ensure we stay within reasonable bounds (no going above project root)
        $cwd = getcwd();
        if ($cwd && ! str_starts_with($realPath, $cwd)) {
            // Allow absolute paths but be cautious about system directories
            $systemDirs = ['/etc', '/usr', '/var', '/sys', '/proc', '/dev'];
            foreach ($systemDirs as $sysDir) {
                if (str_starts_with($realPath, $sysDir)) {
                    return null;
                }
            }
        }

        return $realPath;
    }

    protected function isValidGlobPattern(string $pattern): bool
    {
        // Basic validation to prevent dangerous patterns
        if (empty($pattern) || mb_strlen($pattern) > 1000) {
            return false;
        }

        // Prevent patterns that could be problematic
        $dangerous = ['../', '../', '\\', '${', '`'];
        foreach ($dangerous as $danger) {
            if (str_contains($pattern, $danger)) {
                return false;
            }
        }

        return true;
    }

    protected function findFiles(string $pattern, string $searchPath): array
    {
        $files = [];
        $count = 0;

        // Always search recursively (like Claude Code's Glob)
        // Use recursive search for both ** patterns and simple patterns
        $files = $this->findFilesRecursive($pattern, $searchPath, $count);

        return array_slice($files, 0, $this->maxResults);
    }

    protected function findFilesRecursive(string $pattern, string $searchPath, int &$count): array
    {
        $files = [];

        // Convert glob pattern to regex for recursive search
        $regexPattern = $this->globToRegex($pattern);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($searchPath, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($count >= $this->maxResults) {
                break;
            }

            if (! $fileInfo->isFile()) {
                continue;
            }

            // Skip excluded directories
            if ($this->shouldSkipPath($fileInfo->getPath())) {
                continue;
            }

            // For pattern matching, determine if it's a recursive pattern or simple pattern
            if (str_contains($pattern, '**')) {
                // Use regex for recursive patterns
                $relativePath = $this->getRelativePath($fileInfo->getPathname(), $searchPath);

                if (preg_match($regexPattern, $relativePath)) {
                    $files[] = [
                        'path' => $fileInfo->getPathname(),
                        'mtime' => $fileInfo->getMTime(),
                    ];
                    $count++;
                }
            } else {
                // Use fnmatch for simple patterns (just match filename)
                // Handle brace expansion for patterns like *.{ts,tsx}
                if ($this->matchesPattern($pattern, $fileInfo->getFilename())) {
                    $files[] = [
                        'path' => $fileInfo->getPathname(),
                        'mtime' => $fileInfo->getMTime(),
                    ];
                    $count++;
                }
            }
        }

        return $files;
    }

    protected function findFilesNonRecursive(string $pattern, string $searchPath, int &$count): array
    {
        $files = [];
        $fullPattern = rtrim($searchPath, '/') . '/' . $pattern;

        $globResults = glob($fullPattern, GLOB_NOSORT);
        if ($globResults === false) {
            return $files;
        }

        foreach ($globResults as $file) {
            if ($count >= $this->maxResults) {
                break;
            }

            if (is_file($file)) {
                $files[] = [
                    'path' => $file,
                    'mtime' => filemtime($file),
                ];
                $count++;
            }
        }

        return $files;
    }

    protected function globToRegex(string $pattern): string
    {
        // Convert glob pattern to regex
        $regex = preg_quote($pattern, '/');

        // Replace escaped glob patterns with regex equivalents
        $regex = str_replace([
            '\*\*\/\*',  // **/* becomes recursive match
            '\*\*',      // ** becomes recursive match
            '\*',        // * becomes single level match
            '\?',        // ? becomes single character
        ], [
            '.*',        // **/* matches anything recursively
            '.*',        // ** matches anything recursively
            '[^/]*',     // * matches anything except path separator
            '[^/]',      // ? matches single character except path separator
        ], $regex);

        return '/^' . $regex . '$/';
    }

    protected function getRelativePath(string $fullPath, string $basePath): string
    {
        $basePath = rtrim($basePath, '/') . '/';

        if (str_starts_with($fullPath, $basePath)) {
            return mb_substr($fullPath, mb_strlen($basePath));
        }

        return $fullPath;
    }

    protected function shouldSkipPath(string $path): bool
    {
        foreach ($this->excludePatterns as $excludePattern) {
            if (str_contains($path, "/{$excludePattern}/") || str_ends_with($path, "/{$excludePattern}")) {
                return true;
            }
        }

        return false;
    }

    protected function matchesPattern(string $pattern, string $filename): bool
    {
        // Handle brace expansion patterns like *.{ts,tsx}
        if (str_contains($pattern, '{') && str_contains($pattern, '}')) {
            return $this->matchesBracePattern($pattern, $filename);
        }

        // Use standard fnmatch for simple patterns
        return fnmatch($pattern, $filename);
    }

    protected function matchesBracePattern(string $pattern, string $filename): bool
    {
        // Extract the brace content
        $braceStart = mb_strpos($pattern, '{');
        $braceEnd = mb_strpos($pattern, '}');

        if ($braceStart === false || $braceEnd === false || $braceEnd <= $braceStart) {
            return fnmatch($pattern, $filename);
        }

        $prefix = mb_substr($pattern, 0, $braceStart);
        $suffix = mb_substr($pattern, $braceEnd + 1);
        $braceContent = mb_substr($pattern, $braceStart + 1, $braceEnd - $braceStart - 1);

        // Split by comma and test each option
        $options = explode(',', $braceContent);
        foreach ($options as $option) {
            $expandedPattern = $prefix . trim($option) . $suffix;
            if (fnmatch($expandedPattern, $filename)) {
                return true;
            }
        }

        return false;
    }
}
