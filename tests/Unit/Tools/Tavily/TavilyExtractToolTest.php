<?php

use HelgeSverre\Swarm\Tools\Tavily\TavilyExtractTool;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('tavily extract tool generates correct schema', function () {
    $tool = new TavilyExtractTool('test-api-key');
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('tavily_extract')
        ->and($schema['description'])->toBe('Extract clean content from a URL and return it in markdown format')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKeys(['url'])
        ->and($schema['parameters']['required'])->toBe(['url']);
});

test('tavily extract executes successful extraction', function () {
    $mockResponse = [
        'results' => [
            [
                'title' => 'Example Article',
                'raw_content' => '# Example Article\n\nThis is the content in markdown format.\n\n## Section 1\n\nSome text here.',
                'content' => 'Example Article. This is the content in markdown format. Section 1. Some text here.',
            ],
        ],
    ];

    $httpResponse = new MockResponse(json_encode($mockResponse), [
        'http_code' => 200,
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$httpResponse]);
    $tool = new TavilyExtractTool('test-api-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute(['url' => 'https://example.com/article']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['url'])->toBe('https://example.com/article')
        ->and($result->getData()['title'])->toBe('Example Article')
        ->and($result->getData()['markdown'])->toContain('# Example Article')
        ->and($result->getData()['markdown'])->toContain('## Section 1');
});

test('tavily extract validates URL format', function () {
    $tool = new TavilyExtractTool('test-api-key');

    $result = $tool->execute(['url' => 'not-a-valid-url']);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Invalid URL');
});

test('tavily extract handles missing URL parameter', function () {
    $tool = new TavilyExtractTool('test-api-key');

    expect(fn () => $tool->execute([]))->toThrow(InvalidArgumentException::class, 'url is required');
});

test('tavily extract handles API errors', function () {
    $mockResponse = ['error' => 'Invalid API key'];

    $httpResponse = new MockResponse(json_encode($mockResponse), [
        'http_code' => 401,
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$httpResponse]);
    $tool = new TavilyExtractTool('invalid-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute(['url' => 'https://example.com']);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('401');
});

test('tavily extract handles network errors', function () {
    $mockClient = new MockHttpClient(function () {
        throw new Exception('Connection timeout');
    });

    $tool = new TavilyExtractTool('test-api-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute(['url' => 'https://example.com']);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Failed to extract content')
        ->and($result->getError())->toContain('Connection timeout');
});

test('tavily extract handles empty results', function () {
    $mockResponse = [
        'results' => [],
    ];

    $httpResponse = new MockResponse(json_encode($mockResponse), [
        'http_code' => 200,
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$httpResponse]);
    $tool = new TavilyExtractTool('test-api-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute(['url' => 'https://example.com']);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toBe('No content extracted from URL');
});

test('tavily extract handles missing content in result', function () {
    $mockResponse = [
        'results' => [
            [
                'title' => 'Empty Article',
                'raw_content' => '',
                'content' => '',
            ],
        ],
    ];

    $httpResponse = new MockResponse(json_encode($mockResponse), [
        'http_code' => 200,
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$httpResponse]);
    $tool = new TavilyExtractTool('test-api-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute(['url' => 'https://example.com']);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toBe('Failed to extract content from URL');
});

test('tavily extract prefers raw_content over content', function () {
    $mockResponse = [
        'results' => [
            [
                'title' => 'Test Article',
                'raw_content' => '# Markdown Content',
                'content' => 'Plain text content',
            ],
        ],
    ];

    $httpResponse = new MockResponse(json_encode($mockResponse), [
        'http_code' => 200,
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$httpResponse]);
    $tool = new TavilyExtractTool('test-api-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute(['url' => 'https://example.com']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['markdown'])->toBe('# Markdown Content');
});
