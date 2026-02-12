<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

/**
 * Immutable response object from agent processing
 * 
 * Uses modern PHP patterns:
 * - Readonly properties for immutability
 * - Constructor property promotion
 * - Strong typing with union types
 * - Named constructors for clarity
 */
readonly class AgentResponse
{
    public function __construct(
        public string $content,
        public bool $success = true,
        public array $metadata = [],
        public ?string $error = null,
        public ?float $processingTime = null,
        public array $toolCalls = [],
        public array $classificationData = []
    ) {}

    /**
     * Create successful response
     */
    public static function success(
        string $content, 
        array $metadata = [],
        ?float $processingTime = null
    ): self {
        return new self(
            content: $content,
            success: true,
            metadata: $metadata,
            processingTime: $processingTime
        );
    }

    /**
     * Create error response
     */
    public static function error(
        string $error,
        ?string $partialContent = null,
        array $metadata = []
    ): self {
        return new self(
            content: $partialContent ?? "Error: $error",
            success: false,
            metadata: $metadata,
            error: $error
        );
    }

    /**
     * Create response from tool execution
     */
    public static function fromToolExecution(
        string $content,
        array $toolCalls,
        bool $success = true,
        ?float $processingTime = null
    ): self {
        return new self(
            content: $content,
            success: $success,
            toolCalls: $toolCalls,
            processingTime: $processingTime
        );
    }

    /**
     * Get response message (backward compatibility)
     */
    public function getMessage(): string
    {
        return $this->content;
    }

    /**
     * Check if response was successful (backward compatibility)
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get metadata value by key
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if response has tool calls
     */
    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    /**
     * Get tool call count
     */
    public function getToolCallCount(): int
    {
        return count($this->toolCalls);
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'success' => $this->success,
            'metadata' => $this->metadata,
            'error' => $this->error,
            'processing_time' => $this->processingTime,
            'tool_calls' => $this->toolCalls,
            'classification_data' => $this->classificationData,
        ];
    }

    /**
     * Create from array (for deserialization)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'] ?? $data['message'] ?? '', // Support old 'message' key
            success: $data['success'] ?? false,
            metadata: $data['metadata'] ?? [],
            error: $data['error'] ?? null,
            processingTime: $data['processing_time'] ?? null,
            toolCalls: $data['tool_calls'] ?? [],
            classificationData: $data['classification_data'] ?? []
        );
    }

    /**
     * Create enhanced response with classification
     */
    public static function withClassification(
        string $content,
        array $classificationData,
        bool $success = true,
        array $metadata = []
    ): self {
        return new self(
            content: $content,
            success: $success,
            metadata: $metadata,
            classificationData: $classificationData
        );
    }
}