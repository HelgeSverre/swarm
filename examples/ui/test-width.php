#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/WidthCalculator.php';

// Test emoji width calculations
$testStrings = [
    '💮',
    '💮 swarm',
    ' 💮 swarm ',
    ' 💮 swarm │ ',
    '● Refactor database layer',
    'working (2/4)',
];

echo "Testing emoji width calculations:\n";
echo "================================\n";

foreach ($testStrings as $str) {
    $mbLen = mb_strlen($str);
    $calcWidth = WidthCalculator::width($str);
    $clean = WidthCalculator::stripAnsiCodes($str);

    echo sprintf("String: '%s'\n", $str);
    echo sprintf("  mb_strlen: %d\n", $mbLen);
    echo sprintf("  WidthCalculator: %d\n", $calcWidth);
    echo sprintf("  Clean: '%s'\n", $clean);
    echo "\n";
}

// Test terminal width
$termWidth = (int) exec('tput cols') ?: 80;
echo "Terminal width: {$termWidth}\n";

// Test actual header calculation
echo "\nHeader calculation test:\n";
echo "=======================\n";

$prefix = ' 💮 swarm │ ';
$taskText = 'Refactor database layer';
$task = '● ' . $taskText . ' │ ';
$status = 'working (2/4)';

$prefixWidth = WidthCalculator::width($prefix);
$taskWidth = WidthCalculator::width($task);
$statusWidth = WidthCalculator::width($status);
$totalWidth = $prefixWidth + $taskWidth + $statusWidth;
$paddingNeeded = max(0, $termWidth - $totalWidth);

echo "Prefix: '{$prefix}' = {$prefixWidth} chars\n";
echo "Task: '{$task}' = {$taskWidth} chars\n";
echo "Status: '{$status}' = {$statusWidth} chars\n";
echo "Total: {$totalWidth} chars\n";
echo "Terminal width: {$termWidth}\n";
echo "Padding needed: {$paddingNeeded}\n";

echo "\nLine render test:\n";
echo str_repeat('-', $termWidth) . "\n";
echo $prefix . $task . $status . str_repeat(' ', $paddingNeeded) . "\n";
echo str_repeat('-', $termWidth) . "\n";
