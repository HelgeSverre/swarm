<?php

use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\AbstractToolkit;
use HelgeSverre\Swarm\Core\ToolResponse;

// Create test tools for testing
class TestToolA extends Tool
{
    public function name(): string
    {
        return 'test_tool_a';
    }

    public function description(): string
    {
        return 'Test tool A';
    }

    public function parameters(): array
    {
        return [];
    }

    public function required(): array
    {
        return [];
    }

    public function execute(array $params): ToolResponse
    {
        return ToolResponse::success(['tool' => 'A']);
    }
}

class TestToolB extends Tool
{
    public function name(): string
    {
        return 'test_tool_b';
    }

    public function description(): string
    {
        return 'Test tool B';
    }

    public function parameters(): array
    {
        return [];
    }

    public function required(): array
    {
        return [];
    }

    public function execute(array $params): ToolResponse
    {
        return ToolResponse::success(['tool' => 'B']);
    }
}

// Test implementation of AbstractToolkit
class TestToolkit extends AbstractToolkit
{
    public function provide(): array
    {
        return [
            new TestToolA,
            new TestToolB,
        ];
    }
}

test('toolkit provides all tools by default', function () {
    $toolkit = new TestToolkit;
    $tools = $toolkit->tools();

    expect($tools)->toHaveCount(2)
        ->and($tools[0])->toBeInstanceOf(TestToolA::class)
        ->and($tools[1])->toBeInstanceOf(TestToolB::class);
});

test('toolkit can exclude specific tools', function () {
    $toolkit = new TestToolkit;
    $toolkit->exclude([TestToolA::class]);
    $tools = $toolkit->tools();

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(TestToolB::class);
});

test('toolkit can exclude multiple tools', function () {
    $toolkit = new TestToolkit;
    $toolkit->exclude([TestToolA::class, TestToolB::class]);
    $tools = $toolkit->tools();

    expect($tools)->toHaveCount(0);
});

test('toolkit can filter to only specific tools', function () {
    $toolkit = new TestToolkit;
    $toolkit->only([TestToolA::class]);
    $tools = $toolkit->tools();

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(TestToolA::class);
});

test('toolkit only filter takes precedence over exclude', function () {
    $toolkit = new TestToolkit;
    $toolkit->exclude([TestToolA::class])
        ->only([TestToolA::class, TestToolB::class]);
    $tools = $toolkit->tools();

    expect($tools)->toHaveCount(2)
        ->and($tools[0])->toBeInstanceOf(TestToolA::class)
        ->and($tools[1])->toBeInstanceOf(TestToolB::class);
});

test('toolkit returns null guidelines by default', function () {
    $toolkit = new TestToolkit;

    expect($toolkit->guidelines())->toBeNull();
});

test('toolkit exclude method is chainable', function () {
    $toolkit = new TestToolkit;
    $result = $toolkit->exclude([TestToolA::class]);

    expect($result)->toBe($toolkit);
});

test('toolkit only method is chainable', function () {
    $toolkit = new TestToolkit;
    $result = $toolkit->only([TestToolA::class]);

    expect($result)->toBe($toolkit);
});

test('toolkit tools method resets array keys', function () {
    $toolkit = new TestToolkit;
    $toolkit->exclude([TestToolA::class]);
    $tools = $toolkit->tools();

    expect(array_keys($tools))->toBe([0]);
});
