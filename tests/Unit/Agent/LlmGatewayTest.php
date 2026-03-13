<?php

use HelgeSverre\Swarm\Agent\AgentProgressReporter;
use HelgeSverre\Swarm\Agent\ConversationBuffer;
use HelgeSverre\Swarm\Agent\ErrorRecoveryPolicy;
use HelgeSverre\Swarm\Agent\LlmGateway;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ProcessingEvent;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;
use Psr\Log\NullLogger;

test('llm gateway strips tools when custom tools are disabled and appends assistant content', function () {
    $conversationBuffer = new ConversationBuffer;
    $client = new ClientFake([
        CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Model response.',
                    ],
                ],
            ],
        ]),
    ]);
    $eventBus = new EventBus;
    $progressReporter = new AgentProgressReporter($eventBus);
    $receivedEvents = [];
    $eventBus->on(ProcessingEvent::class, function (ProcessingEvent $event) use (&$receivedEvents): void {
        $receivedEvents[] = $event;
    });

    $gateway = new LlmGateway(
        llmClient: $client,
        conversationBuffer: $conversationBuffer,
        errorRecoveryPolicy: new ErrorRecoveryPolicy($conversationBuffer, new NullLogger),
        progressReporter: $progressReporter,
        logger: new NullLogger,
        delayCallback: fn (float $seconds): null => null,
    );

    $gateway->setUseCustomTools(false);
    $response = $gateway->call(
        messages: [['role' => 'user', 'content' => 'Say hello']],
        options: [
            'tools' => [[
                'type' => 'function',
                'function' => ['name' => 'example_tool'],
            ]],
        ],
    );

    expect($response)->toBe('Model response.');

    $history = $conversationBuffer->getRecentContext();
    expect($history)->toHaveCount(1)
        ->and($history[0]['role'])->toBe('assistant')
        ->and($history[0]['content'])->toBe('Model response.')
        ->and($receivedEvents)->toHaveCount(1)
        ->and($receivedEvents[0]->operation)->toBe('calling_openai');

    $client->chat()->assertSent(function ($method, $parameters) {
        return $method === 'create' && ! array_key_exists('tools', $parameters);
    });
});

test('llm gateway returns fallback response after retries are exhausted without sleeping', function () {
    $conversationBuffer = new ConversationBuffer;
    $client = new ClientFake([
        new RuntimeException('network timeout'),
        new RuntimeException('network timeout'),
        new RuntimeException('network timeout'),
        new RuntimeException('network timeout'),
    ]);

    $delays = [];
    $gateway = new LlmGateway(
        llmClient: $client,
        conversationBuffer: $conversationBuffer,
        errorRecoveryPolicy: new ErrorRecoveryPolicy($conversationBuffer, new NullLogger),
        progressReporter: new AgentProgressReporter(new EventBus),
        logger: new NullLogger,
        delayCallback: function (float $seconds) use (&$delays): void {
            $delays[] = $seconds;
        },
    );

    $response = $gateway->call([
        ['role' => 'user', 'content' => 'Explain retry behavior'],
    ]);

    expect($response)->toContain('technical difficulties')
        ->and($response)->toContain('network timeout')
        ->and($delays)->toHaveCount(3);

    $client->chat()->assertSent(4);
});
