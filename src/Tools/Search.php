<?php

namespace HelgeSverre\Swarm\Tools;

use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use HelgeSverre\Swarm\Core\ToolRouter;
use InvalidArgumentException;

class Search extends Tool
{
    public function __construct(
        private ToolRouter $router
    ) {}

    public function name(): string
    {
        return 'search_content';
    }

    public function description(): string
    {
        return 'Search for content in files';
    }

    public function parameters(): array
    {
        return [
            'search' => [
                'type' => 'string',
                'description' => 'The text to search for',
            ],
            'pattern' => [
                'type' => 'string',
                'description' => 'File pattern to search within (e.g., *.php)',
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
        ];
    }

    public function required(): array
    {
        return ['search'];
    }

    public function execute(array $params): ToolResponse
    {
        $search = $params['search'] ?? throw new InvalidArgumentException('search required');
        $pattern = $params['pattern'] ?? '*';
        $directory = $params['directory'] ?? '.';
        $caseSensitive = $params['case_sensitive'] ?? false;

        $results = [];

        // First find files matching pattern
        $findResponse = $this->router->dispatch('find_files', [
            'pattern' => $pattern,
            'directory' => $directory,
        ]);
        $files = $findResponse->getData()['files'];

        foreach ($files as $file) {
            if (is_file($file)) {
                $content = file_get_contents($file);
                $lines = explode("\n", $content);

                foreach ($lines as $lineNum => $line) {
                    $regexPattern = $caseSensitive ? "/{$search}/" : "/{$search}/i";
                    if (preg_match($regexPattern, $line, $matches)) {
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
    }
}
