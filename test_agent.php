#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\ToolRouter;
use HelgeSverre\Swarm\Task\TaskManager;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use OpenAI;

// Set up logger
$logger = new Logger('test');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create OpenAI client
$client = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? '');

// Create dependencies
$toolRouter = new ToolRouter($logger);
$taskManager = new TaskManager;

// Create agent
$agent = new CodingAgent(
    $toolRouter,
    $taskManager,
    $client,
    $logger,
    $_ENV['OPENAI_MODEL'] ?? 'gpt-4',
    0.7
);

// Test cases
$testCases = [
    'Show me an example of a PHP singleton pattern',
    'Create a new file called test.php with a hello world script',
    'Explain what dependency injection is',
    'What is the current directory?',
    'How do I use composer to install packages?',
];

echo "Testing improved CodingAgent with various request types...\n\n";

foreach ($testCases as $index => $testCase) {
    echo 'Test ' . ($index + 1) . ": {$testCase}\n";
    echo str_repeat('-', 50) . "\n";

    try {
        $response = $agent->processRequest($testCase);
        echo 'Response Type: ' . ($response->isSuccess() ? 'Success' : 'Error') . "\n";
        echo 'Message: ' . mb_substr($response->getMessage(), 0, 200) . "...\n\n";
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage() . "\n\n";
    }
}
