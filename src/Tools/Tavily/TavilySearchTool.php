<?php

namespace HelgeSverre\Swarm\Tools\Tavily;

use Exception;
use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TavilySearchTool extends Tool
{
    protected const SEARCH_ENDPOINT = 'https://api.tavily.com/search';

    protected ?HttpClientInterface $httpClient = null;

    public function __construct(
        protected readonly string $apiKey
    ) {}

    public function name(): string
    {
        return 'tavily_search';
    }

    public function description(): string
    {
        return 'Search the web using Tavily API for accurate and recent information';
    }

    public function parameters(): array
    {
        return [
            'search_query' => [
                'type' => 'string',
                'description' => 'The search query to find information about',
            ],
            'topic' => [
                'type' => 'string',
                'description' => 'The category of the search (general, news, research)',
                'enum' => ['general', 'news', 'research'],
                'default' => 'general',
            ],
            'time_range' => [
                'type' => 'string',
                'description' => 'Time range filter for results',
                'enum' => ['day', 'week', 'month', 'year'],
            ],
            'days' => [
                'type' => 'integer',
                'description' => 'Number of days back to search (1-7)',
                'minimum' => 1,
                'maximum' => 7,
            ],
        ];
    }

    public function required(): array
    {
        return ['search_query'];
    }

    public function execute(array $params): ToolResponse
    {
        $searchQuery = $params['search_query'] ?? throw new InvalidArgumentException('search_query is required');

        try {
            $client = $this->getClient();

            $requestData = [
                'query' => $searchQuery,
                'api_key' => $this->apiKey,
                'search_depth' => 'advanced',
                'include_answer' => true,
                'include_raw_content' => false,
                'max_results' => 5,
            ];

            // Add optional parameters
            if (isset($params['topic'])) {
                $requestData['topic'] = $params['topic'];
            }

            if (isset($params['time_range'])) {
                $requestData['time_range'] = $params['time_range'];
            }

            if (isset($params['days'])) {
                $requestData['days'] = min(7, max(1, (int) $params['days']));
            }

            $response = $client->request('POST', self::SEARCH_ENDPOINT, [
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

            // Format the results
            $results = [];
            foreach ($data['results'] ?? [] as $result) {
                $results[] = [
                    'title' => $result['title'] ?? '',
                    'url' => $result['url'] ?? '',
                    'content' => $result['content'] ?? '',
                    'score' => $result['score'] ?? 0,
                ];
            }

            return ToolResponse::success([
                'answer' => $data['answer'] ?? null,
                'results' => $results,
                'query' => $searchQuery,
            ]);
        } catch (Exception $e) {
            return ToolResponse::error("Failed to search: {$e->getMessage()}");
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
