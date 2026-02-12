<?php

use HelgeSverre\Swarm\Agent\ModelCapabilities;

// --- Reasoning model detection ---

test('gpt-5 is a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('gpt-5'))->toBeTrue();
});

test('gpt-5-mini is a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('gpt-5-mini'))->toBeTrue();
});

test('gpt-5-nano is a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('gpt-5-nano'))->toBeTrue();
});

test('gpt-5-chat-latest is NOT a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('gpt-5-chat-latest'))->toBeFalse();
});

test('gpt-4o is NOT a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('gpt-4o'))->toBeFalse();
});

test('gpt-4o-mini is NOT a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('gpt-4o-mini'))->toBeFalse();
});

test('gpt-4.1-nano is NOT a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('gpt-4.1-nano'))->toBeFalse();
});

test('o1 is a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('o1'))->toBeTrue();
});

test('o1-mini is a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('o1-mini'))->toBeTrue();
});

test('o3 is a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('o3'))->toBeTrue();
});

test('o3-mini is a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('o3-mini'))->toBeTrue();
});

test('o4-mini is a reasoning model', function () {
    expect(ModelCapabilities::isReasoningModel('o4-mini'))->toBeTrue();
});

// --- Temperature support ---

test('temperature is supported for gpt-4o-mini', function () {
    expect(ModelCapabilities::supportsTemperature('gpt-4o-mini'))->toBeTrue();
});

test('temperature is supported for gpt-5-chat-latest', function () {
    expect(ModelCapabilities::supportsTemperature('gpt-5-chat-latest'))->toBeTrue();
});

test('temperature is NOT supported for gpt-5', function () {
    expect(ModelCapabilities::supportsTemperature('gpt-5'))->toBeFalse();
});

test('temperature is NOT supported for gpt-5-mini', function () {
    expect(ModelCapabilities::supportsTemperature('gpt-5-mini'))->toBeFalse();
});

test('temperature is NOT supported for o1', function () {
    expect(ModelCapabilities::supportsTemperature('o1'))->toBeFalse();
});

// --- Reasoning effort support ---

test('reasoning effort is supported for gpt-5-mini', function () {
    expect(ModelCapabilities::supportsReasoningEffort('gpt-5-mini'))->toBeTrue();
});

test('reasoning effort is supported for o3', function () {
    expect(ModelCapabilities::supportsReasoningEffort('o3'))->toBeTrue();
});

test('reasoning effort is NOT supported for gpt-4o-mini', function () {
    expect(ModelCapabilities::supportsReasoningEffort('gpt-4o-mini'))->toBeFalse();
});

test('reasoning effort is NOT supported for gpt-5-chat-latest', function () {
    expect(ModelCapabilities::supportsReasoningEffort('gpt-5-chat-latest'))->toBeFalse();
});

// --- Verbosity support ---

test('verbosity is supported for gpt-5', function () {
    expect(ModelCapabilities::supportsVerbosity('gpt-5'))->toBeTrue();
});

test('verbosity is supported for gpt-5-mini', function () {
    expect(ModelCapabilities::supportsVerbosity('gpt-5-mini'))->toBeTrue();
});

test('verbosity is NOT supported for gpt-4o', function () {
    expect(ModelCapabilities::supportsVerbosity('gpt-4o'))->toBeFalse();
});

test('verbosity is NOT supported for o1', function () {
    expect(ModelCapabilities::supportsVerbosity('o1'))->toBeFalse();
});

test('verbosity is NOT supported for gpt-5-chat-latest', function () {
    expect(ModelCapabilities::supportsVerbosity('gpt-5-chat-latest'))->toBeFalse();
});

// --- buildRequestOptions ---

test('buildRequestOptions includes temperature for gpt-4o-mini', function () {
    $options = ModelCapabilities::buildRequestOptions('gpt-4o-mini', [['role' => 'user', 'content' => 'hi']]);

    expect($options)->toHaveKey('temperature')
        ->and($options)->not->toHaveKey('reasoning_effort')
        ->and($options)->not->toHaveKey('verbosity')
        ->and($options['model'])->toBe('gpt-4o-mini')
        ->and($options['temperature'])->toBe(0.7);
});

test('buildRequestOptions includes reasoning_effort for gpt-5-mini', function () {
    $options = ModelCapabilities::buildRequestOptions('gpt-5-mini', [['role' => 'user', 'content' => 'hi']]);

    expect($options)->toHaveKey('reasoning_effort')
        ->and($options)->toHaveKey('verbosity')
        ->and($options)->not->toHaveKey('temperature')
        ->and($options['reasoning_effort'])->toBe('medium')
        ->and($options['verbosity'])->toBe('medium');
});

test('buildRequestOptions respects custom reasoning_effort', function () {
    $options = ModelCapabilities::buildRequestOptions(
        'gpt-5-mini',
        [['role' => 'user', 'content' => 'hi']],
        ['reasoning_effort' => 'high'],
    );

    expect($options['reasoning_effort'])->toBe('high');
});

test('buildRequestOptions respects custom verbosity', function () {
    $options = ModelCapabilities::buildRequestOptions(
        'gpt-5',
        [['role' => 'user', 'content' => 'hi']],
        ['verbosity' => 'low'],
    );

    expect($options['verbosity'])->toBe('low');
});

test('buildRequestOptions passes through tools', function () {
    $tools = [['type' => 'function', 'function' => ['name' => 'test']]];
    $options = ModelCapabilities::buildRequestOptions(
        'gpt-4o-mini',
        [['role' => 'user', 'content' => 'hi']],
        ['tools' => $tools],
    );

    expect($options['tools'])->toBe($tools);
});

test('buildRequestOptions passes through response_format', function () {
    $format = ['type' => 'json_object'];
    $options = ModelCapabilities::buildRequestOptions(
        'gpt-4o-mini',
        [['role' => 'user', 'content' => 'hi']],
        ['response_format' => $format],
    );

    expect($options['response_format'])->toBe($format);
});

test('buildRequestOptions uses constructor defaults for reasoning_effort', function () {
    $options = ModelCapabilities::buildRequestOptions(
        'gpt-5-mini',
        [['role' => 'user', 'content' => 'hi']],
        [],
        'high',
        'low',
    );

    expect($options['reasoning_effort'])->toBe('high')
        ->and($options['verbosity'])->toBe('low');
});

test('buildRequestOptions for gpt-5-chat-latest behaves like gpt-4', function () {
    $options = ModelCapabilities::buildRequestOptions('gpt-5-chat-latest', [['role' => 'user', 'content' => 'hi']]);

    expect($options)->toHaveKey('temperature')
        ->and($options)->not->toHaveKey('reasoning_effort')
        ->and($options)->not->toHaveKey('verbosity');
});

test('buildRequestOptions always includes max_completion_tokens', function () {
    $options = ModelCapabilities::buildRequestOptions('gpt-4o-mini', [['role' => 'user', 'content' => 'hi']]);
    expect($options['max_completion_tokens'])->toBe(4000);

    $options = ModelCapabilities::buildRequestOptions('gpt-5-mini', [['role' => 'user', 'content' => 'hi']]);
    expect($options['max_completion_tokens'])->toBe(4000);
});

test('buildRequestOptions allows overriding max_completion_tokens', function () {
    $options = ModelCapabilities::buildRequestOptions(
        'gpt-5-mini',
        [['role' => 'user', 'content' => 'hi']],
        ['max_completion_tokens' => 100],
    );

    expect($options['max_completion_tokens'])->toBe(100);
});
