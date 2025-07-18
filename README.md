# Swarm 💮

AI-powered coding agent written in PHP that helps with development tasks through natural language.

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

1. Extract tasks from your input
2. Plan the execution
3. Execute tasks using available tools
4. Show results in the terminal

Type `exit` or `quit` to stop.

## How It Works

Swarm uses OpenAI's GPT-4 to understand your requests and break them down into executable tasks. It has access to
various tools:

- **File operations**: Read, write, and search files
- **Code analysis**: Search for patterns, analyze code structure
- **System commands**: Execute shell commands
- **Project navigation**: Browse directories and understand project structure

The agent maintains conversation context and learns about your project as it works.

## Development

```bash
# Run tests
composer test

# Code formatting
composer format

# Static analysis
composer check
```

## Configuration

Environment variables (in `.env`):

- `OPENAI_API_KEY`: Your OpenAI API key (required)
- `LOG_ENABLED`: Enable logging (true/false)
- `LOG_PATH`: Log directory (default: storage/logs)
- `LOG_LEVEL`: Log level (debug, info, warning, error)