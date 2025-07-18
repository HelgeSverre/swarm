<?php

namespace HelgeSverre\Swarm\Tools;

use HelgeSverre\Swarm\Router\ToolResponse;
use HelgeSverre\Swarm\Router\ToolRouter;
use InvalidArgumentException;

class FileTools
{
    public static function register(ToolRouter $router): void
    {
        // File reading
        $router->registerTool('read_file', function ($params) {
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
        });

        // File writing
        $router->registerTool('write_file', function ($params) {
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
        });
    }
}
