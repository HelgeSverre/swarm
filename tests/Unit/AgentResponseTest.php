<?php

use HelgeSverre\Swarm\Agent\AgentResponse;

test('can create success response', function () {
    $response = AgentResponse::success('Task completed successfully');

    expect($response->getMessage())->toBe('Task completed successfully');
    expect($response->isSuccess())->toBeTrue();
});

test('returns empty string for null message', function () {
    $response = new AgentResponse;

    expect($response->getMessage())->toBe('');
    expect($response->isSuccess())->toBeFalse();
});
