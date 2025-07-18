<?php

namespace HelgeSverre\Swarm\Tools;

use FilesystemIterator;
use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FindFiles extends Tool
{
    public function name(): string
    {
        return 'find_files';
    }

    public function description(): string
    {
        return 'Find files matching a pattern';
    }

    public function parameters(): array
    {
        return [
            'pattern' => [
                'type' => 'string',
                'description' => 'File pattern to search for (e.g., *.php)',
                'default' => '*',
            ],
            'directory' => [
                'type' => 'string',
                'description' => 'Directory to search in',
                'default' => '.',
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
        return [];
    }

    public function execute(array $params): ToolResponse
    {
        $pattern = $params['pattern'] ?? '*';
        $directory = $params['directory'] ?? '.';
        $recursive = $params['recursive'] ?? true;

        $flags = $recursive ? GLOB_BRACE : 0;
        $searchPattern = rtrim($directory, '/') . '/' . $pattern;

        if ($recursive) {
            // For recursive search, we need to implement it manually
            $files = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (fnmatch($pattern, $file->getFilename())) {
                    $files[] = $file->getPathname();
                }
            }
        } else {
            $files = glob($searchPattern, $flags);
        }

        return ToolResponse::success([
            'pattern' => $pattern,
            'directory' => $directory,
            'files' => $files ?: [],
            'count' => count($files ?: []),
        ]);
    }
}
