<?php

declare(strict_types=1);

use HelgeSverre\Swarm\CLI\Process\Message\WorkerUpdate;
use HelgeSverre\Swarm\CLI\Process\Message\WorkerUpdateType;

test('worker update parses known types and preserves payload', function () {
    $update = WorkerUpdate::fromArray([
        'type' => 'state_sync',
        'data' => ['tasks' => [['id' => '1']]],
    ]);

    expect($update->type)->toBe(WorkerUpdateType::StateSync)
        ->and($update->toArray()['data']['tasks'][0]['id'])->toBe('1');
});

test('worker update can attach process id immutably', function () {
    $update = WorkerUpdate::fromArray([
        'type' => 'progress',
        'message' => 'working',
    ])->withProcessId('proc_123');

    expect($update->processId)->toBe('proc_123')
        ->and($update->toArray()['processId'])->toBe('proc_123');
});

test('worker update exposes completed status response metadata', function () {
    $update = WorkerUpdate::fromArray([
        'type' => 'status',
        'status' => 'completed',
        'response' => ['message' => 'done', 'success' => true],
    ]);

    expect($update->isCompletedStatus())->toBeTrue()
        ->and($update->status())->toBe('completed')
        ->and($update->response())->toBe(['message' => 'done', 'success' => true]);
});

test('unknown worker update types are normalized safely', function () {
    $update = WorkerUpdate::fromArray([
        'type' => 'surprise',
        'message' => 'unknown',
    ]);

    expect($update->type)->toBe(WorkerUpdateType::Unknown)
        ->and($update->toArray()['type'])->toBe('unknown');
});
