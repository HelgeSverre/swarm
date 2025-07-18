<?php

namespace HelgeSverre\Swarm;

use Exception;

class ToolRouter
{
    protected $tools = [];
    protected $executionLog = [];

    public function registerTool(string $name, callable $handler): self
    {
        $this->tools[$name] = $handler;
        return $this;
    }

    public function dispatch(string $tool, array $params): ToolResponse
    {
        $startTime = microtime(true);
        $logId = uniqid();

        if (!isset($this->tools[$tool])) {
            throw new Exceptions\ToolNotFoundException("Tool '$tool' not found");
        }

        // Log the execution start
        $this->logExecution($logId, $tool, $params, 'started');

        try {
            $response = $this->tools[$tool]($params);
            $this->logExecution($logId, $tool, $params, 'completed', $response, microtime(true) - $startTime);
            return $response;
        } catch (Exception $e) {
            $this->logExecution($logId, $tool, $params, 'failed', null, microtime(true) - $startTime, $e);
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
            'timestamp' => time()
        ];
    }
}