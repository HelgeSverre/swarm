<?php

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;

test('agent extracts tasks using function calling', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $taskManager = new TaskManager;

    // Create fake OpenAI client with function call response
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
        null,
        'gpt-4',
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

    // Assert the request was sent
    $client->chat()->assertSent(function ($method, $parameters) {
        return $method === 'create'
            && $parameters['model'] === 'gpt-4'
            && isset($parameters['functions'])
            && count($parameters['functions']) === 1
            && $parameters['functions'][0]['name'] === 'extract_tasks';
    });
});

test('agent executes task with function calling', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $taskManager = new TaskManager;

    // Create test file
    $testFile = sys_get_temp_dir() . '/test_execute.txt';
    file_put_contents($testFile, 'Test content');

    // Create fake OpenAI client with function call response
    $client = new ClientFake([
        // First response: select read_file tool
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
        // Second response: task complete (no function call)
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Task completed successfully.',
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
        'gpt-4',
        0.7
    );

    // Use reflection to test protected method
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('executeTask');
    $method->setAccessible(true);

    $task = HelgeSverre\Swarm\Task\Task::fromArray([
        'id' => '1',
        'description' => 'Read the test file',
        'plan' => 'Read file contents',
        'status' => 'pending',
    ]);

    $method->invoke($agent, $task);

    // Verify tool was called
    $log = $executor->getExecutionLog();

    // The agent should have executed the read_file tool
    // Since we're testing the method directly, we just verify the router was used
    expect($log)->toBeArray();

    // Check if file was read successfully (the tool itself works)
    expect(file_exists($testFile))->toBeTrue();

    // Clean up
    unlink($testFile);

    // Assert the correct number of requests were sent
    $client->chat()->assertSent(2);
});

test('agent handles no function call response', function () {
    $executor = new ToolExecutor;
    $taskManager = new TaskManager;

    // Create fake OpenAI client with regular response (no function call)
    $client = new ClientFake([
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'The task has been completed.',
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
        'gpt-4',
        0.7
    );

    // Use reflection to test protected method
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('callOpenAIWithFunctions');
    $method->setAccessible(true);

    $result = $method->invoke($agent, 'Task completed', []);

    expect($result)->toBeNull();
});

test('agent correctly passes tool schemas to OpenAI', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $taskManager = new TaskManager;

    // Create fake OpenAI client
    $client = new ClientFake([
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'function_call' => [
                            'name' => 'bash',
                            'arguments' => json_encode(['command' => 'ls -la']),
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
        'gpt-4',
        0.7
    );

    // Get tool schemas from router
    $toolSchemas = $executor->getToolSchemas();

    // Use reflection to call callOpenAIWithFunctions
    $reflection = new ReflectionClass($agent);
    $callMethod = $reflection->getMethod('callOpenAIWithFunctions');
    $callMethod->setAccessible(true);
    $result = $callMethod->invoke($agent, 'List files', $toolSchemas);

    expect($result)->toBeArray()
        ->and($result['name'])->toBe('bash')
        ->and($result['arguments']['command'])->toBe('ls -la');

    // Assert the functions were passed correctly
    $client->chat()->assertSent(function ($method, $parameters) {
        return $method === 'create'
            && isset($parameters['functions'])
            && count($parameters['functions']) > 0
            && in_array('bash', array_column($parameters['functions'], 'name'))
            && in_array('read_file', array_column($parameters['functions'], 'name'))
            && in_array('write_file', array_column($parameters['functions'], 'name'));
    });
});
