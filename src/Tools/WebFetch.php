<?php

namespace HelgeSverre\Swarm\Tools;

use Crwlr\Html2Text\Html2Text;
use Exception;
use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;
use Spatie\PdfToText\Pdf;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\HttpClient\HttpClient;
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

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return ToolResponse::error("Invalid URL: {$url}");
        }

        try {
            $client = $this->httpClient ?? HttpClient::create([
                'timeout' => $params['timeout'] ?? 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; Swarm/1.0)',
                    'Accept' => '*/*',
                ],
            ]);

            $response = $client->request('GET', $url);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();
            $contentType = $response->getHeaders()['content-type'][0] ?? 'text/html';

            $processedContent = match (true) {
                str_contains($contentType, 'text/html') => (new Html2Text)->convert($content),
                str_contains($contentType, 'application/json') => json_encode(
                    json_decode($content, true, flags: JSON_THROW_ON_ERROR),
                    JSON_PRETTY_PRINT
                ),
                str_contains($contentType, 'application/pdf') => $this->extractPdfText($content),
                str_contains($contentType, 'text/plain') => $content,
                str_contains($contentType, 'text/') => $content, // Any text type
                str_contains($contentType, 'application/xml') => $content,
                str_contains($contentType, 'image/') => '[Image content - ' . mb_strlen($content) . ' bytes]',
                default => '[Binary content - ' . mb_strlen($content) . ' bytes]',
            };

            return ToolResponse::success([
                'url' => $url,
                'status_code' => $statusCode,
                'content_type' => $contentType,
                'content' => $processedContent,
                'size' => mb_strlen($content),
            ]);
        } catch (Exception $e) {
            return ToolResponse::error("Failed to fetch {$url}: {$e->getMessage()}");
        }
    }

    protected function extractPdfText(string $pdfContent): string
    {
        try {
            $tempDir = TemporaryDirectory::make();
            $pdfPath = $tempDir->path('document.pdf');

            file_put_contents($pdfPath, $pdfContent);

            $text = (new Pdf)
                ->setPdf($pdfPath)
                ->text();

            $tempDir->delete();

            return $text ?: '[PDF content - ' . mb_strlen($pdfContent) . ' bytes, no text extracted]';
        } catch (Exception $e) {
            return '[PDF content - ' . mb_strlen($pdfContent) . ' bytes, extraction failed: ' . $e->getMessage() . ']';
        }
    }
}
