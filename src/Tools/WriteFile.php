<?php

namespace HelgeSverre\Swarm\Tools;

use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;

class WriteFile extends Tool
{
    public function name(): string
    {
        return 'write_file';
    }

    public function description(): string
    {
        return 'Write content to a file';
    }

    public function parameters(): array
    {
        return [
            'path' => [
                'type' => 'string',
                'description' => 'Path where the file should be written',
            ],
            'content' => [
                'type' => 'string',
                'description' => 'Content to write to the file',
            ],
            'backup' => [
                'type' => 'boolean',
                'description' => 'Whether to create a backup of existing file',
                'default' => true,
            ],
        ];
    }

    public function required(): array
    {
        return ['path', 'content'];
    }

    public function execute(array $params): ToolResponse
    {
        $path = $params['path'] ?? throw new InvalidArgumentException('path required');
        $content = $params['content'] ?? throw new InvalidArgumentException('content required');
        $backup = $params['backup'] ?? true;

        // Backup existing file if requested
        if ($backup && file_exists($path)) {
            copy($path, $path . '.backup.' . time());
        }

        $bytes = file_put_contents($path, $content);

        return ToolResponse::success([
            'path' => $path,
            'bytes_written' => $bytes,
            'backup_created' => $backup && file_exists($path . '.backup.' . time()),
        ]);
    }
}
