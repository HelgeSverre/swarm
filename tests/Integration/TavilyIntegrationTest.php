<?php

use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Tools\Tavily\TavilyExtractTool;
use HelgeSverre\Swarm\Tools\Tavily\TavilySearchTool;
use HelgeSverre\Swarm\Tools\Tavily\TavilyToolkit;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

beforeEach(function () {})->skip(fn () => ! getenv('TAVILY_API_KEY'), 'TAVILY_API_KEY not set');

test('toolkit can be registered with tool executor', function () {
    $executor = new ToolExecutor;
    $toolkit = new TavilyToolkit;

    $executor->registerToolkit($toolkit);

    $registeredTools = $executor->getRegisteredTools();

    expect($registeredTools)->toContain('tavily_search')
        ->and($registeredTools)->toContain('tavily_extract');
});

test('tools from toolkit generate proper schemas', function () {
    $executor = new ToolExecutor;
    $toolkit = new TavilyToolkit;

    $executor->registerToolkit($toolkit);

    $schemas = $executor->getToolSchemas();
    $schemaNames = array_column($schemas, 'name');

    expect($schemaNames)->toContain('tavily_search')
        ->and($schemaNames)->toContain('tavily_extract');

    // Verify schema structure
    foreach ($schemas as $schema) {
        if (in_array($schema['name'], ['tavily_search', 'tavily_extract'])) {
            expect($schema)->toHaveKeys(['name', 'description', 'parameters'])
                ->and($schema['parameters'])->toHaveKeys(['type', 'properties', 'required']);
        }
    }
});

test('tools from toolkit can be executed through executor', function () {
    $mockSearchResponse = [
        'answer' => 'Test answer',
        'results' => [
            ['title' => 'Result 1', 'url' => 'https://example.com/1', 'content' => 'Content 1', 'score' => 0.9],
        ],
    ];

    $mockExtractResponse = [
        'results' => [
            ['title' => 'Extracted', 'raw_content' => '# Extracted Content', 'content' => 'Extracted Content'],
        ],
    ];

    $httpResponses = [
        new MockResponse(json_encode($mockSearchResponse), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]),
        new MockResponse(json_encode($mockExtractResponse), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]),
    ];

    $mockClient = new MockHttpClient($httpResponses);

    $executor = new ToolExecutor;
    $toolkit = new TavilyToolkit;

    // Create a new toolkit with HTTP clients set
    $searchTool = new TavilySearchTool('test-api-key');
    $searchTool->setHttpClient($mockClient);
    $extractTool = new TavilyExtractTool('test-api-key');
    $extractTool->setHttpClient($mockClient);

    // Register tools directly
    $executor->register($searchTool);
    $executor->register($extractTool);

    // Execute search
    $searchResult = $executor->dispatch('tavily_search', ['search_query' => 'test query']);
    expect($searchResult->isSuccess())->toBeTrue()
        ->and($searchResult->getData()['answer'])->toBe('Test answer');

    // Execute extract
    $extractResult = $executor->dispatch('tavily_extract', ['url' => 'https://example.com']);
    expect($extractResult->isSuccess())->toBeTrue()
        ->and($extractResult->getData()['markdown'])->toBe('# Extracted Content');
});

test('createWithDefaultTools includes tavily toolkit when API key is set', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $registeredTools = $executor->getRegisteredTools();

    // Check default tools
    expect($registeredTools)->toContain('read_file')
        ->and($registeredTools)->toContain('write_file')
        ->and($registeredTools)->toContain('bash')
        ->and($registeredTools)->toContain('grep')
        ->and($registeredTools)->toContain('web_fetch');

    // Check Tavily tools are included when API key is set
    expect($registeredTools)->toContain('tavily_search')
        ->and($registeredTools)->toContain('tavily_extract');
})->skip(fn () => ! getenv('TAVILY_API_KEY'), 'TAVILY_API_KEY not set');

test('toolkit with filtered tools registers correctly', function () {
    $executor = new ToolExecutor;
    $toolkit = new TavilyToolkit;
    $toolkit->only([TavilySearchTool::class]);

    $executor->registerToolkit($toolkit);

    $registeredTools = $executor->getRegisteredTools();

    expect($registeredTools)->toContain('tavily_search')
        ->and($registeredTools)->not->toContain('tavily_extract');
})->skip(fn () => ! getenv('TAVILY_API_KEY'), 'TAVILY_API_KEY not set');

test('complete workflow search then extract', function () {
    // Mock search response
    $mockSearchResponse = [
        'answer' => 'PHP is a programming language',
        'results' => [
            [
                'title' => 'PHP Documentation',
                'url' => 'https://php.net/manual',
                'content' => 'Official PHP documentation...',
                'score' => 0.95,
            ],
        ],
    ];

    // Mock extract response
    $mockExtractResponse = [
        'results' => [
            [
                'title' => 'PHP Manual',
                'raw_content' => '# PHP Manual\n\n## Introduction\n\nPHP is a popular general-purpose scripting language...',
                'content' => 'PHP Manual. Introduction. PHP is a popular general-purpose scripting language...',
            ],
        ],
    ];

    //    $httpResponses = [
    //        new MockResponse(json_encode($mockSearchResponse), [
    //            'http_code' => 200,
    //            'response_headers' => ['content-type' => 'application/json'],
    //        ]),
    //        new MockResponse(json_encode($mockExtractResponse), [
    //            'http_code' => 200,
    //            'response_headers' => ['content-type' => 'application/json'],
    //        ]),
    //    ];
    //
    //    $mockClient = new MockHttpClient($httpResponses);

    $executor = new ToolExecutor;

    // Create tools with HTTP client set
    $searchTool = new TavilySearchTool(
        getenv('TAVILY_API_KEY')
    );
    $extractTool = new TavilyExtractTool(
        getenv('TAVILY_API_KEY')
    );

    // Step 1: Search for information
    $searchResult = $searchTool->execute(['search_query' => 'PHP programming language']);

    expect($searchResult->isSuccess())->toBeTrue();

    $searchData = $searchResult->getData();
    expect($searchData['results'])->not->toBeEmpty();

    // Step 2: Extract content from the first result
    $firstResult = $searchData['results'][0];

    $extractResult = $extractTool->execute(['url' => $firstResult['url']]);

    expect($extractResult->isSuccess())->toBeTrue()
        ->and($extractResult->getData()['markdown'])->toContain('php.net')
        ->and($extractResult->getData()['markdown'])->toContain('Rasmus Lerdorf');
});
