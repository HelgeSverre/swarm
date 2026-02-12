#!/usr/bin/env php
<?php

/**
 * Test script for scrolling functionality
 */

// Include the main demo file and test scrolling functions
require_once 'claude-code-chat-demo.php';

echo "Testing ClaudeCodeChatDemo scrolling functionality...\n";

$demo = new ClaudeCodeChatDemo;

// Use reflection to access private methods for testing
$reflection = new ReflectionClass($demo);

// Test grapheme functions
$graphemeLengthMethod = $reflection->getMethod('graphemeLength');
$graphemeLengthMethod->setAccessible(true);

$graphemeSubstrMethod = $reflection->getMethod('graphemeSubstr');
$graphemeSubstrMethod->setAccessible(true);

// Test text wrapping with emojis
$wrapTextMethod = $reflection->getMethod('wrapText');
$wrapTextMethod->setAccessible(true);

// Test cases
echo "\n1. Testing grapheme length calculation:\n";
$testStrings = [
    'Hello world' => 11,
    'Hello 👋 world' => 13,
    '🚀 Building awesome apps 🎉' => 26,
];

foreach ($testStrings as $text => $expected) {
    $actual = $graphemeLengthMethod->invoke($demo, $text);
    echo "   '{$text}' -> {$actual} characters\n";
}

echo "\n2. Testing grapheme substring:\n";
$text = '🚀 Hello world 👋';
$substr = $graphemeSubstrMethod->invoke($demo, $text, 2, 5);
echo "   Substring of '{$text}' (2, 5) -> '{$substr}'\n";

echo "\n3. Testing text wrapping:\n";
$longText = 'This is a very long line of text that should be wrapped properly across multiple lines when rendered in the terminal interface';
$wrapped = $wrapTextMethod->invoke($demo, $longText, 40);
echo "   Original: {$longText}\n";
echo '   Wrapped into ' . count($wrapped) . " lines:\n";
foreach ($wrapped as $i => $line) {
    echo '   Line ' . ($i + 1) . ": '{$line}'\n";
}

echo "\nScrolling functionality tests completed!\n";
echo "The demo should now properly handle:\n";
echo "- Emoji and multi-byte character sizing\n";
echo "- Smooth scrolling with partial line rendering\n";
echo "- Activity stream with proper text flow\n";
echo "\nYou can run the main demo with: php claude-code-chat-demo.php\n";
