# 💮 Swarm 

AI-powered coding agent written in PHP that helps with development tasks through natural language.

## Features

- **Intelligent Request Classification**: Automatically determines whether to show code examples, implement features, or explain concepts
- **Conversation Memory**: Maintains context across multiple interactions for coherent assistance
- **Smart Tool Usage**: Only uses file operations when necessary, returns markdown snippets for demonstrations
- **Structured Task Planning**: Uses OpenAI's structured outputs for reliable task execution
- **Interactive Terminal UI**: Beautiful TUI with real-time updates, activity tracking, and scrollable panes
- **Extensible Tool System**: Modular architecture with toolkit support for adding new capabilities
- **Type-safe Task Management**: Immutable Task objects with proper state transitions
- **Activity Tracking System**: Visual activity feed showing conversations, tool calls, and notifications
- **Web Integration**: Fetch web content and search the web with Tavily integration
- **Browser Automation**: Playwright integration for browser-based tasks
- **Streaming Processing**: Async and background processing for long-running operations
- **Multiple AI Models**: Support for OpenAI and Mistral models
- **Centralized Prompt Templates**: Consistent prompts across all interactions
- **Progress Reporting**: Real-time updates during task execution

## Requirements

- PHP 8.3+
- Composer
- OpenAI API key

## Installation

```bash
# Clone the repository
git clone https://github.com/helgesverre/swarm.git
cd swarm

# Install dependencies
composer install

# Set up your OpenAI API key
# The .env file will be created automatically from .env.example
# Edit .env and add your API key
```

## Usage

```bash
# Primary method - run directly with PHP
php cli.php

# Alternative - use composer script
composer swarm

# Or use the binary
./bin/swarm
```

Type your request in natural language and Swarm will:

1. **Classify your request** to determine the appropriate response type
2. **Maintain conversation context** across multiple interactions
3. **Execute tasks intelligently** using tools only when necessary
4. **Show results** in a beautiful terminal interface

### Example Interactions

- **"Show me an example of a singleton pattern"** → Returns markdown code snippet
- **"Create a UserController.php file"** → Creates actual file using tools
- **"Explain dependency injection"** → Provides educational explanation
- **"What files are in the src directory?"** → Uses tools to explore filesystem

Type `exit` or `quit` to stop.

## Architecture

### Core Components

```
src/
├── Agent/                 # AI agent logic
│   ├── CodingAgent.php   # Main agent with request classification
│   └── AgentResponse.php # Response wrapper
├── CLI/                   # Command-line interface
│   ├── Swarm.php         # Main CLI entry point
│   ├── UI.php            # Terminal UI rendering
│   ├── InputHandler.php  # Readline input handling
│   ├── Activity/         # Activity tracking system
│   │   ├── ActivityEntry.php      # Base activity class
│   │   ├── ConversationEntry.php  # Conversation messages
│   │   ├── NotificationEntry.php  # System notifications
│   │   └── ToolCallEntry.php      # Tool execution tracking
│   ├── Layout/           # Layout management
│   │   ├── PaneLayout.php         # Pane layout manager
│   │   └── ScrollablePane.php     # Scrollable content panes
│   ├── Terminal/         # Terminal utilities
│   │   └── Ansi.php      # ANSI escape codes
│   ├── WorkerProcess.php         # Worker process for child execution
│   └── ProcessSpawner.php        # Spawns and manages child processes
├── Core/                  # Core functionality
│   ├── ToolExecutor.php  # Executes tool calls
│   ├── AbstractToolkit.php # Base toolkit class
│   ├── ExceptionHandler.php # Global exception handling
│   └── ToolResponse.php  # Tool response wrapper
├── Enums/                 # Type-safe enumerations
│   ├── Agent/            # Agent-related enums
│   │   └── RequestType.php
│   ├── CLI/              # CLI-related enums
│   │   ├── ActivityType.php
│   │   ├── AnsiColor.php
│   │   ├── BoxCharacter.php
│   │   ├── NotificationType.php
│   │   ├── StatusIcon.php
│   │   └── ThemeColor.php
│   └── Core/             # Core enums
│       ├── LogLevel.php
│       └── ToolStatus.php
├── Exceptions/           # Custom exceptions
│   └── ToolNotFoundException.php
├── Prompts/              # Prompt management
│   └── PromptTemplates.php # Centralized prompt templates
├── Task/                 # Task management
│   ├── TaskManager.php  # Task queue management
│   ├── Task.php         # Immutable task value object
│   └── TaskStatus.php   # Task status enum
├── Tools/                # Tool implementations
│   ├── ReadFile.php     # File reading
│   ├── WriteFile.php    # File writing
│   ├── Grep.php         # Pattern searching in files
│   ├── Terminal.php     # Shell command execution
│   ├── WebFetch.php     # Web content fetching
│   ├── Playwright.php   # Browser automation
│   └── Tavily/          # Tavily integration
│       ├── TavilySearchTool.php  # Web search
│       ├── TavilyExtractTool.php # Content extraction
│       └── TavilyToolkit.php     # Tavily toolkit
└── Contracts/           # Interfaces
    ├── Tool.php         # Tool interface
    └── Toolkit.php      # Toolkit interface
```

### How It Works

1. **Request Processing Flow**:
   ```
   User Input → Request Classification → Route to Handler → Execute → Response
   ```

2. **Request Classification**:
   - Uses OpenAI's structured outputs to classify requests
   - Categories: `demonstration`, `implementation`, `explanation`, `query`, `conversation`
   - Determines if tools are needed based on intent

3. **Conversation Memory**:
   - Maintains last 20 messages in memory
   - Includes context in all OpenAI API calls
   - Filters out tool/error messages for cleaner context

4. **Smart Tool Usage**:
   - Only uses tools when `requires_tools` is true
   - Different handlers for different request types
   - Fallback mechanisms for robustness

5. **Task Execution**:
   - Structured planning with specific steps
   - Tool execution with proper error handling
   - Progress tracking and reporting

## Development

```bash
# Run tests
composer test

# Code formatting
composer format

# Static analysis
composer check

# Run test agent
php test_agent.php
```

## Configuration

Environment variables (in `.env`):

### OpenAI Settings
- `OPENAI_API_KEY`: Your OpenAI API key (required)
- `OPENAI_MODEL`: Model to use (default: gpt-4.1-nano)
- `OPENAI_TEMPERATURE`: Temperature for responses (default: 0.7)

### Mistral Settings
- `MISTRAL_API_KEY`: Mistral API key for alternative AI model (optional)
- `MISTRAL_MODEL`: Mistral model to use (default: devstral-medium-latest)

### API Integrations
- `TAVILY_API_KEY`: API key for Tavily web search/extract tools (optional)

### Application Settings
- `APP_ENV`: Application environment (local, production)
- `DEBUG`: Enable debug mode (true/false)

### Logging Settings  
- `LOG_ENABLED`: Enable file logging (true/false)
- `LOG_PATH`: Log directory (default: logs)
- `LOG_LEVEL`: Log level (debug, info, warning, error)

### Timeout Settings
- `SWARM_REQUEST_TIMEOUT`: Per-request timeout in seconds (default: 600 = 10 minutes)
- `SWARM_SUBPROCESS_TIMEOUT`: Subprocess timeout in seconds (default: 300 = 5 minutes)
- `SWARM_HEARTBEAT_INTERVAL`: Heartbeat interval in seconds (default: 30)
- `SWARM_TIMEOUT_RETRY_ENABLED`: Enable retry suggestion on timeout (default: true)

**Note on Running the App**: 
- When using `composer swarm`, Composer enforces its own 300-second timeout
- For long-running operations, use `./bin/swarm` directly to avoid Composer timeouts
- The main CLI process runs with unlimited execution time
- Individual requests and subprocesses have configurable timeouts

## Advanced Features

### Request Classification
The agent uses structured outputs to classify requests with confidence scores:
```json
{
  "request_type": "demonstration",
  "requires_tools": false,
  "confidence": 0.95,
  "reasoning": "User asking for code example"
}
```

### Structured Task Planning
Tasks are planned with detailed steps and complexity estimates:
```json
{
  "plan_summary": "Create a REST API endpoint",
  "steps": [
    {
      "description": "Create controller file",
      "tool_needed": "write_file",
      "expected_outcome": "Controller class created"
    }
  ],
  "estimated_complexity": "moderate"
}
```

### Tool System
Tools are registered with schemas for OpenAI function calling:
- Abstract class-based design (extends `Tool`)
- Automatic schema generation via `toOpenAISchema()`
- Type validation and error handling
- Easy to add new tools by implementing the interface

Available tools:
- **ReadFile**: Read contents of files with line numbers
- **WriteFile**: Create or overwrite files with content
- **Grep**: Search for patterns in files using regular expressions
- **Terminal**: Execute shell commands with timeout control
- **WebFetch**: Fetch and parse web content
- **Playwright**: Automate browser interactions and testing
- **TavilySearch**: Search the web for information
- **TavilyExtract**: Extract structured data from web pages

### Prompt Management
All prompts are centralized in `PromptTemplates` class:
- System prompts for different interaction modes
- Task-related prompts (extraction, planning, execution)
- Code assistance prompts (explain, refactor, debug, review, generate, document, test)
- Dynamic tool list integration

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open source and available under the [MIT License](LICENSE.md).
