# CLAUDE.md - Project-specific instructions for Swarm

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

## Project Structure
```
src/
├── Agent/          # AI agent logic
├── CLI/            # Command-line interface components
├── Exceptions/     # Custom exceptions
├── Router/         # Tool routing system
├── Task/           # Task management
└── Tools/          # Individual tool implementations
```

## Key Components
- **CodingAgent**: Main AI agent that processes requests
- **TUIRenderer**: Terminal UI rendering with ANSI codes
- **ToolRouter**: Routes tool calls to appropriate handlers
- **TaskManager**: Manages task queue and execution

## Environment Variables
- `OPENAI_API_KEY`: Required for OpenAI API access
- `OPENAI_MODEL`: Model to use (default: gpt-4)
- `OPENAI_TEMPERATURE`: Temperature setting (default: 0.7)
- `LOG_ENABLED`: Enable file logging
- `LOG_LEVEL`: Logging level (debug, info, warning, error)
- `LOG_PATH`: Path for log files (default: storage/logs)