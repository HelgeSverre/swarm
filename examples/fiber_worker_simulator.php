#!/usr/bin/env php
<?php

// Worker process simulator for Fiber POC
$processId = $argv[1] ?? 'unknown';

// Simulate sending progress updates
$steps = [
    ['type' => 'status', 'status' => 'initializing', 'message' => 'Starting worker...'],
    ['type' => 'progress', 'operation' => 'classifying', 'message' => 'Analyzing request...'],
    ['type' => 'state_sync', 'data' => ['tasks' => [['description' => 'Sample task', 'status' => 'running']]]],
    ['type' => 'progress', 'operation' => 'executing', 'message' => 'Executing task...'],
    ['type' => 'tool_started', 'tool' => 'ReadFile', 'message' => 'Reading file...'],
    ['type' => 'tool_completed', 'tool' => 'ReadFile', 'message' => 'File read complete'],
    ['type' => 'status', 'status' => 'completed', 'message' => 'Task completed successfully'],
];

foreach ($steps as $i => $update) {
    $update['timestamp'] = microtime(true);
    $update['processId'] = $processId;
    $update['step'] = $i + 1;
    $update['total'] = count($steps);

    echo json_encode($update) . "\n";
    flush();

    // Simulate work
    usleep(500000); // 0.5 seconds per step
}
