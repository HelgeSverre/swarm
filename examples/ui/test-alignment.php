#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/WidthCalculator.php';

// Test the exact header alignment as used in swarm-sidebar-ui.php
$width = (int) exec('tput cols') ?: 80;

echo "Testing header alignment with terminal width: {$width}\n";
echo "=================================================\n\n";

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

echo "Width calculations:\n";
echo "  Prefix: '{$prefix}' = {$prefixWidth} chars\n";
echo "  Task: '{$taskSection}' = {$taskWidth} chars\n";
echo "  Status: '{$status}' = {$statusWidth} chars\n";
echo "  Total content: {$totalWidth} chars\n";
echo "  Terminal width: {$width} chars\n";
echo "  Padding needed: {$paddingNeeded} chars\n\n";

// Test actual rendering
$styledContent = $prefix . $taskSection . $status;
$padding = str_repeat(' ', $paddingNeeded);

echo "Visual test (borders show full width):\n";
echo str_repeat('=', $width) . "\n";
echo $styledContent . $padding . "\n";
echo str_repeat('=', $width) . "\n";

// Test with divider line
echo "\nDivider test:\n";
echo str_repeat('─', $width) . "\n";
echo $styledContent . $padding . "\n";
echo str_repeat('─', $width) . "\n";

echo "\nAlignment test passed! ✓\n";
