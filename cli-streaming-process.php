#!/usr/bin/env php
<?php

/**
 * Streaming background process entry point for async agent execution
 *
 * This script is launched by StreamingBackgroundProcessor to handle requests
 * in a separate process while streaming progress updates via stdout.
 *
 * Usage: php cli-streaming-process.php <base64_encoded_input>
 */

use HelgeSverre\Swarm\CLI\StreamingAsyncProcessor;

// Check command line arguments
if ($argc < 2) {
    fwrite(STDERR, "Usage: php cli-streaming-process.php <base64_encoded_input> [timeout]\n");
    exit(1);
}

// Get arguments
$encodedInput = $argv[1];
$timeout = isset($argv[2]) ? (int) $argv[2] : 300;

// Decode input
$input = base64_decode($encodedInput);
if ($input === false) {
    fwrite(STDERR, "Error: Failed to decode input\n");
    exit(1);
}

// Bootstrap the application
require_once __DIR__ . '/vendor/autoload.php';

try {
    // Process the request with streaming updates
    StreamingAsyncProcessor::processRequest($input, $timeout);
    exit(0);
} catch (Exception $e) {
    // Write error to stderr
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");

    // Also send error via stdout protocol
    echo json_encode([
        'type' => 'status',
        'status' => 'error',
        'error' => $e->getMessage(),
        'timestamp' => microtime(true),
    ]) . "\n";

    exit(1);
}
