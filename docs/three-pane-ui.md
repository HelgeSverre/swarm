# Three-Pane UI Documentation

## Overview

The three-pane UI provides an enhanced terminal interface for Swarm with:
- **Left Sidebar**: File tree navigation
- **Main Area**: Scrollable chat history with full conversation
- **Right Sidebar**: Context notes and metadata

## Enabling Three-Pane UI

Set the environment variable in your `.env` file:
```bash
SWARM_UI_MODE=three-pane
```

Supported values:
- `classic` (default) - Traditional single-pane UI
- `three-pane` or `3pane` or `threepane` - New three-pane layout

## Features

### Scrollable Chat History
- Full conversation history (not limited to recent messages)
- Smooth scrolling with keyboard navigation
- Visual scroll indicators showing position
- Auto-scroll to bottom on new messages
- Line/percentage display in context pane

### Keyboard Shortcuts

| Key | Action |
|-----|--------|
| Tab | Switch focus between panes |
| ↑/↓ | Scroll chat history (when main pane focused) |
| Page Up/Down | Page scroll |
| Home/End | Jump to top/bottom |
| Option+B (macOS) / Ctrl+B | Toggle sidebars |

### Layout

```
┌─────────────┬───────────────────────────┬──────────────┐
│ 📁 Files    ┃  💮 Swarm Assistant       ┃ 📝 Context   │
│ ─────────   ┃  ↑ More above (5 lines) ↑ ┃ ─────────    │
│ ▼ src/      ┃                           ┃ Current Task │
│   ▼ CLI/    ┃  You: Create three-pane   ┃ Model: GPT-4 │  
│     · UI.php┃       UI layout...        ┃ Session: 2h  │
│   ▶ Agent/  ┃                           ┃              │
│ · README.md ┃  Assistant: I'll create   ┃ ─────────    │
│             ┃            a three-pane   ┃ Scroll: 85%  │
│             ┃            layout with... ┃ Line 45/120  │
└─────────────┴───────────────────────────┴──────────────┘
 Tab Switch Pane  ⌥B Toggle Sidebars  ↑↓ Scroll  ⌥Q Quit
```

### File Tree (Left Sidebar)
- Shows current project structure
- Expandable/collapsible directories
- Highlights active files
- Navigate with arrow keys when focused

### Context Pane (Right Sidebar)
- Current task information
- Session metadata
- Scroll position indicators
- Notes and annotations

## Implementation Details

The three-pane UI is implemented in `src/CLI/UIThreePanes.php` and extends the base `UI` class to maintain compatibility while adding new features:

- **PaneLayout**: Manages layout calculations and pane dimensions
- **ScrollablePane**: Handles viewport and scrolling logic
- **Heavy vertical dividers**: Uses ┃ character for better visual separation
- **OS detection**: Adapts keyboard shortcuts for macOS vs other systems

## Testing

Run the demo script to see the three-pane UI in action:
```bash
php test-three-pane-ui.php
```

## Future Enhancements

- Resizable panes with mouse support
- File tree interactions (open/edit files)
- Search within chat history
- Syntax highlighting for code blocks
- Customizable color themes
- Persistent layout preferences