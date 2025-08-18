#!/usr/bin/env php
<?php

/**
 * Test script to verify terminal restoration works correctly
 */

require __DIR__ . '/vendor/autoload.php';

use HelgeSverre\Swarm\CLI\Terminal\FullTerminalUI;
use HelgeSverre\Swarm\Events\EventBus;

echo "Testing terminal restoration...\n";
echo "This text should remain in scrollback after cleanup.\n";
echo "Line 1\n";
echo "Line 2\n";
echo "Line 3\n";
echo "Line 4\n";
echo "Line 5\n";

// Create terminal UI
$eventBus = EventBus::getInstance();
$ui = new FullTerminalUI($eventBus);

// Sleep to show UI
sleep(2);

// Explicitly cleanup
$ui->cleanup();

echo "\nTerminal should be restored now.\n";
echo "You should be able to scroll up and see the previous lines.\n";
echo "Test complete!\n";
