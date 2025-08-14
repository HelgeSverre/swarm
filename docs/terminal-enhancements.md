# Terminal UI Enhancements

This document outlines the minor terminal UI improvements added to the `Ansi.php` class without requiring major refactoring.

## 🆕 New ANSI Escape Sequences

### Cursor Control
- `SAVE_CURSOR` / `saveCursor()` - Save current cursor position  
- `RESTORE_CURSOR` / `restoreCursor()` - Restore saved cursor position
- `CLEAR_LINE` / `clearLine()` - Clear entire line
- `CLEAR_TO_EOL` / `clearToEndOfLine()` - Clear to end of line

### Terminal Control
- `BELL` / `bell()` - Terminal bell/alert
- `ALT_SCREEN_ENABLE` / `enterAltScreen()` - Enter alternative screen buffer
- `ALT_SCREEN_DISABLE` / `exitAltScreen()` - Exit alternative screen buffer

### Mouse Support
- `MOUSE_ENABLE` / `enableMouse()` - Enable click events
- `MOUSE_DISABLE` / `disableMouse()` - Disable mouse tracking
- `MOUSE_ENABLE_ALL` / `enableAllMouseEvents()` - Enable all mouse events

### Window Control
- `setTitle(string $title)` - Set terminal window title

## 🎨 Enhanced Colors

### 256-Color Support
```php
Ansi::color256(196);        // Red (color 196)
Ansi::bgColor256(21);       // Blue background
```

### True RGB Colors
```php
Ansi::rgb(255, 100, 50);    // RGB foreground
Ansi::bgRgb(0, 0, 255);     // RGB background
```

### Terminal Capability Detection
```php
Ansi::supports256Colors();   // Check 256-color support
Ansi::supportsUnicode();     // Check Unicode support  
Ansi::supportsTrueColor();   // Check RGB support
```

## 🔗 Hyperlinks (OSC 8)

Make text clickable in supported terminals:

```php
Ansi::hyperlink('https://example.com', 'Click here');
Ansi::clickableFile('/path/to/file', 'Open file');
```

## 📦 Enhanced Box Drawing

### New Box Characters
- Single line: `BOX_TL`, `BOX_TR`, `BOX_BL`, `BOX_BR`, etc.
- Double line: `BOX_TL2`, `BOX_TR2`, `BOX_BL2`, `BOX_BR2`, etc.  
- Rounded corners: `BOX_TL_ROUND`, `BOX_TR_ROUND`, etc.
- Tree view: `TREE_BRANCH`, `TREE_LAST`, `TREE_PIPE`

### Enhanced Box Methods
```php
// Simple box with title
Ansi::box("Content", "Title", 50, true);

// Fancy box with style options
Ansi::fancyBox("Content", "Title", 'rounded', 60, Ansi::CYAN);
```

## 📊 Better Progress Indicators

### Smooth Progress Bars
Uses Unicode block characters for sub-character precision:

```php
Ansi::smoothProgressBar(65, 100);  // Uses ▏▎▍▌▋▊▉█
```

### Animated Spinners
```php
Ansi::spinner(0, 'dots');    // ⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏
Ansi::spinner(1, 'circle');  // ◐◓◑◒
Ansi::spinner(2, 'arrow');   // ←↖↑↗→↘↓↙
```

### Auto-detecting Progress Bar
The standard `progressBar()` method now automatically uses smooth bars if Unicode is supported.

## 🏷️ Status Badges

Color-coded status indicators:

```php
Ansi::badge('SUCCESS', 'success');  // Green badge
Ansi::badge('ERROR', 'error');      // Red badge  
Ansi::badge('WARNING', 'warning');  // Yellow badge
Ansi::badge('INFO', 'info');        // Blue badge
Ansi::badge('PENDING', 'pending');  // Gray badge
```

## ✨ Semantic Color Methods

Convenient shorthand methods:

```php
Ansi::success('All good!');    // Green text
Ansi::error('Something broke'); // Red text  
Ansi::warning('Be careful');    // Yellow text
Ansi::info('FYI');             // Cyan text
Ansi::muted('Less important'); // Dim text
```

## 🕒 Time Formatting

### Relative Time
```php
Ansi::relativeTime(time() - 300);  // "5m ago"
Ansi::relativeTime(time() - 3600); // "1h ago"
```

### Activity Line with Relative Time
```php
Ansi::activityLineRelative('info', 'Event happened', $timestamp);
```

## 🌳 Tree Views

Format hierarchical data:

```php
Ansi::treeItem('Root');
Ansi::treeItem('Child 1', 1);
Ansi::treeItem('Child 2', 1); 
Ansi::treeItem('Last child', 2, true);
```

Output:
```
Root
├── Child 1  
├── Child 2
└── Last child
```

## ✂️ Enhanced Text Handling

### Better Truncation
Uses proper ellipsis character (`…`) instead of three dots:

```php
Ansi::truncateNice($longText, 50);  // Uses …
Ansi::truncate($text, 50);          // Updated to use … too
```

## 🔧 Terminal Reset

### Reset Methods
```php
Ansi::reset();       // Full terminal reset (\033c)
Ansi::softReset();   // Soft reset (\033[!p)
```

## 📋 Usage Examples

Run the demo script to see all features in action:

```bash
php examples/terminal-demo.php
```

## 🔄 Backward Compatibility

All existing methods continue to work unchanged. New features are additive only:

- `progressBar()` now auto-detects Unicode support for smooth bars
- `truncate()` now uses proper ellipsis character
- `thoughtBlock()` uses proper ellipsis
- Enhanced `activityLine()` supports relative time via new `activityLineRelative()`

## 🎯 Integration

The enhanced `Ansi` class can be used immediately in your terminal UI code:

```php
use HelgeSverre\Swarm\CLI\Terminal\Ansi;

// Set terminal title
Ansi::setTitle('My App');

// Show capabilities 
if (Ansi::supportsTrueColor()) {
    echo Ansi::rgb(255, 100, 50) . 'RGB Colors!' . Ansi::RESET;
}

// Progress with spinner
echo Ansi::smoothProgressBar(75, 100) . ' ' . Ansi::spinner(3, 'dots');

// Status badge
echo 'Status: ' . Ansi::badge('RUNNING', 'info');

// Clickable file
echo 'Edit: ' . Ansi::clickableFile($filePath);
```

These enhancements provide a much richer terminal experience while maintaining full backward compatibility.