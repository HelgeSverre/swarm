<?php

use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\AbstractToolkit;
use HelgeSverre\Swarm\Core\ToolResponse;

beforeEach(function () {
    $this->testToolA = new class extends Tool
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
    };

    $this->testToolB = new class extends Tool
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
    };

    $toolA = $this->testToolA;
    $toolB = $this->testToolB;

    $this->testToolkit = new class($toolA, $toolB) extends AbstractToolkit
    {
        public function __construct(
            private Tool $toolA,
            private Tool $toolB
        ) {}

        public function provide(): array
        {
            return [
                $this->toolA,
                $this->toolB,
            ];
        }
    };
});

test('toolkit provides all tools by default', function () {
    $tools = $this->testToolkit->tools();

    expect($tools)->toHaveCount(2)
        ->and($tools[0])->toBe($this->testToolA)
        ->and($tools[1])->toBe($this->testToolB);
});

test('toolkit can exclude specific tools', function () {
    $this->testToolkit->exclude([$this->testToolA::class]);
    $tools = $this->testToolkit->tools();

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBe($this->testToolB);
});

test('toolkit can exclude multiple tools', function () {
    $this->testToolkit->exclude([$this->testToolA::class, $this->testToolB::class]);
    $tools = $this->testToolkit->tools();

    expect($tools)->toHaveCount(0);
});

test('toolkit can filter to only specific tools', function () {
    $this->testToolkit->only([$this->testToolA::class]);
    $tools = $this->testToolkit->tools();

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBe($this->testToolA);
});

test('toolkit only filter takes precedence over exclude', function () {
    $this->testToolkit->exclude([$this->testToolA::class])
        ->only([$this->testToolA::class, $this->testToolB::class]);
    $tools = $this->testToolkit->tools();

    expect($tools)->toHaveCount(2)
        ->and($tools[0])->toBe($this->testToolA)
        ->and($tools[1])->toBe($this->testToolB);
});

test('toolkit returns null guidelines by default', function () {
    expect($this->testToolkit->guidelines())->toBeNull();
});

test('toolkit exclude method is chainable', function () {
    $result = $this->testToolkit->exclude([$this->testToolA::class]);

    expect($result)->toBe($this->testToolkit);
});

test('toolkit only method is chainable', function () {
    $result = $this->testToolkit->only([$this->testToolA::class]);

    expect($result)->toBe($this->testToolkit);
});

test('toolkit tools method resets array keys', function () {
    $this->testToolkit->exclude([$this->testToolA::class]);
    $tools = $this->testToolkit->tools();

    expect(array_keys($tools))->toBe([0]);
});
