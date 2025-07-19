<?php

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI\Testing\ClientFake;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->client = new ClientFake([]);
    $this->toolExecutor = ToolExecutor::createWithDefaultTools();
    $this->taskManager = new TaskManager(new NullLogger);
    $this->agent = new CodingAgent(
        $this->toolExecutor,
        $this->taskManager,
        $this->client,
        new NullLogger
    );
});

test('agent can set and get conversation history', function () {
    // Create some conversation history
    $history = [
        [
            'role' => 'user',
            'content' => 'Hello, how are you?',
            'timestamp' => time() - 60,
        ],
        [
            'role' => 'assistant',
            'content' => 'I am doing well, thank you!',
            'timestamp' => time() - 30,
        ],
        [
            'role' => 'user',
            'content' => 'Can you help me with coding?',
            'timestamp' => time(),
        ],
    ];

    // Set the conversation history
    $this->agent->setConversationHistory($history);

    // Get the conversation history (truncated version)
    $retrievedHistory = $this->agent->getConversationHistory();

    // Should have all 3 messages (2 user, 1 assistant)
    expect($retrievedHistory)->toHaveCount(3);

    // Check the content is preserved
    expect($retrievedHistory[0]['content'])->toBe('Hello, how are you?');
    expect($retrievedHistory[1]['content'])->toBe('I am doing well, thank you!');
    expect($retrievedHistory[2]['content'])->toBe('Can you help me with coding?');
});

test('conversation history persists through multiple calls', function () {
    // Set up conversation history
    $history = [
        [
            'role' => 'user',
            'content' => 'What is PHP?',
            'timestamp' => time() - 60,
        ],
        [
            'role' => 'assistant',
            'content' => 'PHP is a server-side scripting language.',
            'timestamp' => time() - 30,
        ],
    ];

    $this->agent->setConversationHistory($history);

    // Get conversation history - should include our set history
    $retrievedHistory = $this->agent->getConversationHistory();

    expect($retrievedHistory)->toHaveCount(2);
    expect($retrievedHistory[0]['content'])->toBe('What is PHP?');
    expect($retrievedHistory[1]['content'])->toBe('PHP is a server-side scripting language.');

    // Test that conversation history is used in message building
    // Use reflection to test the protected buildMessagesWithHistory method
    $reflection = new ReflectionClass($this->agent);
    $buildMethod = $reflection->getMethod('buildMessagesWithHistory');
    $buildMethod->setAccessible(true);

    $messages = $buildMethod->invoke($this->agent, 'Tell me more about PHP');

    // Should have system message + 2 history messages + 1 new message
    expect($messages)->toHaveCount(4);
    expect($messages[0]['role'])->toBe('system'); // System prompt
    expect($messages[1]['content'])->toBe('What is PHP?');
    expect($messages[2]['content'])->toBe('PHP is a server-side scripting language.');
    expect($messages[3]['content'])->toBe('Tell me more about PHP');
});

test('conversation history merging prevents duplicates', function () {
    // This test would be more of an integration test with the Swarm class
    // but we can test the logic conceptually

    $existingHistory = [
        ['role' => 'user', 'content' => 'Hello', 'timestamp' => 1000],
        ['role' => 'assistant', 'content' => 'Hi there!', 'timestamp' => 1001],
    ];

    $newHistory = [
        ['role' => 'assistant', 'content' => 'Hi there!', 'timestamp' => 1001], // Duplicate
        ['role' => 'user', 'content' => 'How are you?', 'timestamp' => 1002], // New
    ];

    // Simulate the merging logic from handleStateSyncUpdate
    $historyMap = [];
    foreach ($existingHistory as $entry) {
        $key = $entry['timestamp'] . '_' . $entry['role'] . '_' . md5($entry['content']);
        $historyMap[$key] = $entry;
    }

    foreach ($newHistory as $entry) {
        $key = $entry['timestamp'] . '_' . $entry['role'] . '_' . md5($entry['content']);
        if (! isset($historyMap[$key])) {
            $historyMap[$key] = $entry;
        }
    }

    $mergedHistory = array_values($historyMap);
    usort($mergedHistory, fn ($a, $b) => $a['timestamp'] - $b['timestamp']);

    // Should have 3 entries (not 4) due to deduplication
    expect($mergedHistory)->toHaveCount(3);
    expect($mergedHistory[0]['content'])->toBe('Hello');
    expect($mergedHistory[1]['content'])->toBe('Hi there!');
    expect($mergedHistory[2]['content'])->toBe('How are you?');
});
