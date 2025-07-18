# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Swarm is an AI-powered coding agent written in PHP that integrates with OpenAI's GPT models. It's a CLI tool that processes natural language requests, plans tasks, and executes them using a modular tool system.

## Key Architecture

### Agent Pattern
The `CodingAgent` class (src/Agent/CodingAgent.php) is the core component that:
- Processes user requests through natural language
- Extracts and plans tasks using AI
- Executes tasks via the tool system
- Maintains conversation history for context

### Tool System
- **ToolRouter** (src/Router/ToolRouter.php): Dispatches tool calls and logs execution
- **ToolRegistry** (src/Tools/ToolRegistry.php): Registers available tools
- Tools are modular and located in src/Tools/ (FileTools, SearchTools, SystemTools)

### Entry Points
- **bin/swarm**: Shell script for environment setup
- **cli.php**: PHP entry point that initializes SwarmCLI

## Essential Commands

```bash
# Run the agent
./bin/swarm
# or
composer swarm

# Run tests
composer test

# Run specific test file
vendor/bin/pest tests/Unit/ExampleTest.php

# Run tests with coverage
vendor/bin/pest --coverage

# Static analysis
composer check

# Code formatting
composer format

# Install dependencies
composer install
```

## Development Workflow

1. **Environment Setup**: Copy `.env.example` to `.env` and add your OpenAI API key
2. **Code Style**: Uses Laravel preset via Pint - run `composer format` before committing
3. **Testing**: Uses Pest PHP framework with Unit and Feature test organization
4. **Static Analysis**: PHPStan at level 5 - run `composer check` to verify code quality

## Adding New Tools

1. Create a new tool class in `src/Tools/`
2. Implement required methods for tool execution
3. Register the tool in `ToolRegistry`
4. Tools should return structured `ToolResponse` objects

## Key Dependencies

- **PHP 8.3+** with json and mbstring extensions
- **openai-php/client**: OpenAI integration
- **monolog/monolog**: Logging (configurable via LOG_ENABLED env var)
- **pestphp/pest**: Testing framework
- **laravel/pint**: Code formatting

## Environment Variables

- `OPENAI_API_KEY`: Required for AI functionality
- `LOG_ENABLED`: Toggle logging (true/false)
- `LOG_PATH`: Log directory (default: storage/logs)
- `APP_ENV`: Environment setting (affects logging behavior)

## Error Handling

- Custom exceptions in `src/Exceptions/`
- Comprehensive logging when enabled
- Tool execution errors are captured and returned as ToolResponse objects

## TUI Integration

The project includes a Terminal UI renderer (`src/CLI/TUIRenderer.php`) for enhanced user experience in the command line.