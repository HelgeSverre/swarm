<?php

namespace HelgeSverre\Swarm\Tools;

use FilesystemIterator;
use HelgeSverre\Swarm\Router\ToolResponse;
use HelgeSverre\Swarm\Router\ToolRouter;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SearchTools
{
    public static function register(ToolRouter $router): void
    {
        // File/directory search with glob patterns
        $router->registerTool('find_files', function ($params) {
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
        });

        // Content search (grep-like)
        $router->registerTool('search_content', function ($params) use ($router) {
            $search = $params['search'] ?? throw new InvalidArgumentException('search required');
            $pattern = $params['pattern'] ?? '*';
            $directory = $params['directory'] ?? '.';
            $case_sensitive = $params['case_sensitive'] ?? false;

            $results = [];

            // First find files matching pattern
            $findResponse = $router->dispatch('find_files', ['pattern' => $pattern, 'directory' => $directory]);
            $files = $findResponse->getData()['files'];

            foreach ($files as $file) {
                if (is_file($file)) {
                    $content = file_get_contents($file);
                    $lines = explode("\n", $content);

                    foreach ($lines as $lineNum => $line) {
                        $pattern = $case_sensitive ? "/{$search}/" : "/{$search}/i";
                        if (preg_match($pattern, $line, $matches)) {
                            $results[] = [
                                'file' => $file,
                                'line' => $lineNum + 1,
                                'content' => trim($line),
                                'match' => $matches[0] ?? $search,
                            ];
                        }
                    }
                }
            }

            return ToolResponse::success([
                'search' => $search,
                'pattern' => $pattern,
                'results' => $results,
                'count' => count($results),
            ]);
        });
    }
}
