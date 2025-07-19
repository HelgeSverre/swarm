#!/usr/bin/env php
<?php

/**
 * Test script for the three-pane UI
 * 
 * Usage: php test-three-pane-ui.php
 * Or set SWARM_UI_MODE=three-pane in your .env file
 */

require_once __DIR__ . '/vendor/autoload.php';

use HelgeSverre\Swarm\CLI\UIThreePanes;
use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\CLI\Activity\ConversationEntry;
use HelgeSverre\Swarm\CLI\Activity\ToolCallEntry;
use HelgeSverre\Swarm\Core\ToolResponse;

// Create the three-pane UI
$ui = new UIThreePanes();

// Welcome message
$ui->clearScreen();
echo "\033[2J\033[H"; // Clear screen and move to top
echo "Three-Pane UI Demo\n";
echo "==================\n\n";
echo "This demo shows the new three-pane UI with:\n";
echo "- Left sidebar: File tree navigation\n";
echo "- Main area: Scrollable chat history\n";
echo "- Right sidebar: Context notes\n\n";
echo "Press Enter to start the demo...";
fgets(STDIN);

// Initial state
$state = [
    'tasks' => [
        ['description' => 'Implement three-pane UI', 'status' => 'completed'],
        ['description' => 'Add scrollable chat history', 'status' => 'completed'],
        ['description' => 'Test keyboard navigation', 'status' => 'pending'],
    ],
    'current_task' => [
        'description' => 'Testing the new UI layout',
        'plan' => 'Verify all panes render correctly and keyboard navigation works',
    ],
    'conversation_history' => [
        [
            'role' => 'user',
            'content' => 'Create a three-pane UI layout with scrollable chat history',
            'timestamp' => time() - 300,
        ],
        [
            'role' => 'assistant',
            'content' => "I'll create a three-pane UI layout with the following features:\n\n1. **Left Sidebar**: File tree navigation\n2. **Main Area**: Scrollable chat history\n3. **Right Sidebar**: Context notes and metadata\n\nThe main chat area will support:\n- Full conversation history (not limited to recent messages)\n- Smooth scrolling with keyboard shortcuts\n- Visual indicators for scroll position\n- Auto-scroll to bottom on new messages",
            'timestamp' => time() - 250,
        ],
        [
            'role' => 'user',
            'content' => 'Great! Make sure the sidebars can be toggled',
            'timestamp' => time() - 200,
        ],
        [
            'role' => 'assistant',
            'content' => "I've implemented sidebar toggling with Option+B (on macOS) or Ctrl+B. The layout automatically adjusts when sidebars are hidden to give more space to the main chat area.",
            'timestamp' => time() - 150,
        ],
    ],
];

// Demo loop
$running = true;
while ($running) {
    $ui->refresh($state);
    
    echo "\n\nDemo Controls:\n";
    echo "1. Add a user message\n";
    echo "2. Add an assistant response\n";
    echo "3. Add a tool execution\n";
    echo "4. Simulate scrolling (add many messages)\n";
    echo "5. Toggle sidebars (simulated)\n";
    echo "q. Quit demo\n";
    echo "\nChoice: ";
    
    $choice = trim(fgets(STDIN));
    
    switch ($choice) {
        case '1':
            $state['conversation_history'][] = [
                'role' => 'user',
                'content' => 'This is a new user message added at ' . date('H:i:s'),
                'timestamp' => time(),
            ];
            break;
            
        case '2':
            $state['conversation_history'][] = [
                'role' => 'assistant',
                'content' => "This is an assistant response with multiple lines.\n\nIt demonstrates how the UI handles:\n- Line wrapping for long content\n- Proper formatting with indentation\n- Timestamp display\n\nThe scrollable area will automatically scroll to show new messages.",
                'timestamp' => time(),
            ];
            break;
            
        case '3':
            $state['conversation_history'][] = [
                'role' => 'tool',
                'content' => "Tool: ReadFile\nFile: /src/CLI/UIThreePanes.php\nResult: Successfully read file (850 lines)",
                'timestamp' => time(),
            ];
            break;
            
        case '4':
            // Add many messages to demonstrate scrolling
            for ($i = 0; $i < 20; $i++) {
                $state['conversation_history'][] = [
                    'role' => $i % 2 === 0 ? 'user' : 'assistant',
                    'content' => "Message #" . ($i + 1) . ": This demonstrates the scrollable chat history. You can use arrow keys, Page Up/Down, or Home/End to navigate through the conversation.",
                    'timestamp' => time() - (20 - $i) * 10,
                ];
            }
            echo "\nAdded 20 messages. Use arrow keys or Page Up/Down to scroll when the UI refreshes.\n";
            sleep(2);
            break;
            
        case '5':
            echo "\nIn the actual implementation, press Option+B (macOS) or Ctrl+B to toggle sidebars.\n";
            sleep(2);
            break;
            
        case 'q':
        case 'Q':
            $running = false;
            break;
            
        default:
            echo "\nInvalid choice. Please try again.\n";
            sleep(1);
    }
}

// Cleanup
$ui->cleanup();
echo "\nDemo completed. The three-pane UI is now available in Swarm!\n";
echo "Set SWARM_UI_MODE=three-pane in your .env file to use it.\n\n";