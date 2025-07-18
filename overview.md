# Swarm Architecture Overview

## Core Components

### Agent System

#### CodingAgent
The main AI agent that processes user requests. It:
- Classifies requests (implementation, demonstration, explanation, conversation)
- Extracts tasks from user input
- Plans and executes tasks using available tools
- Manages conversation history
- Generates summaries of completed work

#### ToolExecutor (formerly ToolRouter)
Manages tool registration and execution:
- Registers tools and maintains tool instances
- Routes tool calls to appropriate handlers
- Provides OpenAI function schemas for all tools
- Logs tool execution for debugging
- Handles tool execution errors gracefully

#### Toolchain (formerly ToolRegistry)
Static registry that registers all available tools:
- Central place to register tool instances
- Provides a simple interface to add new tools
- Initializes tools with dependencies

### Tools

#### ReadFile
Reads contents of files from the filesystem:
- Takes a file path as input
- Returns file content, size, and line count
- Handles file not found errors

#### WriteFile  
Creates or overwrites files:
- Takes file path and content
- Creates directories if needed
- Returns bytes written

#### Terminal
Executes shell commands:
- Runs bash commands with timeout support
- Captures stdout, stderr, and return codes
- Handles command execution errors

#### Grep (merged FindFiles + Search)
Searches for content in files:
- Find files by pattern
- Search content within files
- Support for case-sensitive/insensitive search
- Recursive directory searching

### CLI & UI

#### SwarmCLI
Main command-line interface:
- Handles user input and commands
- Manages the main interaction loop
- Integrates with TUI for rich display
- Supports both sync and async processing

#### TUIRenderer
Terminal User Interface rendering:
- Creates bordered UI with ANSI escape codes
- Shows task progress and status
- Displays conversation history
- Handles terminal resizing
- Provides animations and progress indicators

#### InputHandler
Protected input handling using PsySH:
- Prevents accidental prompt deletion
- Provides command history
- Handles special key combinations safely

### Task Management

#### TaskManager
Manages multi-step task execution:
- Tracks task status (pending, planned, executing, completed)
- Maintains task queue
- Associates plans and steps with tasks
- Provides task progress information

#### Task
Individual task representation:
- Unique ID and description
- Status tracking
- Plan and steps storage
- Timestamps for tracking

### Support Classes

#### PromptTemplates
Dynamic prompt generation for different scenarios:
- Classification prompts
- Task extraction prompts
- Execution prompts with available tools
- Conversation and explanation prompts

#### AgentResponse
Standardized response format from the agent:
- Success/error status
- Response message
- Consistent interface for UI

#### ToolResponse
Standardized response format from tools:
- Success/error status
- Response data
- Error messages

#### ExceptionHandler
Global exception handling:
- Formats errors for display
- Logs exceptions
- Provides user-friendly error messages

### Async Processing

#### StreamingBackgroundProcessor
Manages background process execution:
- Launches PHP processes for async work
- Uses pipes for real-time communication
- Monitors process status
- Handles timeouts gracefully

#### StreamingAsyncProcessor
Background worker for async requests:
- Processes requests in separate process
- Streams progress updates via stdout
- Handles signals for graceful shutdown
- Sends heartbeat messages

## Data Flow

1. User enters request in SwarmCLI
2. Request is sent to CodingAgent
3. Agent classifies the request type
4. For implementation tasks:
   - Extract specific tasks
   - Plan each task
   - Execute using tools via ToolExecutor
   - Generate summary
5. Results displayed in TUI
6. Conversation history maintained

## Adding New Tools

1. Create a new class extending `Tool` abstract class
2. Implement required methods:
   - `name()`: Unique tool identifier
   - `description()`: What the tool does
   - `parameters()`: OpenAI function parameters
   - `required()`: Required parameters
   - `execute()`: Tool logic
3. Register in Toolchain::registerAll()
4. Tool automatically available to agent

## Configuration

Environment variables:
- `OPENAI_API_KEY`: Required for AI functionality
- `OPENAI_MODEL`: Model to use (default: gpt-4)
- `OPENAI_TEMPERATURE`: Creativity level (default: 0.7)
- `LOG_ENABLED`: Enable file logging
- `LOG_PATH`: Log file location
- `SWARM_REQUEST_TIMEOUT`: Max request time in seconds
- `SWARM_TIMEOUT_RETRY_ENABLED`: Allow retry on timeout
- `SWARM_HEARTBEAT_INTERVAL`: Heartbeat frequency for long operations