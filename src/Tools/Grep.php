<?php

namespace HelgeSverre\Swarm\Tools;

use Exception;
use FilesystemIterator;
use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;

class Grep extends Tool
{
    public function __construct(
        protected readonly int $maxResults = 1000,
        protected readonly int $maxFileSize = 10 * 1024 * 1024, // 10MB
        protected readonly array $excludePatterns = ['.git', 'node_modules', 'vendor', '.cache', 'tmp'],
        protected readonly bool $preferNativeRipgrep = true
    ) {}

    public function name(): string
    {
        return 'grep';
    }

    public function description(): string
    {
        return 'Fast content search tool that works with any codebase size. Searches file contents using regular expressions. Supports full regex syntax and returns file paths with at least one match sorted by modification time.';
    }

    public function parameters(): array
    {
        return [
            'pattern' => [
                'type' => 'string',
                'description' => 'The regular expression pattern to search for in file contents (e.g., "log.*Error", "function\\s+\\w+")',
            ],
            'path' => [
                'type' => 'string',
                'description' => 'The directory to search in. Defaults to the current working directory.',
            ],
            'include' => [
                'type' => 'string',
                'description' => 'File pattern to include in the search (e.g., "*.js", "*.{ts,tsx}")',
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
        $include = $params['include'] ?? null;

        // Validate and normalize the search path
        $searchPath = $this->validateAndNormalizePath($path);
        if (! $searchPath) {
            return ToolResponse::error("Invalid or inaccessible path: {$path}");
        }

        // Validate the regex pattern
        if (! $this->isValidRegexPattern($pattern)) {
            return ToolResponse::error("Invalid or potentially dangerous regex pattern: {$pattern}");
        }

        try {
            // Try native ripgrep first if available and preferred
            if ($this->preferNativeRipgrep && $this->isRipgrepAvailable()) {
                $result = $this->searchWithRipgrep($pattern, $searchPath, $include);
            } else {
                $result = $this->searchWithPHP($pattern, $searchPath, $include);
            }

            return $result;
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

        // Ensure we stay within reasonable bounds
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

    protected function isValidRegexPattern(string $pattern): bool
    {
        // Basic validation
        if (empty($pattern) || mb_strlen($pattern) > 1000) {
            return false;
        }

        // Test if it's a valid regex
        if (@preg_match("/{$pattern}/", '') === false) {
            return false;
        }

        // Check for catastrophic backtracking patterns (ReDoS)
        $dangerousPatterns = [
            '(.*)+',
            '(.+)+',
            '(a+)+',
            '(a*)*',
            '(x+x+)+',
            '([a-zA-Z]+)*',
        ];

        foreach ($dangerousPatterns as $dangerous) {
            if (str_contains($pattern, $dangerous)) {
                return false;
            }
        }

        return true;
    }

    protected function isRipgrepAvailable(): bool
    {
        static $available = null;

        if ($available === null) {
            $process = new Process(['which', 'rg']);
            $process->run();
            $available = $process->isSuccessful();
        }

        return $available;
    }

    protected function searchWithRipgrep(string $pattern, string $searchPath, ?string $include): ToolResponse
    {
        $command = ['rg', '--files-with-matches', '--no-heading', '--no-line-number'];

        // Add include pattern if specified
        if ($include) {
            $command[] = '--glob';
            $command[] = $include;
        }

        // Add exclude patterns
        foreach ($this->excludePatterns as $exclude) {
            $command[] = '--glob';
            $command[] = "!{$exclude}";
        }

        $command[] = $pattern;
        $command[] = $searchPath;

        $process = new Process($command);
        $process->setTimeout(30); // 30 second timeout
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Ripgrep failed: ' . $process->getErrorOutput());
        }

        $output = trim($process->getOutput());
        $files = $output ? explode("\n", $output) : [];

        // Sort by modification time (newest first)
        $filesWithMtime = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $filesWithMtime[] = [
                    'path' => $file,
                    'mtime' => filemtime($file),
                ];
            }
        }

        usort($filesWithMtime, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
        $sortedFiles = array_column($filesWithMtime, 'path');

        // Limit results
        $sortedFiles = array_slice($sortedFiles, 0, $this->maxResults);

        return ToolResponse::success([
            'pattern' => $pattern,
            'path' => $searchPath,
            'include' => $include,
            'files' => $sortedFiles,
            'count' => count($sortedFiles),
            'truncated' => count($files) > $this->maxResults,
            'method' => 'ripgrep',
        ]);
    }

    protected function searchWithPHP(string $pattern, string $searchPath, ?string $include): ToolResponse
    {
        $matchingFiles = [];
        $fileCount = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($searchPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileCount >= $this->maxResults) {
                break;
            }

            if (! $fileInfo->isFile() || ! $fileInfo->isReadable()) {
                continue;
            }

            // Skip if file is too large
            if ($fileInfo->getSize() > $this->maxFileSize) {
                continue;
            }

            // Skip excluded paths
            if ($this->shouldSkipPath($fileInfo->getPath())) {
                continue;
            }

            // Check include pattern if specified
            if ($include && ! fnmatch($include, $fileInfo->getFilename())) {
                continue;
            }

            // Search file content
            if ($this->fileContainsPattern($fileInfo->getPathname(), $pattern)) {
                $matchingFiles[] = [
                    'path' => $fileInfo->getPathname(),
                    'mtime' => $fileInfo->getMTime(),
                ];
                $fileCount++;
            }
        }

        // Sort by modification time (newest first)
        usort($matchingFiles, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
        $filePaths = array_column($matchingFiles, 'path');

        return ToolResponse::success([
            'pattern' => $pattern,
            'path' => $searchPath,
            'include' => $include,
            'files' => $filePaths,
            'count' => count($filePaths),
            'truncated' => $fileCount >= $this->maxResults,
            'method' => 'php',
        ]);
    }

    protected function fileContainsPattern(string $filePath, string $pattern): bool
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return false;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if (preg_match("/{$pattern}/", $line)) {
                    return true;
                }
            }
        } finally {
            fclose($handle);
        }

        return false;
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
}
