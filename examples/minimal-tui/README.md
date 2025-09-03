# Minimal TUI Library

A lightweight, practical Terminal User Interface library for PHP that provides complex TUI functionality without the bloated framework overhead.

## Why Minimal TUI?

The existing TUI frameworks often suffer from:
- **Over-engineering**: 2000+ lines to display text
- **Memory waste**: Object-per-character allocation disasters  
- **Complexity explosion**: 20+ classes to understand for basic functionality

**Minimal TUI solves this with:**
- **~800 total lines** vs 1,567+ in alternatives
- **String-based rendering** - no memory allocation disasters
- **Component composition** - build complex UIs from simple parts
- **Clean APIs** - easy to understand and extend

## Features

- ✅ **Input fields** with cursor, scrolling, and keyboard shortcuts
- ✅ **Scrollable lists** with selection and navigation
- ✅ **Panels** with borders and titles
- ✅ **Status bars** with multiple sections
- ✅ **Layout management** with flexible positioning
- ✅ **Focus management** with Tab cycling
- ✅ **Terminal resizing** support
- ✅ **Non-blocking input** handling
- ✅ **ANSI color** and formatting support

## Quick Start

### Basic Usage

```php
<?php
use MinimalTui\Core\{TuiApp, Layout};
use MinimalTui\Components\{Panel, InputField, ListWidget};

class MyApp extends TuiApp
{
    public function __construct()
    {
        parent::__construct();
        
        // Create components
        $list = new ListWidget(['Item 1', 'Item 2', 'Item 3']);
        $input = new InputField('Type something...');
        
        // Setup layout
        $layout = Layout::vsplit($this->width, $this->height, 50);
        $this->setLayout($layout);
        
        // Add components
        $this->addComponent('list', new Panel('Items', $list), 'left');
        $this->addComponent('input', new Panel('Input', $input), 'right');
    }
    
    protected function onCommand(string $command): void
    {
        // Handle user input
        echo "User entered: $command\\n";
    }
}

$app = new MyApp();
$app->run();
```

### Running the Examples

```bash
# Simple todo app
php examples/minimal-tui/Examples/simple-demo.php

# Complex chat interface (replicates FullTerminalUI functionality)  
php examples/minimal-tui/Examples/chat-demo.php
```

## Components

### InputField

Text input with cursor, scrolling, and editing support:

```php
$input = new InputField('Enter text...', 100); // max length 100
$input->setPassword(true); // Hide input
$input->setValue('default text');

// Handle input in your app
protected function onCommand(string $text): void {
    // User pressed Enter
}
```

**Keyboard shortcuts:**
- `Ctrl+A` - Move to beginning
- `Ctrl+E` - Move to end  
- `Ctrl+K` - Delete to end
- `Ctrl+U` - Delete to beginning
- `Left/Right` - Move cursor
- `Backspace` - Delete character

### List

Scrollable list with selection:

```php
$list = new ListWidget($items, true); // numbered list
$list->setEmptyMessage('Nothing here...');

// Navigation
// Up/Down or j/k - Navigate
// Enter/Space - Select item
// 1-9 - Quick select by number
```

### Panel

Container with optional border and title:

```php
$panel = new Panel('My Title', $content, true); // with border
$panel->setContent($someComponent);
```

### StatusBar

Multi-section status display:

```php
$status = StatusBar::create('App Name', 'Ready');
$status->setSection('users', '5 online', Terminal::GREEN);
$status->progress(3, 10); // Show progress
```

### Layout

Flexible layout management:

```php
// Grid layout with sidebar
$layout = Layout::grid($width, $height, [
    'sidebar_width' => 30,
    'status_height' => 1
]);

// Subdivide areas
$layout->subdivide('main', ['bottom_height' => 3]);

// Manual positioning
$layout->area('custom', $x, $y, $width, $height);
```

## Architecture

### Core Classes

- **`Terminal`** - ANSI utilities, input handling, terminal management
- **`Layout`** - Simple layout system for positioning components  
- **`TuiApp`** - Main application framework with event loop

### Component System

All components implement these methods:
- `render(): string` - Return rendered content
- `setSize(int $width, int $height): self` - Set component dimensions
- `setFocused(bool $focused): self` - Handle focus state
- `handleInput(string $key): mixed` - Process keyboard input

### Event Flow

```
Terminal Input → TuiApp → Focused Component → onCommand() → Re-render
```

## Advanced Usage

### Custom Components

```php
class MyWidget
{
    protected bool $focused = false;
    protected int $width = 0;
    protected int $height = 0;
    
    public function setSize(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;
        return $this;
    }
    
    public function setFocused(bool $focused): self
    {
        $this->focused = $focused;
        return $this;
    }
    
    public function handleInput(string $key): mixed
    {
        // Handle keyboard input
        return null;
    }
    
    public function render(): string
    {
        return "My custom content";
    }
}
```

### Custom Applications

```php
class ChatApp extends TuiApp
{
    protected function onCommand(string $command): void
    {
        // Process user commands
        if (str_starts_with($command, '/')) {
            $this->handleSlashCommand(substr($command, 1));
        } else {
            $this->sendMessage($command);
        }
    }
    
    protected function handleResize(): void
    {
        parent::handleResize();
        // Custom resize handling
    }
}
```

## Keyboard Shortcuts

### Global
- `Alt+Q` - Quit application
- `Tab` - Cycle focus between components

### Component-specific
- **Lists**: `Up/Down`, `j/k`, `Enter`, `1-9` for quick select
- **Input**: `Ctrl+A/E/K/U`, `Left/Right`, `Backspace`

## Performance

The library is designed for efficiency:

- **String-based rendering** - No object allocation per character
- **Dirty region tracking** - Only re-render when needed
- **Minimal memory footprint** - ~800 lines total vs 1,567+ in alternatives
- **60 FPS rendering** - Smooth updates without blocking

## Comparison

| Feature | Minimal TUI | FullTerminalUI | Complex TUI Framework |
|---------|-------------|----------------|----------------------|
| **Lines of code** | ~800 | 1,567 | 2,000+ |
| **Memory usage** | String-based | Moderate | Object-per-cell |
| **Complexity** | Simple | Monolithic | Over-engineered |
| **Features** | All essentials | Complete | Everything + kitchen sink |
| **Learning curve** | Minutes | Hours | Days |

## Requirements

- PHP 8.3+
- POSIX-compatible terminal
- `stty` command available

## License

MIT License - feel free to use in any project.

## Contributing

This library prioritizes simplicity and practical functionality over feature completeness. Before adding new features, consider:

1. Does this solve a real terminal UI problem?
2. Can it be implemented simply without breaking existing patterns?
3. Does it maintain the lightweight philosophy?

The goal is a practical tool, not a comprehensive framework.