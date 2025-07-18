<?php

namespace HelgeSverre\Swarm\Router;

use Exception;
use HelgeSverre\Swarm\Exceptions\ToolNotFoundException;
use Psr\Log\LoggerInterface;

class ToolRouter
{
    protected $tools = [];

    protected $executionLog = [];

    protected ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function registerTool(string $name, callable $handler): self
    {
        $this->tools[$name] = $handler;
        $this->logger?->info('Tool registered', ['tool' => $name]);

        return $this;
    }

    public function dispatch(string $tool, array $params): ToolResponse
    {
        $startTime = microtime(true);
        $logId = uniqid();

        if (! isset($this->tools[$tool])) {
            $availableTools = array_keys($this->tools);
            $this->logger?->error('Tool not found', [
                'tool' => $tool,
                'available_tools' => $availableTools,
            ]);
            throw new ToolNotFoundException("Tool '{$tool}' not found");
        }

        // Log the execution start
        $this->logExecution($logId, $tool, $params, 'started');
        $this->logger?->info('Tool dispatch started', [
            'tool' => $tool,
            'log_id' => $logId,
            'params' => $this->sanitizeParams($params),
        ]);

        try {
            $response = $this->tools[$tool]($params);
            $duration = microtime(true) - $startTime;
            $this->logExecution($logId, $tool, $params, 'completed', $response, $duration);

            $this->logger?->info('Tool dispatch completed', [
                'tool' => $tool,
                'log_id' => $logId,
                'duration_ms' => round($duration * 1000, 2),
                'success' => $response->isSuccess(),
            ]);

            // Log warning for slow operations
            if ($duration > 5) {
                $this->logger?->warning('Slow tool execution', [
                    'tool' => $tool,
                    'duration_seconds' => round($duration, 2),
                ]);
            }

            return $response;
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logExecution($logId, $tool, $params, 'failed', null, $duration, $e);

            $this->logger?->error('Tool dispatch failed', [
                'tool' => $tool,
                'log_id' => $logId,
                'duration_ms' => round($duration * 1000, 2),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function getExecutionLog(): array
    {
        return $this->executionLog;
    }

    protected function logExecution(string $id, string $tool, array $params, string $status, $response = null, float $duration = 0, ?Exception $error = null): void
    {
        $this->executionLog[] = [
            'id' => $id,
            'tool' => $tool,
            'params' => $params,
            'status' => $status,
            'response' => $response,
            'duration' => $duration,
            'error' => $error?->getMessage(),
            'timestamp' => time(),
        ];
    }

    protected function sanitizeParams(array $params): array
    {
        // Remove or mask sensitive data from params for logging
        $sanitized = $params;

        // Common sensitive keys to mask
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'auth'];

        foreach ($sanitized as $key => $value) {
            if (in_array(mb_strtolower($key), $sensitiveKeys)) {
                $sanitized[$key] = '***REDACTED***';
            }
        }

        return $sanitized;
    }
}
