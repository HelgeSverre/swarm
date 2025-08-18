#!/usr/bin/env php
<?php

/**
 * Simple test to verify Fiber functionality works
 */
echo "Testing PHP Fibers functionality...\n\n";

// Test 1: Basic Fiber creation and execution
echo "Test 1: Basic Fiber\n";
$fiber1 = new Fiber(function () {
    echo "  Fiber started\n";
    $value = Fiber::suspend('suspended');
    echo "  Fiber resumed with: {$value}\n";

    return 'completed';
});

$result = $fiber1->start();
echo "  Fiber suspended with: {$result}\n";
$final = $fiber1->resume('resumed');
echo "  Fiber completed with: {$final}\n\n";

// Test 2: Multiple cooperating fibers
echo "Test 2: Multiple Fibers\n";
$counter = 0;
$maxIterations = 5;

$fiber2 = new Fiber(function () use (&$counter, $maxIterations) {
    while ($counter < $maxIterations) {
        echo "  Fiber A: counter = {$counter}\n";
        $counter++;
        Fiber::suspend();
    }
});

$fiber3 = new Fiber(function () use (&$counter, $maxIterations) {
    while ($counter < $maxIterations) {
        echo "  Fiber B: counter = {$counter}\n";
        $counter++;
        Fiber::suspend();
    }
});

$fiber2->start();
$fiber3->start();

while ($fiber2->isSuspended() || $fiber3->isSuspended()) {
    if ($fiber2->isSuspended()) {
        $fiber2->resume();
    }
    if ($fiber3->isSuspended()) {
        $fiber3->resume();
    }
}

echo "\nTest 3: Simulating async process monitoring\n";

// Simulate monitoring multiple processes with fibers
$processes = [];
$processCount = 3;

for ($i = 0; $i < $processCount; $i++) {
    $processId = "proc_{$i}";
    $processes[$processId] = new Fiber(function () use ($processId) {
        $steps = 3;
        for ($step = 1; $step <= $steps; $step++) {
            echo "  [{$processId}] Step {$step}/{$steps}\n";
            Fiber::suspend();
        }
        echo "  [{$processId}] Completed!\n";
    });
    $processes[$processId]->start();
}

// Main loop resuming all process fibers
$iterations = 0;
while (true) {
    $activeFibers = 0;
    foreach ($processes as $id => $fiber) {
        if ($fiber->isSuspended()) {
            $fiber->resume();
            $activeFibers++;
        }
    }

    if ($activeFibers === 0) {
        break;
    }

    $iterations++;
    echo "  [Main] Iteration {$iterations}, active fibers: {$activeFibers}\n";
}

echo "\nAll tests completed successfully!\n";
echo "PHP Fibers are working correctly.\n";
echo "\nKey benefits for Swarm refactoring:\n";
echo "- No blocking on I/O operations\n";
echo "- Cooperative multitasking within single process\n";
echo "- Better responsiveness than 50ms polling\n";
echo "- Each subprocess can have dedicated monitoring fiber\n";
echo "- UI rendering can happen at 60 FPS independently\n";
