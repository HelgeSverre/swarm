<?php

use HelgeSverre\Swarm\Agent\ConversationBuffer;
use HelgeSverre\Swarm\Agent\RequestClassifier;
use HelgeSverre\Swarm\Enums\Agent\RequestType;
use Psr\Log\NullLogger;

test('quick greeting classification does not invoke llm callback', function () {
    $llmCalls = 0;
    $classifier = new RequestClassifier(
        conversationBuffer: new ConversationBuffer,
        llmCallback: function (array $messages, array $options = []) use (&$llmCalls): string {
            $llmCalls++;

            return json_encode(['type' => RequestType::Conversation->value]);
        },
        logger: new NullLogger,
    );

    $classification = $classifier->quickClassify('Hello');

    expect($llmCalls)->toBe(0)
        ->and($classification['request_type'])->toBe(RequestType::Conversation->value)
        ->and($classification['confidence'])->toBe(0.95)
        ->and($classification['complexity'])->toBe('simple');
});

test('quick classification can use llm for short ambiguous input', function () {
    $llmCalls = 0;
    $classifier = new RequestClassifier(
        conversationBuffer: new ConversationBuffer,
        llmCallback: function (array $messages, array $options = []) use (&$llmCalls): string {
            $llmCalls++;

            return json_encode([
                'type' => RequestType::Demonstration->value,
                'confidence' => 0.91,
                'complexity' => 'simple',
            ]);
        },
        logger: new NullLogger,
    );

    $classification = $classifier->quickClassify('snippet please');

    expect($llmCalls)->toBe(1)
        ->and($classification['request_type'])->toBe(RequestType::Demonstration->value)
        ->and($classification['requires_tools'])->toBeFalse()
        ->and($classification['confidence'])->toBe(0.91)
        ->and($classification['reasoning'])->toBe('Quick LLM classification');
});

test('consistency classification falls back to quick classification when reasoning paths fail', function () {
    $llmCalls = 0;
    $classifier = new RequestClassifier(
        conversationBuffer: new ConversationBuffer,
        llmCallback: function (array $messages, array $options = []) use (&$llmCalls): string {
            $llmCalls++;

            return 'not-json';
        },
        logger: new NullLogger,
    );

    $classification = $classifier->classify('Explain PHP arrays', [
        'complexity' => 'moderate',
    ]);

    expect($llmCalls)->toBe(3)
        ->and($classification['request_type'])->toBe(RequestType::Explanation->value)
        ->and($classification['confidence'])->toBe(0.8)
        ->and($classification['reasoning'])->toBe('Request for explanation or description');
});
