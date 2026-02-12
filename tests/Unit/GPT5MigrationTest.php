<?php

use HelgeSverre\Swarm\Agent\ConversationBuffer;

test('conversation buffer manages memory correctly', function () {
    $buffer = new ConversationBuffer(10); // Small buffer for testing
    
    // Add messages up to limit
    for ($i = 0; $i < 15; $i++) {
        $buffer->addMessage('user', "Test message {$i}");
    }
    
    $stats = $buffer->getStats();
    expect($stats['message_count'])->toBeLessThanOrEqual(10);
    expect($stats)->toHaveKey('max_size');
    expect($stats)->toHaveKey('memory_usage');
});

test('conversation buffer calculates relevance correctly', function () {
    $buffer = new ConversationBuffer();
    
    // Add messages with different relevance
    $buffer->addMessage('user', 'How do I create a PHP class?');
    $buffer->addMessage('assistant', 'You can create a PHP class using the class keyword...');
    $buffer->addMessage('user', 'What is the weather like?');
    $buffer->addMessage('user', 'Can you help me implement a PHP function?');
    
    // Get context for PHP-related task
    $context = $buffer->getOptimalContext('Create a new PHP method', 1000);
    
    expect($context)->toBeArray();
    expect(count($context))->toBeGreaterThan(0);
});

test('conversation buffer estimates memory usage', function () {
    $buffer = new ConversationBuffer();
    
    // Add some test messages
    for ($i = 0; $i < 5; $i++) {
        $buffer->addMessage('user', str_repeat('test message ', 50));
    }
    
    $stats = $buffer->getStats();
    expect($stats['memory_usage'])->toMatch('/\d+(\.\d+)?\s+(B|KB|MB|GB)/');
});

test('conversation buffer prevents unbounded growth', function () {
    $buffer = new ConversationBuffer(50);
    
    // Add many messages (more than max)
    for ($i = 0; $i < 100; $i++) {
        $buffer->addMessage('user', "Message {$i}");
    }
    
    $stats = $buffer->getStats();
    expect($stats['message_count'])->toBeLessThanOrEqual(50);
});

test('conversation buffer selects relevant context', function () {
    $buffer = new ConversationBuffer();
    
    // Add various messages
    $buffer->addMessage('user', 'I need help with PHP arrays');
    $buffer->addMessage('assistant', 'PHP arrays can be created using array() or []');
    $buffer->addMessage('user', 'How about JavaScript objects?');
    $buffer->addMessage('assistant', 'JavaScript objects use {} syntax');
    $buffer->addMessage('user', 'Back to PHP - how do I iterate arrays?');
    
    // Get context for PHP array task
    $context = $buffer->getOptimalContext('Show me PHP array iteration methods', 2000);
    
    // Should have selected PHP-related messages
    $contextText = implode(' ', array_column($context, 'content'));
    expect($contextText)->toContain('PHP');
    expect($contextText)->toContain('array');
});

test('conversation buffer handles empty context gracefully', function () {
    $buffer = new ConversationBuffer();
    
    // No messages added
    $context = $buffer->getOptimalContext('Any task', 1000);
    
    expect($context)->toBeArray();
    expect($context)->toBeEmpty();
});

test('conversation buffer recent context fallback works', function () {
    $buffer = new ConversationBuffer();
    
    // Add some messages
    for ($i = 0; $i < 10; $i++) {
        $buffer->addMessage('user', "Message {$i}");
    }
    
    $recentContext = $buffer->getRecentContext(5);
    
    expect($recentContext)->toBeArray();
    expect(count($recentContext))->toBe(5);
    
    // Should contain the most recent messages
    $lastMessage = $recentContext[4]['content'] ?? '';
    expect($lastMessage)->toBe('Message 9');
});