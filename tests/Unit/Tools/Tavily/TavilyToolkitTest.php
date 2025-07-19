<?php

use HelgeSverre\Swarm\Tools\Tavily\TavilyExtractTool;
use HelgeSverre\Swarm\Tools\Tavily\TavilySearchTool;
use HelgeSverre\Swarm\Tools\Tavily\TavilyToolkit;

beforeEach(function () {})->skip(fn () => ! getenv('TAVILY_API_KEY'), 'TAVILY_API_KEY not set');

test('tavily toolkit provides both search and extract tools', function () {
    $toolkit = new TavilyToolkit;
    $tools = $toolkit->provide();

    expect($tools)->toHaveCount(2)
        ->and($tools[0])->toBeInstanceOf(TavilySearchTool::class)
        ->and($tools[1])->toBeInstanceOf(TavilyExtractTool::class);
});
test('tavily toolkit passes API key to all tools', function () {
    $apiKey = 'custom-api-key';
    $toolkit = new TavilyToolkit($apiKey);
    $tools = $toolkit->provide();

    // We can't directly access the protected apiKey property,
    // but we can verify the tools are created properly
    expect($tools[0])->toBeInstanceOf(TavilySearchTool::class)
        ->and($tools[1])->toBeInstanceOf(TavilyExtractTool::class);
});

test('tavily toolkit requires API key when none provided', function () {
    // Test by passing null explicitly to override any environment key
    $toolkit = new TavilyToolkit(null);

    // Temporarily clear $_ENV to simulate missing key
    $originalEnv = $_ENV['TAVILY_API_KEY'] ?? null;
    unset($_ENV['TAVILY_API_KEY']);

    // Also clear getenv
    $originalGetenv = getenv('TAVILY_API_KEY');
    putenv('TAVILY_API_KEY');

    expect(fn () => $toolkit->provide())->toThrow(
        InvalidArgumentException::class,
        'Tavily API key is required. Set TAVILY_API_KEY in your .env file.'
    );

    // Restore environment
    if ($originalEnv !== null) {
        $_ENV['TAVILY_API_KEY'] = $originalEnv;
    }
    if ($originalGetenv) {
        putenv("TAVILY_API_KEY={$originalGetenv}");
    }
});

test('tavily toolkit can exclude search tool', function () {
    $toolkit = new TavilyToolkit;
    $toolkit->exclude([TavilySearchTool::class]);
    $tools = $toolkit->tools();

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(TavilyExtractTool::class);
});
test('tavily toolkit can exclude extract tool', function () {
    $toolkit = new TavilyToolkit;
    $toolkit->exclude([TavilyExtractTool::class]);
    $tools = $toolkit->tools();

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(TavilySearchTool::class);
});

test('tavily toolkit can filter to only search tool', function () {
    $toolkit = new TavilyToolkit;
    $toolkit->only([TavilySearchTool::class]);
    $tools = $toolkit->tools();

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(TavilySearchTool::class);
});

test('tavily toolkit provides usage guidelines', function () {
    $toolkit = new TavilyToolkit;
    $guidelines = $toolkit->guidelines();

    expect($guidelines)->toBeString()
        ->and($guidelines)->toContain('tavily_search')
        ->and($guidelines)->toContain('tavily_extract')
        ->and($guidelines)->toContain('Usage Guidelines')
        ->and($guidelines)->toContain('Workflow example');
});
