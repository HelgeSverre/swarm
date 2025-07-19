<?php

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;
use Psr\Log\NullLogger;

test('agent correctly extracts tasks using function calling', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $taskManager = new TaskManager;

    // Create fake client with function call response
    $client = new ClientFake([
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'function_call' => [
                            'name' => 'extract_tasks',
                            'arguments' => json_encode([
                                'tasks' => [
                                    ['description' => 'Read the README.md file'],
                                    ['description' => 'Find all PHP files in src directory'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $agent = new CodingAgent(
        $executor,
        $taskManager,
        $client,
        new NullLogger,
        'gpt-4.1-mini',
        0.7
    );

    // Use reflection to test protected method
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('extractTasks');
    $method->setAccessible(true);

    $tasks = $method->invoke($agent, 'I need you to read the README file and find all PHP files in src');

    expect($tasks)->toBeArray()
        ->and($tasks)->toHaveCount(2)
        ->and($tasks[0]['description'])->toBe('Read the README.md file')
        ->and($tasks[1]['description'])->toBe('Find all PHP files in src directory');
});

test('agent selects correct tool based on task description', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $testCases = [
        [
            'prompt' => 'Read the contents of config.json',
            'expectedTool' => 'read_file',
            'expectedParams' => ['path' => 'config.json'],
        ],
        [
            'prompt' => 'Save this data to output.txt',
            'expectedTool' => 'write_file',
            'expectedParams' => ['path' => 'output.txt', 'content' => 'some data'],
        ],
        [
            'prompt' => 'Run ls -la command',
            'expectedTool' => 'bash',
            'expectedParams' => ['command' => 'ls -la'],
        ],
        [
            'prompt' => 'Find all markdown files',
            'expectedTool' => 'grep',
            'expectedParams' => ['pattern' => '*.md'],
        ],
        [
            'prompt' => 'Search for TODO comments',
            'expectedTool' => 'grep',
            'expectedParams' => ['search' => 'TODO'],
        ],
    ];

    $taskManager = new TaskManager;

    foreach ($testCases as $testCase) {
        // Create a client that responds with the expected tool
        $client = new ClientFake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'function_call' => [
                                'name' => $testCase['expectedTool'],
                                'arguments' => json_encode($testCase['expectedParams']),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $agent = new CodingAgent(
            $executor,
            $taskManager,
            $client,
            null,
            'gpt-4.1-mini',
            0.7
        );

        // Get tool schemas directly from router to verify they include the expected tool
        $toolSchemas = $executor->getToolSchemas();
        $toolNames = array_column($toolSchemas, 'name');

        expect($toolNames)->toContain($testCase['expectedTool']);
    }
});

test('agent handles function call responses correctly', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $taskManager = new TaskManager;

    // Create test file
    $testFile = '/tmp/test_function_call.txt';
    file_put_contents($testFile, 'Test content for function calling');

    // Mock a function call response
    $client = new ClientFake([
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'function_call' => [
                            'name' => 'read_file',
                            'arguments' => json_encode(['path' => $testFile]),
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $agent = new CodingAgent(
        $executor,
        $taskManager,
        $client,
        null,
        'gpt-4.1-mini',
        0.7
    );

    // Use reflection to test protected method
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('callOpenAIWithFunctions');
    $method->setAccessible(true);

    // Get tool schemas from router
    $toolSchemas = $executor->getToolSchemas();
    $result = $method->invoke($agent, 'Read the file at /tmp/test_function_call.txt', $toolSchemas);

    expect($result)->toBeArray()
        ->and($result['name'])->toBe('read_file')
        ->and($result['arguments'])->toBeArray()
        ->and($result['arguments']['path'])->toBe($testFile);

    // Clean up
    unlink($testFile);
});

test('agent handles no function call response correctly', function () {
    $executor = ToolExecutor::createWithDefaultTools();
    $taskManager = new TaskManager;

    // Mock a regular response without function call
    $client = new ClientFake([
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'The task has been completed successfully.',
                    ],
                ],
            ],
        ]),
    ]);

    $agent = new CodingAgent(
        $executor,
        $taskManager,
        $client,
        null,
        'gpt-4.1-mini',
        0.7
    );

    // Use reflection to test protected method
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('callOpenAIWithFunctions');
    $method->setAccessible(true);

    $result = $method->invoke($agent, 'Task completed', []);

    expect($result)->toBeNull();
});
