<?php

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\Toolchain;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;
use Psr\Log\NullLogger;

test('agent extracts tasks for implementation requests without tools', function () {
    $executor = new ToolExecutor(new NullLogger);
    Toolchain::registerAll($executor);

    $taskManager = new TaskManager(new NullLogger);

    $client = new ClientFake([
        // Classification response - implementation without tools
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'request_type' => 'implementation',
                            'requires_tools' => false,
                            'confidence' => 0.85,
                            'reasoning' => 'User wants to create tasks for simple actions',
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
                                    ['description' => 'Eat an apple'],
                                    ['description' => 'Eat a banana'],
                                    ['description' => 'Eat grapes'],
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
                            'plan_summary' => 'Pick and eat a fresh apple',
                            'steps' => [
                                ['description' => 'Select a fresh apple', 'tool_needed' => 'none'],
                                ['description' => 'Wash the apple', 'tool_needed' => 'none'],
                                ['description' => 'Eat the apple', 'tool_needed' => 'none'],
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
                            'plan_summary' => 'Peel and eat a ripe banana',
                            'steps' => [
                                ['description' => 'Select a ripe banana', 'tool_needed' => 'none'],
                                ['description' => 'Peel the banana', 'tool_needed' => 'none'],
                                ['description' => 'Eat the banana', 'tool_needed' => 'none'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
        // Plan for task 3
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'plan_summary' => 'Wash and eat grapes',
                            'steps' => [
                                ['description' => 'Select fresh grapes', 'tool_needed' => 'none'],
                                ['description' => 'Wash the grapes', 'tool_needed' => 'none'],
                                ['description' => 'Eat the grapes', 'tool_needed' => 'none'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
        // Execute task 1 - no tools needed
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Task 1 completed - apple eating instructions provided.',
                    ],
                ],
            ],
        ]),
        // Execute task 2 - no tools needed
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Task 2 completed - banana eating instructions provided.',
                    ],
                ],
            ],
        ]),
        // Execute task 3 - no tools needed
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Task 3 completed - grape eating instructions provided.',
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
                        'content' => 'I have created 3 tasks for eating different fruits: eating an apple, eating a banana, and eating grapes. Each task includes steps for selecting, preparing, and consuming the fruit.',
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
    $response = $agent->processRequest('make 3 tasks to eat a different fruit');

    // Verify the response
    expect($response->isSuccess())->toBeTrue()
        ->and($response->getMessage())->toContain('3 tasks')
        ->and($response->getMessage())->toContain('fruit');

    // Verify tasks were created and planned
    $tasks = $taskManager->getTasks();
    expect($tasks)->toHaveCount(3)
        ->and($tasks[0]['description'])->toBe('Eat an apple')
        ->and($tasks[0]['status'])->toBe('completed')
        ->and($tasks[0]['plan'])->toContain('apple')
        ->and($tasks[1]['description'])->toBe('Eat a banana')
        ->and($tasks[1]['status'])->toBe('completed')
        ->and($tasks[2]['description'])->toBe('Eat grapes')
        ->and($tasks[2]['status'])->toBe('completed');
});

test('agent handles conversation when implementation request has no tasks', function () {
    $executor = new ToolExecutor(new NullLogger);
    $taskManager = new TaskManager(new NullLogger);

    $client = new ClientFake([
        // Classification response - implementation without tools
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'request_type' => 'implementation',
                            'requires_tools' => false,
                            'confidence' => 0.9,
                            'reasoning' => 'User wants something implemented',
                        ]),
                    ],
                ],
            ],
        ]),
        // No tasks extracted
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'No specific tasks to extract.',
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
                        'content' => 'I understand you want to implement something. Could you provide more specific details about what you would like me to help you with?',
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

    $response = $agent->processRequest('implement something');

    expect($response->isSuccess())->toBeTrue()
        ->and($response->getMessage())->toContain('provide more specific details')
        ->and($taskManager->getTasks())->toBeEmpty();
});
