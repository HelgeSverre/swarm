<?php

namespace HelgeSverre\Swarm\Tools;

use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\PathChecker;
use HelgeSverre\Swarm\Core\ToolResponse;
use HelgeSverre\Swarm\Exceptions\PathNotAllowedException;
use InvalidArgumentException;

class WriteFile extends Tool
{
    public function __construct(
        protected readonly ?PathChecker $pathChecker = null
    ) {}

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

        // Validate path if PathChecker is available
        if ($this->pathChecker) {
            try {
                $validatedPath = $this->pathChecker->validatePath($path);
                $path = $validatedPath;
            } catch (PathNotAllowedException $e) {
                return ToolResponse::error($e->getMessage());
            }
        }

        $bytes = file_put_contents($path, $content);

        if ($bytes === false) {
            return ToolResponse::error("Failed to write file: {$path}");
        }

        return ToolResponse::success([
            'path' => $path,
            'bytes_written' => $bytes,
        ]);
    }
}
