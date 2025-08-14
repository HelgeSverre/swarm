#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use HelgeSverre\Swarm\CLI\Terminal\Ansi;

// Demo script to showcase the enhanced terminal features

echo Ansi::setTitle('Swarm Terminal Features Demo');

echo Ansi::fancyBox('Terminal UI Enhancement Demo', '🎨 Swarm Terminal Features', 'rounded', 60, Ansi::CYAN);

// 1. Terminal Capabilities
echo Ansi::sectionHeader('📡 Terminal Capabilities Detection');
echo Ansi::activityLine('info', '256 Colors: ' . (Ansi::supports256Colors() ? 'YES' : 'NO'));
echo Ansi::activityLine('info', 'Unicode: ' . (Ansi::supportsUnicode() ? 'YES' : 'NO'));
echo Ansi::activityLine('info', 'True Color: ' . (Ansi::supportsTrueColor() ? 'YES' : 'NO'));
echo "\n";

// 2. Color Examples
echo Ansi::sectionHeader('🌈 Enhanced Colors');

// RGB Colors
echo 'RGB Colors: ';
for ($i = 0; $i < 5; $i++) {
    $r = 50 + $i * 40;
    $g = 100 + $i * 30;
    $b = 200 - $i * 30;
    echo Ansi::rgb($r, $g, $b) . '●' . Ansi::RESET . ' ';
}
echo "\n";

// 256 Colors
echo '256 Colors: ';
for ($i = 16; $i < 26; $i++) {
    echo Ansi::color256($i) . '█' . Ansi::RESET;
}
echo "\n\n";

// 3. Semantic Colors
echo Ansi::sectionHeader('✨ Semantic Colors');
echo Ansi::success('✓ Success message') . "\n";
echo Ansi::error('✗ Error message') . "\n";
echo Ansi::warning('⚠ Warning message') . "\n";
echo Ansi::info('ℹ Information message') . "\n";
echo Ansi::muted('(This is muted text)') . "\n\n";

// 4. Status Badges
echo Ansi::sectionHeader('🏷️ Status Badges');
echo 'Task Status: ' . Ansi::badge('SUCCESS', 'success') . ' ' .
     Ansi::badge('ERROR', 'error') . ' ' .
     Ansi::badge('WARNING', 'warning') . ' ' .
     Ansi::badge('INFO', 'info') . ' ' .
     Ansi::badge('PENDING', 'pending') . "\n\n";

// 5. Progress Bars
echo Ansi::sectionHeader('📊 Progress Indicators');
echo 'Standard Progress: ' . Ansi::progressBar(65, 100) . "\n";
echo 'Smooth Progress:   ' . Ansi::smoothProgressBar(65, 100) . "\n";

// 6. Spinners
echo Ansi::sectionHeader('⚡ Spinners & Animations');
echo 'Spinner Types: ';
for ($i = 0; $i < 4; $i++) {
    echo Ansi::spinner($i, 'dots') . ' ';
}
echo '(dots) ';
for ($i = 0; $i < 4; $i++) {
    echo Ansi::spinner($i, 'circle') . ' ';
}
echo '(circle) ';
for ($i = 0; $i < 4; $i++) {
    echo Ansi::spinner($i, 'arrow') . ' ';
}
echo "(arrow)\n\n";

// 7. Enhanced Box Drawing
echo Ansi::sectionHeader('📦 Enhanced Box Drawing');

echo Ansi::fancyBox('This is a single-line box', 'Single Line', 'single');
echo Ansi::fancyBox('This is a double-line box', 'Double Line', 'double');
echo Ansi::fancyBox('This is a rounded corner box', 'Rounded', 'rounded');

// 8. Tree Views
echo Ansi::sectionHeader('🌳 Tree Views');
echo Ansi::treeItem('Root Item');
echo Ansi::treeItem('Child Item 1', 1);
echo Ansi::treeItem('Child Item 2', 1);
echo Ansi::treeItem('Grandchild', 2, true);

// 9. Hyperlinks (if supported)
echo Ansi::sectionHeader('🔗 Hyperlinks');
echo 'Click here: ' . Ansi::hyperlink('https://github.com/helgesverre/swarm', 'Swarm on GitHub') . "\n";
echo 'File link: ' . Ansi::clickableFile(__FILE__, 'demo script') . "\n\n";

// 10. Relative Time
echo Ansi::sectionHeader('🕒 Time Formatting');
$timestamps = [time() - 30, time() - 300, time() - 3600, time() - 86400];
foreach ($timestamps as $ts) {
    echo Ansi::activityLineRelative('info', 'Sample event', $ts);
}

// 11. Enhanced Truncation
echo Ansi::sectionHeader('✂️ Text Truncation');
$longText = 'This is a very long text that will be truncated with a proper ellipsis character';
echo 'Original: ' . $longText . "\n";
echo 'Old way:  ' . mb_substr($longText, 0, 47) . "...\n";
echo 'New way:  ' . Ansi::truncateNice($longText, 50) . "\n\n";

// 12. Enhanced Features Demo
echo Ansi::fancyBox(
    "This demo showcases the enhanced terminal features:\n" .
    "• RGB and 256-color support\n" .
    "• Clickable hyperlinks (OSC 8)\n" .
    "• Smooth progress bars\n" .
    "• Multiple spinner types\n" .
    "• Enhanced box drawing\n" .
    "• Tree view formatting\n" .
    "• Relative time display\n" .
    "• Status badges\n" .
    "• Semantic color methods\n" .
    '• Enhanced truncation with proper ellipsis',
    '✨ Summary',
    'rounded',
    70,
    Ansi::BRIGHT_CYAN
);

echo Ansi::success('Demo completed! All features are now available in Ansi.php') . "\n";

// Cleanup
echo Ansi::bell(); // Terminal bell
