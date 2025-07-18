#!/usr/bin/env php
<?php

/**
 * Background process entry point for async agent execution
 *
 * This script is launched by BackgroundProcessor to handle requests
 * in a separate process while the main UI remains responsive.
 *
 * Usage: php cli-process.php <base64_encoded_input> <status_file_path>
 */

use HelgeSverre\Swarm\CLI\AsyncProcessor;

// Check command line arguments
if ($argc !== 3) {
    fwrite(STDERR, "Usage: php cli-process.php <base64_encoded_input> <status_file_path>\n");
    exit(1);
}

// Get arguments
$encodedInput = $argv[1];
$statusFile = $argv[2];

// Decode input
$input = base64_decode($encodedInput);
if ($input === false) {
    fwrite(STDERR, "Error: Failed to decode input\n");
    exit(1);
}

// Bootstrap the application
require_once __DIR__ . '/vendor/autoload.php';

// Debug: Check if class exists
if (! class_exists('HelgeSverre\Swarm\Prompts\PromptTemplates')) {
    fwrite(STDERR, "Error: PromptTemplates class not found after autoload\n");
    // Try to debug further
    $classFile = __DIR__ . '/src/Prompts/PromptTemplates.php';
    fwrite(STDERR, 'Class file exists: ' . (file_exists($classFile) ? 'yes' : 'no') . "\n");
}

try {
    // Process the request
    AsyncProcessor::processRequest($input, $statusFile);
    exit(0);
} catch (Exception $e) {
    // Write error to status file
    file_put_contents($statusFile, json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'timestamp' => time(),
    ]));

    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
