# Swarm 💮

AI-powered coding agent written in PHP that helps with development tasks through natural language.

## Features

- **Intelligent Request Classification**: Automatically determines whether to show code examples, implement features, or explain concepts
- **Conversation Memory**: Maintains context across multiple interactions for coherent assistance
- **Smart Tool Usage**: Only uses file operations when necessary, returns markdown snippets for demonstrations
- **Structured Task Planning**: Uses OpenAI's structured outputs for reliable task execution
- **Interactive Terminal UI**: Beautiful TUI with real-time updates and progress tracking
- **Extensible Tool System**: Modular architecture for adding new capabilities

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
# Run the agent
./bin/swarm

# Or use composer
composer swarm
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
│   ├── SwarmCLI.php      # Main CLI entry point
│   ├── TUIRenderer.php   # Terminal UI rendering
│   ├── InputHandler.php  # Readline input handling
│   └── AsyncProcessor.php # Async task processing
├── Core/                  # Core functionality
│   ├── ToolRouter.php    # Routes tool calls
│   ├── ToolRegistry.php  # Tool registration
│   └── ToolResponse.php  # Tool response wrapper
├── Task/                  # Task management
│   ├── TaskManager.php   # Task queue management
│   ├── Task.php          # Task entity
│   └── TaskStatus.php    # Task status enum
├── Tools/                 # Tool implementations
│   ├── ReadFile.php      # File reading
│   ├── WriteFile.php     # File writing
│   ├── FindFiles.php     # File searching
│   ├── Search.php        # Content searching
│   └── Terminal.php      # Shell commands
└── Contracts/            # Interfaces
    └── Tool.php          # Tool interface
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
   - Maintains last 50 messages in memory
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

- `OPENAI_API_KEY`: Your OpenAI API key (required)
- `OPENAI_MODEL`: Model to use (default: gpt-4)
- `OPENAI_TEMPERATURE`: Temperature for responses (default: 0.7)
- `LOG_ENABLED`: Enable file logging (true/false)
- `LOG_PATH`: Log directory (default: storage/logs)
- `LOG_LEVEL`: Log level (debug, info, warning, error)

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

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open source and available under the [MIT License](LICENSE).