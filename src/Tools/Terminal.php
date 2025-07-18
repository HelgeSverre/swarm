<?php

namespace HelgeSverre\Swarm\Tools;

use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;

class Terminal extends Tool
{
    public function name(): string
    {
        return 'bash';
    }

    public function description(): string
    {
        return 'Execute bash commands in a terminal';
    }

    public function parameters(): array
    {
        return [
            'command' => [
                'type' => 'string',
                'description' => 'The bash command to execute',
            ],
            'timeout' => [
                'type' => 'number',
                'description' => 'Command timeout in seconds',
                'default' => 30,
            ],
            'directory' => [
                'type' => 'string',
                'description' => 'Working directory for the command',
                'default' => getcwd(),
            ],
        ];
    }

    public function required(): array
    {
        return ['command'];
    }

    public function execute(array $params): ToolResponse
    {
        $command = $params['command'] ?? throw new InvalidArgumentException('command required');
        $timeout = $params['timeout'] ?? 30;
        $directory = $params['directory'] ?? getcwd();

        // Change to specified directory
        $oldCwd = getcwd();
        chdir($directory);

        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (is_resource($process)) {
            // Close stdin
            fclose($pipes[0]);

            // Read stdout and stderr
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            // Restore original directory
            chdir($oldCwd);

            return ToolResponse::success([
                'command' => $command,
                'directory' => $directory,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'return_code' => $returnCode,
                'success' => $returnCode === 0,
            ]);
        }

        chdir($oldCwd);

        return ToolResponse::error("Failed to execute command: {$command}");
    }
}
