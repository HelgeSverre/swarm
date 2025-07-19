<?php

namespace HelgeSverre\Swarm\Tools\Tavily;

use HelgeSverre\Swarm\Core\AbstractToolkit;
use InvalidArgumentException;

class TavilyToolkit extends AbstractToolkit
{
    public function __construct(
        protected readonly ?string $apiKey = null
    ) {}

    /**
     * Provide all Tavily tools
     */
    public function provide(): array
    {
        $apiKey = $this->apiKey ?? $_ENV['TAVILY_API_KEY'] ?? getenv('TAVILY_API_KEY');

        if (! $apiKey) {
            throw new InvalidArgumentException('Tavily API key is required. Set TAVILY_API_KEY in your .env file.');
        }

        return [
            new TavilySearchTool($apiKey),
            new TavilyExtractTool($apiKey),
        ];
    }

    /**
     * Provide usage guidelines for Tavily tools
     */
    public function guidelines(): ?string
    {
        return <<<'GUIDELINES'
Tavily Tools Usage Guidelines:

1. **tavily_search**: Use this tool to search for recent and accurate information from the web.
   - Best for: Current events, research topics, fact-checking
   - Supports time filtering for recent results
   - Returns both a direct answer and source links

2. **tavily_extract**: Use this tool to extract clean content from a specific URL.
   - Best for: Reading full articles, documentation pages
   - Returns content in markdown format
   - Useful after finding relevant URLs with tavily_search

Workflow example:
1. Use tavily_search to find relevant sources
2. Use tavily_extract on the most relevant URLs to get full content
GUIDELINES;
    }
}
