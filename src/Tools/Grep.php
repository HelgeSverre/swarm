<?php

namespace HelgeSverre\Swarm\Tools;

use FilesystemIterator;
use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Grep extends Tool
{
    public function name(): string
    {
        return 'grep';
    }

    public function description(): string
    {
        return 'Search for content in files, optionally filtering by filename pattern';
    }

    public function parameters(): array
    {
        return [
            'search' => [
                'type' => 'string',
                'description' => 'The text/pattern to search for in file contents',
            ],
            'pattern' => [
                'type' => 'string',
                'description' => 'File pattern to search within (e.g., *.php, *.txt)',
                'default' => '*',
            ],
            'directory' => [
                'type' => 'string',
                'description' => 'Directory to search in',
                'default' => '.',
            ],
            'case_sensitive' => [
                'type' => 'boolean',
                'description' => 'Whether the search is case sensitive',
                'default' => false,
            ],
            'files_only' => [
                'type' => 'boolean',
                'description' => 'Only return matching filenames without searching content',
                'default' => false,
            ],
            'recursive' => [
                'type' => 'boolean',
                'description' => 'Search recursively in subdirectories',
                'default' => true,
            ],
        ];
    }

    public function required(): array
    {
        // If files_only is true, we don't need a search term
        return [];
    }

    public function execute(array $params): ToolResponse
    {
        $search = $params['search'] ?? null;
        $pattern = $params['pattern'] ?? '*';
        $directory = $params['directory'] ?? '.';
        $caseSensitive = $params['case_sensitive'] ?? false;
        $filesOnly = $params['files_only'] ?? false;
        $recursive = $params['recursive'] ?? true;

        // If files_only and no search term, just find files
        if ($filesOnly && ! $search) {
            return $this->findFiles($pattern, $directory, $recursive);
        }

        // If no search term for content search, error
        if (! $filesOnly && ! $search) {
            throw new InvalidArgumentException('search parameter required for content search');
        }

        // Find matching files first
        $files = $this->getMatchingFiles($pattern, $directory, $recursive);

        // If files_only, filter by search term in filename
        if ($filesOnly) {
            $filteredFiles = [];
            $searchRegex = $caseSensitive ? "/{$search}/" : "/{$search}/i";
            foreach ($files as $file) {
                if (preg_match($searchRegex, basename($file))) {
                    $filteredFiles[] = $file;
                }
            }

            return ToolResponse::success([
                'search' => $search,
                'pattern' => $pattern,
                'directory' => $directory,
                'files' => $filteredFiles,
                'count' => count($filteredFiles),
            ]);
        }

        // Search content in files
        $results = [];
        $searchRegex = $caseSensitive ? "/{$search}/" : "/{$search}/i";

        foreach ($files as $file) {
            if (! is_file($file) || ! is_readable($file)) {
                continue;
            }

            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $lineNum => $line) {
                if (preg_match($searchRegex, $line, $matches)) {
                    $results[] = [
                        'file' => $file,
                        'line' => $lineNum + 1,
                        'content' => trim($line),
                        'match' => $matches[0] ?? $search,
                    ];
                }
            }
        }

        return ToolResponse::success([
            'search' => $search,
            'pattern' => $pattern,
            'directory' => $directory,
            'results' => $results,
            'count' => count($results),
            'files_searched' => count($files),
        ]);
    }

    protected function findFiles(string $pattern, string $directory, bool $recursive): ToolResponse
    {
        $files = $this->getMatchingFiles($pattern, $directory, $recursive);

        return ToolResponse::success([
            'pattern' => $pattern,
            'directory' => $directory,
            'files' => $files,
            'count' => count($files),
        ]);
    }

    protected function getMatchingFiles(string $pattern, string $directory, bool $recursive): array
    {
        $files = [];

        if (! is_dir($directory)) {
            return $files;
        }

        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                    $files[] = $file->getPathname();
                }
            }
        } else {
            $searchPattern = rtrim($directory, '/') . '/' . $pattern;
            $globFiles = glob($searchPattern);
            if ($globFiles !== false) {
                foreach ($globFiles as $file) {
                    if (is_file($file)) {
                        $files[] = $file;
                    }
                }
            }
        }

        return $files;
    }
}
