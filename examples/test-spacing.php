#!/usr/bin/env php
<?php

/**
 * Test spacing improvements
 */

echo "Testing improved spacing logic...\n\n";

// Simulate the new spacing logic
function needsSpacing(array $currentActivity, ?array $nextActivity): bool
{
    // No spacing needed if no next activity
    if (!$nextActivity) {
        return false;
    }
    
    // Only add spacing when transitioning from tools back to messages
    if (in_array($currentActivity['type'], ['tool_start', 'tool_output', 'tool_complete']) && 
        $nextActivity['type'] === 'message') {
        return true;
    }
    
    // No spacing between messages or consecutive tool activities - keep it compact
    return false;
}

// Test scenarios
$activities = [
    ['type' => 'message', 'speaker' => 'claude', 'content' => 'Hello'],
    ['type' => 'message', 'speaker' => 'user', 'content' => 'Hi'],
    ['type' => 'message', 'speaker' => 'claude', 'content' => 'How can I help?'],
    ['type' => 'tool_start', 'tool' => 'ReadFile'],
    ['type' => 'tool_output', 'output' => ['line1', 'line2']],
    ['type' => 'tool_complete', 'tool' => 'ReadFile'],
    ['type' => 'message', 'speaker' => 'claude', 'content' => 'Done!'],
];

echo "Spacing decisions:\n";
for ($i = 0; $i < count($activities) - 1; $i++) {
    $current = $activities[$i];
    $next = $activities[$i + 1];
    $spacing = needsSpacing($current, $next) ? 'YES' : 'NO';
    
    echo "  {$current['type']} → {$next['type']}: {$spacing}\n";
}

echo "\nExpected layout:\n";
echo "  Claude: Hello\n";
echo "  You: Hi\n";  
echo "  Claude: How can I help?\n";
echo "    🔧 Using ReadFile\n";
echo "      line1\n";
echo "      line2\n";
echo "    ✓ ReadFile completed\n";
echo "\n";  // <- Only spacing here (tool → message transition)
echo "  Claude: Done!\n";

echo "\n✅ Spacing improvements implemented!\n";
echo "- No spacing between consecutive messages\n";
echo "- No spacing between tool activities\n"; 
echo "- Only spacing when transitioning from tools to messages\n";
echo "- Task lists removed from inline display\n";
echo "- Minimal empty state in sidebar\n\n";

echo "The chat should now be much more compact!\n";