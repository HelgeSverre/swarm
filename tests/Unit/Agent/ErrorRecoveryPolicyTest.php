<?php

use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\Agent\ConversationBuffer;
use HelgeSverre\Swarm\Agent\ErrorRecoveryPolicy;
use Psr\Log\NullLogger;

test('noncritical fatal errors produce recovery metadata and append an error message', function () {
    $conversationBuffer = new ConversationBuffer;
    $policy = new ErrorRecoveryPolicy($conversationBuffer, new NullLogger);

    $response = $policy->handleFatalProcessingError(
        error: new RuntimeException('invalid JSON payload'),
        input: 'Explain PHP arrays',
        processingTime: 1.25,
    );

    expect($response->success)->toBeFalse()
        ->and($response->error)->toBe('invalid JSON payload')
        ->and($response->metadata['severity'])->toBe('medium')
        ->and($response->metadata['processing_time'])->toBe(1.25)
        ->and($response->metadata['recovery_suggestions'])->toContain('rephrase_request');

    $history = $conversationBuffer->getRecentContext();
    expect($history)->toHaveCount(1)
        ->and($history[0]['role'])->toBe('error')
        ->and($history[0]['content'])->toContain('Explain PHP arrays');
});

test('simplified fallback responses are wrapped with fallback metadata', function () {
    $policy = new ErrorRecoveryPolicy(new ConversationBuffer, new NullLogger);

    $response = $policy->runWithRecovery(
        userInput: 'Hello',
        primaryProcessor: function (string $input): AgentResponse {
            throw new RuntimeException('primary processing failed');
        },
        simplifiedProcessor: fn (string $input): array => [
            'response' => AgentResponse::success(
                content: 'Fallback conversation response',
                metadata: ['type' => 'conversation'],
            ),
            'classification' => [
                'request_type' => 'conversation',
                'confidence' => 0.5,
            ],
        ],
    );

    expect($response->success)->toBeFalse()
        ->and($response->content)->toBe('Fallback conversation response')
        ->and($response->error)->toBe('Used simplified processing due to primary system issues')
        ->and($response->metadata['fallback_mode'])->toBe('simplified')
        ->and($response->metadata['classification']['request_type'])->toBe('conversation');
});
