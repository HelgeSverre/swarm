<?php

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;
use Psr\Log\NullLogger;

test('coding agent uses quick routing for simple greetings', function () {
    $agent = new CodingAgent(
        toolExecutor: ToolExecutor::createWithDefaultTools(),
        taskManager: new TaskManager(new NullLogger),
        llmClient: new ClientFake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello there.',
                        ],
                    ],
                ],
            ]),
        ]),
        logger: new NullLogger,
    );

    $response = $agent->processRequest('Hello');

    expect($response->success)->toBeTrue()
        ->and($response->content)->toBe('Hello there.')
        ->and($response->metadata)->toHaveKey('processing_time');
});

test('coding agent uses deep classification for non-trivial explanation requests', function () {
    $agent = new CodingAgent(
        toolExecutor: ToolExecutor::createWithDefaultTools(),
        taskManager: new TaskManager(new NullLogger),
        llmClient: new ClientFake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'complexity' => 'moderate',
                                'required_capabilities' => ['explanation'],
                            ]),
                        ],
                    ],
                ],
            ]),
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'request_type' => 'explanation',
                                'requires_tools' => false,
                                'confidence' => 0.61,
                                'reasoning' => 'literal',
                                'complexity' => 'moderate',
                            ]),
                        ],
                    ],
                ],
            ]),
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'request_type' => 'explanation',
                                'requires_tools' => false,
                                'confidence' => 0.82,
                                'reasoning' => 'contextual',
                                'complexity' => 'moderate',
                            ]),
                        ],
                    ],
                ],
            ]),
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'request_type' => 'query',
                                'requires_tools' => false,
                                'confidence' => 0.73,
                                'reasoning' => 'pragmatic',
                                'complexity' => 'moderate',
                            ]),
                        ],
                    ],
                ],
            ]),
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'PHP arrays are ordered maps that can behave like lists or dictionaries.',
                        ],
                    ],
                ],
            ]),
        ]),
        logger: new NullLogger,
    );

    $response = $agent->processRequest('Explain PHP arrays in simple terms');

    expect($response->success)->toBeTrue()
        ->and($response->content)->toContain('ordered maps');
});
