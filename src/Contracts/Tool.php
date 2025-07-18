<?php

namespace HelgeSverre\Swarm\Contracts;

use HelgeSverre\Swarm\Core\ToolResponse;

abstract class Tool
{
    /**
     * The tool's unique name
     */
    abstract public function name(): string;

    /**
     * The tool's description for AI understanding
     */
    abstract public function description(): string;

    /**
     * Define the tool's parameters for OpenAI function calling
     */
    abstract public function parameters(): array;

    /**
     * Required parameters for this tool
     */
    abstract public function required(): array;

    /**
     * Execute the tool with given parameters
     */
    abstract public function execute(array $params): ToolResponse;

    /**
     * Get the OpenAI function schema for this tool
     */
    public function toOpenAISchema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => $this->parameters(),
                'required' => $this->required(),
            ],
        ];
    }

    /**
     * Create a callable wrapper for router registration
     */
    public function toCallable(): callable
    {
        return fn (array $params) => $this->execute($params);
    }
}
