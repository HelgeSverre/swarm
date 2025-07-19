<?php

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;
use Psr\Log\NullLogger;

test('full task lifecycle from extraction to completion', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $taskManager = new TaskManager(new NullLogger);

    // Create fake client that will:
    // 1. Extract tasks
    // 2. Return a plan (via regular chat)
    // 3. Execute the task with a tool call
    // 4. Complete the task (no function call)
    $client = new ClientFake([
        // Classification response
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'request_type' => 'implementation',
                            'requires_tools' => true,
                            'confidence' => 0.9,
                            'reasoning' => 'User wants to create a file',
                        ]),
                    ],
                ],
            ],
        ]),
        // Task extraction response
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
                                    ['description' => 'Create a test file'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ],
        ]),
        // Planning response
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'plan_summary' => "I'll create the test file by writing content to test.txt",
                            'steps' => [
                                ['description' => 'Writing content to test.txt', 'tool_needed' => 'write_file'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
        // Task execution - write file
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'function_call' => [
                            'name' => 'write_file',
                            'arguments' => json_encode([
                                'path' => sys_get_temp_dir() . '/test_lifecycle.txt',
                                'content' => 'Test content',
                                'backup' => false,
                            ]),
                        ],
                    ],
                ],
            ],
        ]),
        // Task complete (no function call)
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
        // Summary generation
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'I have successfully created the test file at ' . sys_get_temp_dir() . '/test_lifecycle.txt',
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
        'gpt-4',
        0.7
    );

    // Process the request
    $response = $agent->processRequest('Create a test file');

    // Verify the response
    expect($response->isSuccess())->toBeTrue()
        ->and($response->getMessage())->toContain('successfully created');

    // Verify task was completed
    $tasks = $taskManager->getTasks();
    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]->status->value)->toBe('completed')
        ->and($tasks[0]->description)->toBe('Create a test file')
        ->and($tasks[0]->plan)->toContain('test file');

    // Verify file was created
    $testFile = sys_get_temp_dir() . '/test_lifecycle.txt';
    expect(file_exists($testFile))->toBeTrue()
        ->and(file_get_contents($testFile))->toBe('Test content');

    // Clean up
    unlink($testFile);
});

test('multiple tasks are processed sequentially', function () {
    $executor = ToolExecutor::createWithDefaultTools();

    $taskManager = new TaskManager(new NullLogger);

    $client = new ClientFake([
        // Classification response
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'request_type' => 'implementation',
                            'requires_tools' => true,
                            'confidence' => 0.9,
                            'reasoning' => 'User wants to create files',
                        ]),
                    ],
                ],
            ],
        ]),
        // Extract multiple tasks
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
                                    ['description' => 'Create file1.txt'],
                                    ['description' => 'Create file2.txt'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ],
        ]),
        // Plan for task 1
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'plan_summary' => 'Plan for file1.txt',
                            'steps' => [
                                ['description' => 'Create file1.txt', 'tool_needed' => 'write_file'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
        // Plan for task 2
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'plan_summary' => 'Plan for file2.txt',
                            'steps' => [
                                ['description' => 'Create file2.txt', 'tool_needed' => 'write_file'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
        // Execute task 1
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'function_call' => [
                            'name' => 'write_file',
                            'arguments' => json_encode([
                                'path' => sys_get_temp_dir() . '/file1.txt',
                                'content' => 'Content 1',
                                'backup' => false,
                            ]),
                        ],
                    ],
                ],
            ],
        ]),
        // Complete task 1
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Task 1 completed.',
                    ],
                ],
            ],
        ]),
        // Execute task 2
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'function_call' => [
                            'name' => 'write_file',
                            'arguments' => json_encode([
                                'path' => sys_get_temp_dir() . '/file2.txt',
                                'content' => 'Content 2',
                                'backup' => false,
                            ]),
                        ],
                    ],
                ],
            ],
        ]),
        // Complete task 2
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Task 2 completed.',
                    ],
                ],
            ],
        ]),
        // Summary
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Both files created successfully.',
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
        'gpt-4',
        0.7
    );

    // Process the request
    $response = $agent->processRequest('Create file1.txt and file2.txt');

    // Verify both tasks were completed
    $tasks = $taskManager->getTasks();
    expect($tasks)->toHaveCount(2)
        ->and($tasks[0]->status->value)->toBe('completed')
        ->and($tasks[1]->status->value)->toBe('completed');

    // Verify files were created
    $file1 = sys_get_temp_dir() . '/file1.txt';
    $file2 = sys_get_temp_dir() . '/file2.txt';

    expect(file_exists($file1))->toBeTrue()
        ->and(file_get_contents($file1))->toBe('Content 1')
        ->and(file_exists($file2))->toBeTrue()
        ->and(file_get_contents($file2))->toBe('Content 2');

    // Clean up
    unlink($file1);
    unlink($file2);
});

test('agent handles conversation when no tasks extracted', function () {
    $executor = ToolExecutor::createWithDefaultTools();
    $taskManager = new TaskManager(new NullLogger);

    $client = new ClientFake([
        // Classification response
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'request_type' => 'query',
                            'requires_tools' => false,
                            'confidence' => 0.9,
                            'reasoning' => 'User is asking a question',
                        ]),
                    ],
                ],
            ],
        ]),
        // Handle as conversation
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'PHP is a popular server-side scripting language.',
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
        'gpt-4',
        0.7
    );

    $response = $agent->processRequest('What is PHP?');

    expect($response->isSuccess())->toBeTrue()
        ->and($response->getMessage())->toContain('PHP is a popular')
        ->and($taskManager->getTasks())->toBeEmpty();
});

test('task execution handles errors gracefully', function () {
    $executor = ToolExecutor::createWithDefaultTools();
    $taskManager = new TaskManager(new NullLogger);

    $client = new ClientFake([
        // Classification response
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'request_type' => 'implementation',
                            'requires_tools' => true,
                            'confidence' => 0.9,
                            'reasoning' => 'User wants to read a file',
                        ]),
                    ],
                ],
            ],
        ]),
        // Extract task
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
                                    ['description' => 'Read non-existent file'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ],
        ]),
        // Plan
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'plan_summary' => 'Will read the file',
                            'steps' => [
                                ['description' => 'Read non-existent file', 'tool_needed' => 'read_file'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
        // Try to read non-existent file
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'function_call' => [
                            'name' => 'read_file',
                            'arguments' => json_encode([
                                'path' => '/definitely/does/not/exist.txt',
                            ]),
                        ],
                    ],
                ],
            ],
        ]),
        // Summary after error
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Task attempted but encountered an error.',
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
        'gpt-4',
        0.7
    );

    // Process should not throw exception
    $response = $agent->processRequest('Read non-existent file');

    expect($response->isSuccess())->toBeTrue();

    // Task should still be marked as completed (even if tool failed)
    $tasks = $taskManager->getTasks();
    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]->status->value)->toBe('completed');
});
