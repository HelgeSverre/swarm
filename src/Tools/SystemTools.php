<?php

namespace HelgeSverre\Swarm\Tools;

use HelgeSverre\Swarm\Router\ToolResponse;
use HelgeSverre\Swarm\Router\ToolRouter;
use InvalidArgumentException;

class SystemTools
{
    public static function register(ToolRouter $router): void
    {
        // Execute bash commands
        $router->registerTool('bash', function ($params) {
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
        });
    }
}
