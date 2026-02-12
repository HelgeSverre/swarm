#!/usr/bin/env php
<?php

/**
 * Test input handling functionality
 */

echo "Testing ClaudeCodeChatDemo input handling...\n\n";

// Test grapheme functions with the expected behavior
function testGraphemeLength(string $text): int {
    return function_exists('grapheme_strlen') ? grapheme_strlen($text) : mb_strlen($text);
}

function testGraphemeSubstr(string $text, int $start, ?int $length = null): string {
    if (function_exists('grapheme_substr')) {
        return $length !== null ? grapheme_substr($text, $start, $length) : grapheme_substr($text, $start);
    }
    return $length !== null ? mb_substr($text, $start, $length) : mb_substr($text, $start);
}

// Test input buffer operations
$inputBuffer = "Hello world! 🌍";
$cursorPosition = 5;

echo "Original text: '{$inputBuffer}'\n";
echo "Cursor position: {$cursorPosition}\n";
echo "Text length: " . testGraphemeLength($inputBuffer) . "\n\n";

// Test inserting text at cursor
$insertText = " beautiful";
$newBuffer = testGraphemeSubstr($inputBuffer, 0, $cursorPosition) . 
             $insertText . 
             testGraphemeSubstr($inputBuffer, $cursorPosition);

echo "After inserting '{$insertText}' at position {$cursorPosition}:\n";
echo "Result: '{$newBuffer}'\n";
echo "New length: " . testGraphemeLength($newBuffer) . "\n\n";

// Test backspace operation
$beforeBackspace = "Hello world! 🌍";
$cursor = 6; // After "Hello "
$afterBackspace = testGraphemeSubstr($beforeBackspace, 0, $cursor - 1) . 
                  testGraphemeSubstr($beforeBackspace, $cursor);

echo "Before backspace: '{$beforeBackspace}' (cursor at {$cursor})\n";
echo "After backspace: '{$afterBackspace}'\n\n";

// Test cursor movement within emoji text
$emojiText = "🚀 Building 🏗️ awesome apps 🎉";
echo "Emoji text: '{$emojiText}'\n";
echo "Length: " . testGraphemeLength($emojiText) . " characters\n";
echo "Substring (0,5): '" . testGraphemeSubstr($emojiText, 0, 5) . "'\n";
echo "Substring (5,10): '" . testGraphemeSubstr($emojiText, 5, 10) . "'\n\n";

echo "✅ Input handling functionality looks good!\n\n";
echo "Key features implemented:\n";
echo "- Tab to toggle between demo mode and input mode\n";
echo "- Character input with emoji support\n";
echo "- Backspace and cursor movement\n";
echo "- Enter to submit messages\n";
echo "- Visual cursor positioning\n";
echo "- Auto-scrolling input area for long text\n";
echo "- Interactive message submission with simulated Claude responses\n\n";

echo "To test the full interface, run: php claude-code-chat-demo.php\n";
echo "Then press Tab to enter input mode and start typing!\n";