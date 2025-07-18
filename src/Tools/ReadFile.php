<?php

namespace HelgeSverre\Swarm\Tools;

use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;

class ReadFile extends Tool
{
    public function name(): string
    {
        return 'read_file';
    }

    public function description(): string
    {
        return 'Read contents of a file from the filesystem';
    }

    public function parameters(): array
    {
        return [
            'path' => [
                'type' => 'string',
                'description' => 'Path to the file to read',
            ],
        ];
    }

    public function required(): array
    {
        return ['path'];
    }

    public function execute(array $params): ToolResponse
    {
        $path = $params['path'] ?? throw new InvalidArgumentException('path required');

        if (! file_exists($path)) {
            return ToolResponse::error("File not found: {$path}");
        }

        $content = file_get_contents($path);

        return ToolResponse::success([
            'path' => $path,
            'content' => $content,
            'size' => mb_strlen($content),
            'lines' => mb_substr_count($content, "\n") + 1,
        ]);
    }
}
