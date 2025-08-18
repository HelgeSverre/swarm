<?php

namespace HelgeSverre\Swarm\Tools;

use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\PathChecker;
use HelgeSverre\Swarm\Core\ToolResponse;
use HelgeSverre\Swarm\Exceptions\PathNotAllowedException;
use InvalidArgumentException;

class ReadFile extends Tool
{
    public function __construct(
        protected readonly ?PathChecker $pathChecker = null
    ) {}

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

        // Validate path if PathChecker is available
        if ($this->pathChecker) {
            try {
                $validatedPath = $this->pathChecker->validatePath($path);
                $path = $validatedPath;
            } catch (PathNotAllowedException $e) {
                return ToolResponse::error($e->getMessage());
            }
        }

        if (! file_exists($path)) {
            return ToolResponse::error("File not found: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return ToolResponse::error("Failed to read file: {$path}");
        }

        return ToolResponse::success([
            'path' => $path,
            'content' => $content,
            'size' => mb_strlen($content),
            'lines' => mb_substr_count($content, "\n") + 1,
        ]);
    }
}
