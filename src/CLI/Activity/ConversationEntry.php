<?php

namespace HelgeSverre\Swarm\CLI\Activity;

use HelgeSverre\Swarm\Enums\CLI\ActivityType;

/**
 * Represents a conversation entry (user or agent message)
 *
 * Handles both plain text messages and structured content like
 * function calls, plans, and other JSON-encoded data
 */
class ConversationEntry extends ActivityEntry
{
    private ?array $parsedContent = null;

    public function __construct(
        public readonly string $role,
        public readonly string $content,
        int $timestamp,
    ) {
        $type = $role === 'user' ? ActivityType::User : ActivityType::Agent;
        parent::__construct($type, $timestamp);

        $this->parseContent();
    }

    /**
     * Get the formatted message for display
     */
    public function getMessage(): string
    {
        // If it's parsed JSON content, format it appropriately
        if ($this->parsedContent !== null) {
            return $this->formatParsedContent();
        }

        // Otherwise return the raw content
        return $this->content;
    }

    /**
     * Check if this entry contains a function call
     */
    public function isFunctionCall(): bool
    {
        return $this->parsedContent !== null && isset($this->parsedContent['function_call']);
    }

    /**
     * Get the function name if this is a function call
     */
    public function getFunctionName(): ?string
    {
        if ($this->isFunctionCall()) {
            return $this->parsedContent['function_call']['name'] ?? null;
        }

        return null;
    }

    /**
     * Parse JSON content if applicable
     */
    private function parseContent(): void
    {
        if (str_starts_with(trim($this->content), '{') || str_starts_with(trim($this->content), '[')) {
            $decoded = json_decode($this->content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->parsedContent = $decoded;
            }
        }
    }

    /**
     * Format parsed JSON content into human-readable text
     */
    private function formatParsedContent(): string
    {
        // Handle function calls
        if (isset($this->parsedContent['function_call'])) {
            return $this->formatFunctionCall($this->parsedContent['function_call']);
        }

        // Handle plan data
        if (isset($this->parsedContent['plan_summary'])) {
            return $this->formatPlan($this->parsedContent);
        }

        // Handle arrays (like file lists)
        if (is_array($this->parsedContent) && isset($this->parsedContent[0]) && is_string($this->parsedContent[0])) {
            return $this->formatList($this->parsedContent);
        }

        // Default to truncated JSON
        return mb_substr($this->content, 0, 100) . '...';
    }

    /**
     * Format a function call into readable text
     */
    private function formatFunctionCall(array $functionCall): string
    {
        $name = $functionCall['name'] ?? 'unknown';
        $args = $functionCall['arguments'] ?? '';

        // Parse nested JSON arguments if present
        if (is_string($args)) {
            $parsedArgs = json_decode($args, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $args = $parsedArgs;
            }
        }

        // Format based on function name
        return match ($name) {
            'write_file' => sprintf('📝 Writing to %s', $this->extractPath($args)),
            'read_file' => sprintf('📖 Reading %s', $this->extractPath($args)),
            'bash' => sprintf('⚡ Running: %s', $this->extractCommand($args)),
            'grep' => sprintf('🔍 Searching: %s', $this->extractSearchTerm($args)),
            default => sprintf('🔧 Calling %s', $name),
        };
    }

    /**
     * Format a plan into readable text
     */
    private function formatPlan(array $plan): string
    {
        $summary = $plan['plan_summary'] ?? 'Planning task execution';
        $stepCount = isset($plan['steps']) ? count($plan['steps']) : 0;

        return sprintf('📋 Plan: %s (%d steps)',
            mb_substr($summary, 0, 50) . (mb_strlen($summary) > 50 ? '...' : ''),
            $stepCount
        );
    }

    /**
     * Format a list (like file paths) into readable text
     */
    private function formatList(array $list): string
    {
        $count = count($list);

        // Extract filenames from paths if they look like file paths
        $items = array_map(function ($item) {
            if (is_string($item) && str_contains($item, '/')) {
                return basename($item);
            }

            return $item;
        }, array_slice($list, 0, 3));

        $preview = implode(', ', $items);
        if ($count > 3) {
            $preview .= sprintf(' (+%d more)', $count - 3);
        }

        return sprintf('📄 Listed %d items: %s', $count, $preview);
    }

    /**
     * Extract path from function arguments
     */
    private function extractPath($args): string
    {
        if (is_array($args) && isset($args['path'])) {
            return basename($args['path']);
        }
        if (is_string($args)) {
            return 'file';
        }

        return 'unknown';
    }

    /**
     * Extract command from bash arguments
     */
    private function extractCommand($args): string
    {
        if (is_array($args) && isset($args['command'])) {
            $cmd = $args['command'];

            return mb_strlen($cmd) > 40 ? mb_substr($cmd, 0, 37) . '...' : $cmd;
        }

        return 'command';
    }

    /**
     * Extract search term from grep arguments
     */
    private function extractSearchTerm($args): string
    {
        if (is_array($args)) {
            return $args['search'] ?? $args['pattern'] ?? 'pattern';
        }

        return 'pattern';
    }
}
