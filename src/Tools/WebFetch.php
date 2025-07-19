<?php

namespace HelgeSverre\Swarm\Tools;

use Crwlr\Html2Text\Html2Text;
use Exception;
use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebFetch extends Tool
{
    protected ?HttpClientInterface $httpClient = null;

    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient;
    }

    public function name(): string
    {
        return 'web_fetch';
    }

    public function description(): string
    {
        return 'Fetch content from a URL and convert HTML to text for AI processing';
    }

    public function parameters(): array
    {
        return [
            'url' => [
                'type' => 'string',
                'description' => 'The URL to fetch content from',
            ],
            'headers' => [
                'type' => 'object',
                'description' => 'Optional HTTP headers to include in the request',
            ],
            'timeout' => [
                'type' => 'number',
                'description' => 'Request timeout in seconds (default: 30)',
            ],
        ];
    }

    public function required(): array
    {
        return ['url'];
    }

    public function execute(array $params): ToolResponse
    {
        $url = $params['url'] ?? throw new InvalidArgumentException('URL is required');
        $headers = $params['headers'] ?? [];
        $timeout = $params['timeout'] ?? 30;

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return ToolResponse::error("Invalid URL: {$url}");
        }

        try {
            // Use injected client or create default
            $client = $this->httpClient ?? HttpClient::create([
                'timeout' => $timeout,
                'headers' => array_merge([
                    'User-Agent' => 'HelgeSverre/Swarm AI Coding Agent',
                ], $headers),
            ]);

            // Fetch the content
            $response = $client->request('GET', $url);
            $statusCode = $response->getStatusCode();

            // Check for HTTP errors
            if ($statusCode >= 400) {
                return ToolResponse::error("HTTP error {$statusCode}: " . $response->getContent(false));
            }

            $contentType = $response->getHeaders()['content-type'][0] ?? 'text/html';
            $content = $response->getContent();

            // Process based on content type
            $processedContent = $content;

            if (str_contains($contentType, 'text/html')) {
                // Convert HTML to text
                $html2Text = new Html2Text;
                $processedContent = $html2Text->convert($content);
            } elseif (str_contains($contentType, 'application/json')) {
                // Pretty print JSON
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $processedContent = json_encode($decoded, JSON_PRETTY_PRINT);
                }
            }

            return ToolResponse::success([
                'url' => $url,
                'status_code' => $statusCode,
                'content_type' => $contentType,
                'content' => $processedContent,
                'raw_size' => mb_strlen($content),
                'processed_size' => mb_strlen($processedContent),
            ]);
        } catch (TransportExceptionInterface $e) {
            return ToolResponse::error("Network error fetching URL: {$e->getMessage()}");
        } catch (HttpExceptionInterface $e) {
            return ToolResponse::error("HTTP error {$e->getCode()}: {$e->getMessage()}");
        } catch (Exception $e) {
            return ToolResponse::error("Unexpected error: {$e->getMessage()}");
        }
    }
}
