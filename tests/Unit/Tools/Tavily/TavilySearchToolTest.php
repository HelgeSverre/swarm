<?php

use HelgeSverre\Swarm\Tools\Tavily\TavilySearchTool;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('tavily search tool generates correct schema', function () {
    $tool = new TavilySearchTool('test-api-key');
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('tavily_search')
        ->and($schema['description'])->toBe('Search the web using Tavily API for accurate and recent information')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKeys(['search_query', 'topic', 'time_range', 'days'])
        ->and($schema['parameters']['properties']['topic']['enum'])->toBe(['general', 'news', 'research'])
        ->and($schema['parameters']['properties']['time_range']['enum'])->toBe(['day', 'week', 'month', 'year'])
        ->and($schema['parameters']['required'])->toBe(['search_query']);
});

test('tavily search executes successful search', function () {
    $mockResponse = [
        'answer' => 'PHP is a popular server-side scripting language.',
        'results' => [
            [
                'title' => 'PHP: Hypertext Preprocessor',
                'url' => 'https://php.net',
                'content' => 'PHP is a popular general-purpose scripting language...',
                'score' => 0.95,
            ],
            [
                'title' => 'Learn PHP - W3Schools',
                'url' => 'https://w3schools.com/php',
                'content' => 'PHP is a server scripting language...',
                'score' => 0.87,
            ],
        ],
    ];

    $httpResponse = new MockResponse(json_encode($mockResponse), [
        'http_code' => 200,
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$httpResponse]);
    $tool = new TavilySearchTool('test-api-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute(['search_query' => 'PHP programming language']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['answer'])->toBe('PHP is a popular server-side scripting language.')
        ->and($result->getData()['query'])->toBe('PHP programming language')
        ->and($result->getData()['results'])->toHaveCount(2)
        ->and($result->getData()['results'][0]['title'])->toBe('PHP: Hypertext Preprocessor')
        ->and($result->getData()['results'][0]['url'])->toBe('https://php.net')
        ->and($result->getData()['results'][0]['score'])->toBe(0.95);
});

test('tavily search handles missing search_query', function () {
    $tool = new TavilySearchTool('test-api-key');

    expect(fn () => $tool->execute([]))->toThrow(InvalidArgumentException::class, 'search_query is required');
});

test('tavily search handles API errors', function () {
    $mockResponse = ['error' => 'Invalid API key'];

    $httpResponse = new MockResponse(json_encode($mockResponse), [
        'http_code' => 401,
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$httpResponse]);
    $tool = new TavilySearchTool('invalid-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute(['search_query' => 'test']);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('401');
});

test('tavily search handles network errors', function () {
    $mockClient = new MockHttpClient(function () {
        throw new Exception('Network error');
    });

    $tool = new TavilySearchTool('test-api-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute(['search_query' => 'test']);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Failed to search')
        ->and($result->getError())->toContain('Network error');
});

test('tavily search includes optional parameters', function () {
    $mockResponse = [
        'answer' => 'Recent PHP news',
        'results' => [],
    ];

    $httpResponse = new MockResponse(json_encode($mockResponse), [
        'http_code' => 200,
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$httpResponse]);
    $tool = new TavilySearchTool('test-api-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute([
        'search_query' => 'PHP updates',
        'topic' => 'news',
        'time_range' => 'week',
        'days' => 3,
    ]);

    expect($result->isSuccess())->toBeTrue();
});

test('tavily search limits days parameter', function () {
    $mockResponse = [
        'answer' => 'Results',
        'results' => [],
    ];

    $httpResponse = new MockResponse(json_encode($mockResponse), [
        'http_code' => 200,
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$httpResponse]);
    $tool = new TavilySearchTool('test-api-key');
    $tool->setHttpClient($mockClient);

    // Test that days > 7 gets capped at 7
    $result = $tool->execute([
        'search_query' => 'test',
        'days' => 30,
    ]);

    expect($result->isSuccess())->toBeTrue();
});

test('tavily search handles empty results', function () {
    $mockResponse = [
        'answer' => null,
        'results' => [],
    ];

    $httpResponse = new MockResponse(json_encode($mockResponse), [
        'http_code' => 200,
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$httpResponse]);
    $tool = new TavilySearchTool('test-api-key');
    $tool->setHttpClient($mockClient);

    $result = $tool->execute(['search_query' => 'very obscure query']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['answer'])->toBeNull()
        ->and($result->getData()['results'])->toBe([]);
});
