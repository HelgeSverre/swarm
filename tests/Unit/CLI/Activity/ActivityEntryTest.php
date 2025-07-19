<?php

use HelgeSverre\Swarm\CLI\Activity\ConversationEntry;
use HelgeSverre\Swarm\CLI\Activity\NotificationEntry;
use HelgeSverre\Swarm\CLI\Activity\ToolCallEntry;
use HelgeSverre\Swarm\Core\ToolResponse;
use HelgeSverre\Swarm\Enums\CLI\ActivityType;

test('ConversationEntry formats plain text correctly', function () {
    $entry = new ConversationEntry('user', 'Hello, can you help me?', time());

    expect($entry->getMessage())->toBe('Hello, can you help me?');
    expect($entry->type)->toBe(ActivityType::User);
    expect($entry->hasIcon())->toBeTrue();
    expect($entry->getIcon())->toBe('💬');
});

test('ConversationEntry formats function calls correctly', function () {
    $functionCall = json_encode([
        'function_call' => [
            'name' => 'write_file',
            'arguments' => json_encode(['path' => '/tmp/test.txt', 'content' => 'Hello world']),
        ],
    ]);

    $entry = new ConversationEntry('assistant', $functionCall, time());

    expect($entry->getMessage())->toBe('📝 Writing to test.txt');
    expect($entry->isFunctionCall())->toBeTrue();
    expect($entry->getFunctionName())->toBe('write_file');
});

test('ConversationEntry formats file lists correctly', function () {
    $fileList = json_encode([
        '/Users/test/file1.txt',
        '/Users/test/file2.txt',
        '/Users/test/file3.txt',
        '/Users/test/file4.txt',
        '/Users/test/file5.txt',
    ]);

    $entry = new ConversationEntry('assistant', $fileList, time());

    expect($entry->getMessage())->toBe('📄 Listed 5 items: file1.txt, file2.txt, file3.txt (+2 more)');
});

test('ToolCallEntry formats different tools correctly', function () {
    // Test write_file
    $writeEntry = new ToolCallEntry(
        'write_file',
        ['path' => '/tmp/hello.txt', 'content' => 'Hello'],
        ToolResponse::success(['bytes_written' => 5]),
        time()
    );
    expect($writeEntry->getMessage())->toBe('📝 write_file: hello.txt → 5 bytes');

    // Test bash
    $bashEntry = new ToolCallEntry(
        'bash',
        ['command' => 'echo "Hello World"'],
        ToolResponse::success(['return_code' => 0]),
        time()
    );
    expect($bashEntry->getMessage())->toBe('⚡ bash: echo "Hello World" → success');

    // Test grep
    $grepEntry = new ToolCallEntry(
        'grep',
        ['search' => 'TODO', 'directory' => '/src'],
        ToolResponse::success(['count' => 5]),
        time()
    );
    expect($grepEntry->getMessage())->toBe("🔍 grep: 'TODO' in src → 5 results");
});

test('NotificationEntry includes icon in message', function () {
    $entry = NotificationEntry::error('Something went wrong', time());

    expect($entry->getMessage())->toBe('Something went wrong');
    expect($entry->hasIcon())->toBeFalse();
    expect($entry->type)->toBe(ActivityType::Notification);
});

test('Activity entries convert to array for backwards compatibility', function () {
    $entry = new ConversationEntry('user', 'Test message', 1234567890);
    $array = $entry->toArray();

    expect($array)->toHaveKeys(['type', 'message', 'color', 'timestamp']);
    expect($array['type'])->toBe('user');
    expect($array['message'])->toBe('Test message');
    expect($array['timestamp'])->toBe(1234567890);
});
