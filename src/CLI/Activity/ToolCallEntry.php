<?php

namespace HelgeSverre\Swarm\CLI\Activity;

use HelgeSverre\Swarm\Core\ToolResponse;
use HelgeSverre\Swarm\Enums\CLI\ActivityType;

/**
 * Represents a tool call activity entry
 *
 * Displays information about tool executions in a human-readable format
 */
class ToolCallEntry extends ActivityEntry
{
    public function __construct(
        public readonly string $toolName,
        public readonly array $params,
        public readonly ?ToolResponse $response,
        int $timestamp,
    ) {
        parent::__construct(ActivityType::Tool, $timestamp);
    }

    /**
     * Get the formatted message for display
     */
    public function getMessage(): string
    {
        $message = $this->formatToolCall();

        // Add result summary if available
        if ($this->response !== null && $this->response->isSuccess()) {
            $summary = $this->summarizeResult();
            if ($summary) {
                $message .= " → {$summary}";
            }
        }

        return $message;
    }

    /**
     * Get a short summary of parameters for display
     */
    public function getParamsSummary(): string
    {
        $summary = [];

        foreach ($this->params as $key => $value) {
            if (is_string($value) && mb_strlen($value) > 50) {
                $value = mb_substr($value, 0, 47) . '...';
            }
            $summary[] = "{$key}: " . json_encode($value);
        }

        return implode(', ', $summary);
    }

    /**
     * Format the tool call based on tool name and parameters
     */
    protected function formatToolCall(): string
    {
        return match ($this->toolName) {
            'write_file' => $this->formatWriteFile(),
            'read_file' => $this->formatReadFile(),
            'bash' => $this->formatBash(),
            'grep' => $this->formatGrep(),
            default => $this->formatGeneric(),
        };
    }

    /**
     * Format write_file tool call
     */
    protected function formatWriteFile(): string
    {
        $path = $this->params['path'] ?? 'unknown';
        $filename = basename($path);

        return "📝 write_file: {$filename}";
    }

    /**
     * Format read_file tool call
     */
    protected function formatReadFile(): string
    {
        $path = $this->params['path'] ?? 'unknown';
        $filename = basename($path);

        return "📖 read_file: {$filename}";
    }

    /**
     * Format bash tool call
     */
    protected function formatBash(): string
    {
        $command = $this->params['command'] ?? 'command';

        // Truncate long commands
        if (mb_strlen($command) > 80) {
            $command = mb_substr($command, 0, 77) . '...';
        }

        return "⚡ bash: {$command}";
    }

    /**
     * Format grep tool call
     */
    protected function formatGrep(): string
    {
        $search = $this->params['search'] ?? null;
        $pattern = $this->params['pattern'] ?? null;
        $directory = $this->params['directory'] ?? '.';

        $what = $search ?? $pattern ?? 'pattern';
        $where = basename($directory);

        return "🔍 grep: '{$what}' in {$where}";
    }

    /**
     * Format generic tool call
     */
    protected function formatGeneric(): string
    {
        return "🔧 {$this->toolName}";
    }

    /**
     * Summarize the result of the tool call
     */
    protected function summarizeResult(): ?string
    {
        if ($this->response === null || ! $this->response->isSuccess()) {
            return null;
        }

        $data = $this->response->getData();

        return match ($this->toolName) {
            'write_file' => isset($data['bytes_written']) ? "{$data['bytes_written']} bytes" : 'done',
            'read_file' => isset($data['content']) ? mb_strlen($data['content']) . ' chars' : 'read',
            'bash' => $this->summarizeBashResult($data),
            'grep' => $this->summarizeGrepResult($data),
            default => 'completed',
        };
    }

    /**
     * Summarize bash command result
     */
    protected function summarizeBashResult(array $data): string
    {
        $returnCode = $data['return_code'] ?? null;

        if ($returnCode !== null) {
            return $returnCode === 0 ? 'success' : "exit {$returnCode}";
        }

        return 'executed';
    }

    /**
     * Summarize grep result
     */
    protected function summarizeGrepResult(array $data): string
    {
        $count = $data['count'] ?? 0;

        if (isset($data['files']) && is_array($data['files'])) {
            $fileCount = count($data['files']);

            return "{$fileCount} files";
        }

        if (isset($data['matches']) && is_array($data['matches'])) {
            $matchCount = count($data['matches']);

            return "{$matchCount} matches";
        }

        return $count > 0 ? "{$count} results" : 'no matches';
    }
}
