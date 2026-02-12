# CLAUDE.md - Project-specific instructions for Swarm

## System Architecture

### Overview
Swarm is an AI-powered coding assistant that uses OpenAI's GPT models to understand natural language requests and execute coding tasks. The system is built with a modular architecture that separates concerns between request processing, task management, tool execution, and UI rendering.

### Core Flow
```
1. User Input (CLI) 
   ↓
2. Request Classification (CodingAgent)
   ↓
3. Route to Handler (demonstration/explanation/conversation/implementation)
   ↓
4. Task Extraction & Planning (if needed)
   ↓
5. Tool Execution (via ToolRouter)
   ↓
6. Response Generation
   ↓
7. Terminal UI Update
```

### Key Architectural Decisions

1. **Request Classification First**: Before any task extraction, the system classifies the user's intent using structured outputs. This prevents unnecessary tool usage for simple queries.

2. **Conversation Memory**: All OpenAI API calls include conversation history (last 20 messages) for context awareness. Tool and error messages are filtered out to keep context clean.

3. **Structured Outputs**: Uses OpenAI's `response_format` with JSON schemas for reliable parsing of classifications and task plans.

4. **Tool Abstraction**: Tools implement a common interface and use PHP attributes for schema generation, making it easy to add new capabilities.

5. **Async-Ready**: Infrastructure for async processing is in place (AsyncProcessor) for future parallel task execution.

## Component Details

### CodingAgent (src/Agent/CodingAgent.php)
The brain of the system. Uses multi-channel processing with handler delegation:
- `processRequest()`: Main entry point, routes through channels with recovery
- `processWithChannels()`: Multi-channel processing (analysis → classification → routing → execution)
- `quickClassifyRequest()`: Fast pattern-based classification for simple requests
- `classifyRequestWithConsistency()`: Self-consistent classification via multiple reasoning paths
- `getRequestHandler()`: Routes to appropriate handler (Implementation/Demonstration/Explanation/Query/Conversation)
- `callLLMWithEnhancements()`: LLM call with retry logic and exponential backoff
- `setProgressCallback()`: Sets callback for progress reporting

### Request Handlers (src/Agent/*Handler.php)
Handlers implement `RequestHandler` interface for clean separation:
- `ImplementationHandler`: Code generation and task execution with tools
- `DemonstrationHandler`: Code examples in markdown
- `ExplanationHandler`: Educational content
- `QueryHandler`: General questions
- `ConversationHandler`: General conversation with context

### ConversationBuffer (src/Agent/ConversationBuffer.php)
Intelligent context management with relevance-based selection:
- `addMessage()`: Add messages with automatic memory management
- `getOptimalContext()`: Get contextually relevant messages for a given query
- `getRecentContext()`: Simple recent message retrieval

### ToolExecutor (src/Core/ToolExecutor.php)
Manages tool registration and execution:
- Maintains registry of available tools
- Routes function calls to appropriate tool handlers
- Logs all tool executions
- Handles errors gracefully
- Supports toolkits for grouped tool registration

### Tool System (src/Tools/*)
Each tool is a class extending the abstract Tool class:
- Implements required methods: `name()`, `description()`, `parameters()`, `execute()`
- Auto-generates OpenAI function schemas via `toOpenAISchema()`
- Examples: ReadFile, WriteFile, FindFiles, Search, Terminal

### Task Management (src/Task/*)
Type-safe task management with immutable objects:
- `Task.php`: Immutable value object with readonly properties
- `TaskStatus.php`: Enum with states (Pending, Planned, Executing, Completed)
- `TaskManager.php`: Manages task queue with proper state transitions

### Prompt Management (src/Prompts/PromptTemplates.php)
Centralized prompt templates for consistency:
- Static methods returning type-safe prompt strings
- System prompts for different modes (classification, planning, execution, etc.)
- Task-related prompts with parameter injection
- Code assistance prompts inspired by Claude Code patterns

### UI (src/CLI/UI.php)
Manages the terminal UI:
- ANSI escape codes for colors and positioning
- Real-time updates without flicker
- Input handling with readline support
- Progress animations and status tracking

## PHP Code Style Preferences

### Class Member Visibility
- **Use `protected` as the default visibility** instead of `private`
- This allows for easier extension and testing
- Only use `private` when absolutely necessary to prevent access

### PHP Version
- Target PHP 8.3+ features
- Use constructor property promotion
- Use readonly properties where appropriate
- Use typed properties
- Use match expressions instead of switch where applicable
- Use null safe operator (`?->`) where appropriate
- Use named arguments for clarity in complex method calls

### Code Organization
- Follow PSR-4 autoloading standards
- Use descriptive namespaces matching directory structure
- Group related functionality into focused classes
- Prefer composition over inheritance

### Testing
- Use Pest for testing instead of PHPUnit
- Write descriptive test names
- Focus on behavior rather than implementation

### Logging
- Use PSR-3 LoggerInterface for all logging
- Log to files only in CLI applications to avoid UI interference
- Use appropriate log levels (debug, info, warning, error)
- Include contextual data in log messages

### Terminal UI (TUI) Specific
- Always clear scrollback buffer when refreshing UI
- Use subtle colors (grays) for borders
- Ensure proper terminal size detection with fallbacks
- Handle both readline and non-readline environments
- Position input areas carefully to avoid cutoff

### Dependencies
- Prefer well-maintained packages
- Use Laravel components where they make sense (Pint for formatting)
- Keep composer.json organized with proper sections

### Error Handling
- Use exceptions for exceptional cases
- Provide meaningful error messages
- Log errors with full context
- Handle API errors gracefully

## Key Implementation Details

### Conversation History Management
```php
// History is managed by ConversationBuffer with intelligent context selection
// ConversationBuffer provides:
// - Relevance-based context retrieval via getOptimalContext()
// - Automatic memory management with configurable buffer size
// - Recent context fallback via getRecentContext()
```

### Progress Reporting System
```php
// CodingAgent and ToolRouter support progress callbacks
$agent->setProgressCallback(function($operation, $details) {
    // Handle progress updates
});
// Operations: classifying, extracting_tasks, planning_task, executing_task, calling_openai
```

### Request Classification Schema
```php
{
  'request_type' => enum['demonstration', 'implementation', 'explanation', 'query', 'conversation'],
  'requires_tools' => boolean,
  'confidence' => number (0-1),
  'reasoning' => string
}
```

### Task Planning Schema
```php
{
  'plan_summary' => string,
  'steps' => array of {
    'description' => string,
    'tool_needed' => string,
    'expected_outcome' => string
  },
  'estimated_complexity' => enum['simple', 'moderate', 'complex'],
  'potential_issues' => array of strings
}
```

### Task Value Object
```php
readonly class Task {
    public string $id;
    public string $description;
    public TaskStatus $status;
    public ?string $plan;
    public array $steps;
    public ?DateTimeImmutable $createdAt;
    
    // Immutable state transitions
    public function withPlan(string $plan, array $steps): self
    public function startExecuting(): self
    public function complete(): self
}
```

## Environment Variables
- `OPENAI_API_KEY`: Required for OpenAI API access
- `OPENAI_MODEL`: Model to use (default: gpt-4o-mini)
- `OPENAI_TEMPERATURE`: Temperature setting (default: 0.7)
- `TERMINAL_ENABLED`: Enable terminal tool (default: false, for security)
- `LOG_ENABLED`: Enable file logging
- `LOG_LEVEL`: Logging level (debug, info, warning, error)
- `LOG_PATH`: Path for log files (default: storage/logs)

## Architecture Highlights
- **Handler Pattern**: Request handling is delegated to focused handler classes via `RequestHandler` interface
- **ConversationBuffer**: Intelligent context management replaces simple array-based history
- **Self-Consistent Classification**: Multiple reasoning paths vote on request classification
- **Event-Driven**: EventBus for decoupled component communication
- **Immutable Tasks**: Task value objects with enum-based status transitions
- **UIInterface Contract**: Both SimpleUI and FullTerminalUI implement UIInterface