<?php

namespace HelgeSverre\Swarm\Tools\Tavily;

use Exception;
use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TavilyExtractTool extends Tool
{
    protected const EXTRACT_ENDPOINT = 'https://api.tavily.com/extract';

    protected ?HttpClientInterface $httpClient = null;

    public function __construct(
        protected readonly string $apiKey
    ) {}

    public function name(): string
    {
        return 'tavily_extract';
    }

    public function description(): string
    {
        return 'Extract clean content from a URL and return it in markdown format';
    }

    public function parameters(): array
    {
        return [
            'url' => [
                'type' => 'string',
                'description' => 'The URL to extract content from',
            ],
        ];
    }

    public function required(): array
    {
        return ['url'];
    }

    public function execute(array $params): ToolResponse
    {
        $url = $params['url'] ?? throw new InvalidArgumentException('url is required');

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return ToolResponse::error("Invalid URL: {$url}");
        }

        try {
            $client = $this->getClient();

            $requestData = [
                'urls' => [$url],
                'api_key' => $this->apiKey,
            ];

            $response = $client->request('POST', self::EXTRACT_ENDPOINT, [
                'json' => $requestData,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray();

            if ($statusCode !== 200) {
                return ToolResponse::error("API returned status {$statusCode}: " . ($data['error'] ?? 'Unknown error'));
            }

            // Extract content from the first result
            $results = $data['results'] ?? [];
            if (empty($results)) {
                return ToolResponse::error('No content extracted from URL');
            }

            $firstResult = $results[0] ?? [];
            $markdown = $firstResult['raw_content'] ?? $firstResult['content'] ?? '';

            if (empty($markdown)) {
                return ToolResponse::error('Failed to extract content from URL');
            }

            return ToolResponse::success([
                'url' => $url,
                'markdown' => $markdown,
                'title' => $firstResult['title'] ?? null,
            ]);
        } catch (Exception $e) {
            return ToolResponse::error("Failed to extract content: {$e->getMessage()}");
        }
    }

    /**
     * Set a custom HTTP client (useful for testing)
     */
    public function setHttpClient(HttpClientInterface $httpClient): self
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    protected function getClient(): HttpClientInterface
    {
        if ($this->httpClient === null) {
            $this->httpClient = HttpClient::create([
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Swarm/1.0 (Tavily Integration)',
                ],
            ]);
        }

        return $this->httpClient;
    }
}
