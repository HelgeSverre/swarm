# TUI Framework Development Plan

## Project Overview

We are developing a terminal UI framework to solve rendering issues in the existing Swarm AI assistant's `src/CLI/Terminal/FullTerminalUI.php`. The current implementation has problems with:

1. **Text overlap** - Wrapped lines bleeding into previous messages
2. **Inconsistent positioning** - Row tracking confusion between methods
3. **Mixed concerns** - Data formatting, wrapping, and cursor positioning tangled together
4. **No pre-calculation** - Don't know line counts until after rendering

## Goal

Create a modern, widget-based terminal UI framework inspired by Flutter/React patterns, then use it to create a **MOCK version** of the Swarm terminal UI to iron out issues before refactoring the real implementation.

## Current Implementation Status

### ✅ Completed Components

#### Core Framework (`/Core/`)

- **Widget.php** - Abstract base widget class with lifecycle management
- **BuildContext.php** - Context for widget building with terminal info
- **Constraints.php** - Layout constraint system (Size, Rect, Constraints)
- **Canvas.php** - Drawing abstraction with ANSI support and clipping
- **RenderPipeline.php** - Three-phase rendering (build → layout → paint)

#### Focus Management (`/Focus/`)

- **FocusNode.php** - Individual focus nodes with event dispatching
- **FocusManager.php** - Central focus management with traversal policies
- **FocusScope.php** - Scoped focus management widget

#### Layout Widgets (`/Layout/`)

- **Flex.php** - Flexible box layout (base for Row/Column)
- **Row.php** / **Column.php** - Horizontal/vertical flex layouts
- **Container.php** - Container with padding, borders, decorations
- **Stack.php** - Overlapping widgets with z-ordering
- **ScrollView.php** - Scrollable container with keyboard navigation

#### Basic Widgets (`/Widgets/`)

- **Text.php** - Text display with styling and alignment
- **Box.php** - Border widget with multiple styles
- **TextInput.php** - Interactive text input with cursor/selection
- **ListView.php** - Scrollable list with selection and navigation

#### Application Layer (`/App/`)

- **MockData.php** - Sample data generators for activities/tasks
- **ActivityLog.php** - Activity display widget
- **TaskList.php** - Task management widget
- **SwarmApp.php** - Main application layout

### 🎯 Next Steps: Mock Swarm Terminal UI

#### Phase 1: Exact Feature Mapping

Create a mock version that replicates the exact functionality of `FullTerminalUI.php`:

1. **Header Bar** - Status display with branding (`💮 swarm | status`)
2. **Main Activity Area** - Scrollable activity log with proper wrapping
3. **Sidebar** - Task queue and context information
4. **Input Area** - Command input with focus management
5. **Focus Navigation** - Tab between areas, arrow navigation

#### Phase 2: Data Structure Mapping

Map existing Swarm data structures to our framework:

```php
// Current Swarm data → Framework widgets
$this->history → ActivityLog widget
$this->tasks → TaskList widget
$this->context → ContextPanel widget
$this->input → TextInput widget
$this->status → StatusBar widget
```

#### Phase 3: Integration Points

Identify how to integrate with existing Swarm systems:

- **EventBus** - Map to widget event handling
- **ProcessManager** - Connect to activity updates
- **CodingAgent** - Feed data to activity log
- **UI State** - Map to widget state management

## File Structure

```
examples/tui-lib/
├── plan.md                    # This file
├── Core/                      # ✅ Complete
│   ├── Widget.php
│   ├── BuildContext.php
│   ├── Constraints.php
│   ├── Canvas.php
│   └── RenderPipeline.php
├── Focus/                     # ✅ Complete
│   ├── FocusManager.php
│   ├── FocusNode.php
│   └── FocusScope.php
├── Layout/                    # ✅ Complete
│   ├── Flex.php
│   ├── Container.php
│   ├── Stack.php
│   └── ScrollView.php
├── Widgets/                   # ✅ Complete
│   ├── Text.php
│   ├── Box.php
│   ├── TextInput.php
│   └── ListView.php
├── App/                       # ✅ Complete (basic)
│   ├── MockData.php
│   ├── ActivityLog.php
│   ├── TaskList.php
│   └── SwarmApp.php
├── SwarmMock/                 # 🎯 TODO: Mock Swarm UI
│   ├── SwarmMockApp.php       # Main mock application
│   ├── SwarmActivityLog.php   # Exact replica of history rendering
│   ├── SwarmSidebar.php       # Task queue + context
│   ├── SwarmHeader.php        # Status bar
│   ├── SwarmInput.php         # Command input
│   └── SwarmMockData.php      # Mock Swarm-specific data
├── demo.php                   # ✅ Complete (generic demo)
└── swarm-mock.php             # 🎯 TODO: Mock Swarm app runner
```

## Implementation Strategy

### Step 1: Create SwarmMock Components

Create exact replicas of current FullTerminalUI functionality:

- **SwarmHeader** - Replicate `renderStatusBar()`
- **SwarmActivityLog** - Replicate history rendering with proper wrapping
- **SwarmSidebar** - Replicate `renderSidebar()` with tasks/context
- **SwarmInput** - Replicate input handling
- **SwarmMockApp** - Combine everything with exact layout

### Step 2: Test Against Real Issues

Use the mock to verify we solve the original problems:

- ✅ Text wrapping without overflow
- ✅ Proper row calculation
- ✅ No text bleeding/overlap
- ✅ Clean focus management
- ✅ Responsive layout

### Step 3: Performance Testing

Ensure the framework performs well:

- Memory usage compared to direct rendering
- Frame rate with large activity logs
- Startup time and responsiveness

### Step 4: Integration Planning

Plan how to integrate back into the main Swarm app:

- Gradual migration strategy
- Backwards compatibility
- Event system integration
- Testing approach

## Key Benefits of This Approach

1. **Risk Mitigation** - Test thoroughly before touching production code
2. **Issue Isolation** - Iron out framework bugs in isolation
3. **Feature Parity** - Ensure we don't lose any existing functionality
4. **Performance Validation** - Confirm the abstraction doesn't hurt performance
5. **Team Review** - Easy to review and iterate on design

## Success Criteria

The mock implementation should:

1. ✅ Render identical UI to current FullTerminalUI
2. ✅ Handle all the same keyboard inputs
3. ✅ Solve the text wrapping/overlap issues
4. ✅ Maintain performance characteristics
5. ✅ Provide cleaner, more maintainable code
6. ✅ Enable easy future enhancements

## Next Actions

1. **Create SwarmMock components** - Start with SwarmMockApp layout
2. **Implement exact feature parity** - Match current behavior exactly
3. **Test against original issues** - Verify problems are solved
4. **Performance benchmark** - Compare with original implementation
5. **Document integration plan** - Plan migration strategy

This approach ensures we validate the framework thoroughly before any production changes.
