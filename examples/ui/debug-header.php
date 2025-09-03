#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/WidthCalculator.php';

// Exact debug of header rendering from swarm-sidebar-ui.php
$width = 80;

// Build header content components (same as in swarm-sidebar-ui.php)
$prefix = ' 💮 swarm │ ';
$taskText = WidthCalculator::truncate('Refactor database layer', 30);
$taskSection = '● ' . $taskText . ' │ ';
$status = 'working (2/4)';

// Calculate padding for full width using visual content only
$prefixWidth = WidthCalculator::width($prefix);
$taskWidth = WidthCalculator::width($taskSection);
$statusWidth = WidthCalculator::width($status);
$totalWidth = $prefixWidth + $taskWidth + $statusWidth;
$paddingNeeded = max(0, $width - $totalWidth);
$padding = str_repeat(' ', $paddingNeeded);

echo "Debug header calculation:\n";
echo "========================\n";
echo "prefix: '{$prefix}' -> width: {$prefixWidth}\n";
echo "taskSection: '{$taskSection}' -> width: {$taskWidth}\n";
echo "status: '{$status}' -> width: {$statusWidth}\n";
echo "total content width: {$totalWidth}\n";
echo "padding needed: {$paddingNeeded}\n";
echo 'padding string length: ' . mb_strlen($padding) . "\n\n";

// Build styled content with colors
$styledContent = $prefix . "\033[32m● \033[37m" . $taskText . "\033[2m │ \033[33m" . $status . "\033[0m";

echo "Styled content debug:\n";
echo 'Clean width: ' . WidthCalculator::width($styledContent) . "\n";
echo 'MB length: ' . mb_strlen($styledContent) . "\n";
echo "Raw content: '{$styledContent}'\n\n";

// Test full line
$fullLine = "\033[48;5;236m" . $styledContent . $padding . "\033[0m";
echo "Full line test:\n";
echo "Raw: '{$fullLine}'\n";
echo 'Byte length: ' . mb_strlen($fullLine) . "\n";
echo "Visual test:\n";
echo str_repeat('=', $width) . "\n";
echo $fullLine . "\n";
echo str_repeat('=', $width) . "\n";
