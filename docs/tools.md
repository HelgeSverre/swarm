# Swarm Tools Reference

This document provides a comprehensive reference for all tools available in the Swarm AI coding assistant.

## Table of Contents
1. [Tool System Overview](#tool-system-overview)
2. [File System Tools](#file-system-tools)
3. [Execution Tools](#execution-tools)
4. [Web Tools](#web-tools)
5. [Toolkit System](#toolkit-system)
6. [Creating Custom Tools](#creating-custom-tools)

## Tool System Overview

Swarm's tool system provides the AI agent with capabilities to interact with the file system, execute commands, and fetch web content. All tools follow a consistent interface and return structured responses.

### Tool Interface

Every tool implements the following interface:

```php
abstract class Tool
{
    abstract public function name(): string;
    abstract public function description(): string;
    abstract public function parameters(): array;
    abstract public function required(): array;
    abstract public function execute(array $params): ToolResponse;
    
    public function toOpenAISchema(): array; // Auto-generated
}
```

### Tool Response

All tools return a `ToolResponse` object:

```php
class ToolResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $result,
        public readonly ?string $error = null
    ) {}
}
```

## File System Tools

### read_file

Reads the contents of a file from the filesystem.

**Parameters:**
- `path` (string, required): The file path to read

**Returns:**
- `content`: The file contents
- `size`: File size in bytes
- `lines`: Number of lines in the file

**Example:**
```json
{
  "name": "read_file",
  "parameters": {
    "path": "/path/to/file.txt"
  }
}
```

**Response:**
```json
{
  "success": true,
  "result": {
    "content": "File contents here...",
    "size": 1234,
    "lines": 45
  }
}
```

### write_file

Creates or overwrites a file with the specified content.

**Parameters:**
- `path` (string, required): The file path to write
- `content` (string, required): The content to write

**Returns:**
- `bytes`: Number of bytes written

**Example:**
```json
{
  "name": "write_file",
  "parameters": {
    "path": "/path/to/file.txt",
    "content": "Hello, World!"
  }
}
```

### grep

Searches for files and content using pattern matching. Supports both file name patterns and content search.

**Parameters:**
- `pattern` (string, optional): Content pattern to search for (regex supported)
- `path` (string, optional): Directory to search in (default: current directory)
- `filePattern` (string, optional): File name pattern (glob syntax, e.g., "*.php")
- `caseSensitive` (boolean, optional): Case-sensitive search (default: false)
- `recursive` (boolean, optional): Search recursively (default: true)
- `filesOnly` (boolean, optional): Only return file paths, not content (default: false)

**Returns:**
- Array of matches with file paths and matching lines (or just paths if filesOnly=true)

**Example - Search content:**
```json
{
  "name": "grep",
  "parameters": {
    "pattern": "TODO",
    "path": "./src",
    "filePattern": "*.php",
    "caseSensitive": false
  }
}
```

**Example - Find files:**
```json
{
  "name": "grep",
  "parameters": {
    "filePattern": "*.test.php",
    "path": "./tests",
    "filesOnly": true
  }
}
```

## Execution Tools

### bash

Executes shell commands in a bash environment.

**Parameters:**
- `command` (string, required): The command to execute
- `workingDirectory` (string, optional): Working directory for command execution
- `timeout` (integer, optional): Command timeout in seconds (default: 300)

**Returns:**
- `stdout`: Standard output from the command
- `stderr`: Standard error output
- `returnCode`: Command exit code

**Example:**
```json
{
  "name": "bash",
  "parameters": {
    "command": "ls -la",
    "workingDirectory": "/home/user/project"
  }
}
```

**Security Note:** Commands are executed with the same permissions as the Swarm process. Be cautious with commands that modify system state.

### playwright

Controls a browser using Playwright for web automation, testing, and scraping.

**Parameters:**
- `action` (string, required): The action to perform
- `url` (string, optional): URL to navigate to
- `selector` (string, optional): CSS selector for element interactions
- `text` (string, optional): Text input for typing actions
- `code` (string, optional): JavaScript code to evaluate
- `options` (object, optional): Additional options (browser type, headless mode, etc.)

**Available Actions:**
- `launch`: Start a new browser session
- `navigate`: Go to a URL
- `screenshot`: Take a screenshot
- `click`: Click an element
- `type`: Type text into an element
- `evaluate`: Execute JavaScript
- `waitForSelector`: Wait for element to appear
- `getText`: Get text content of element
- `getAttribute`: Get attribute value
- `close`: Close the browser

**Example - Web scraping:**
```json
{
  "name": "playwright",
  "parameters": {
    "action": "launch",
    "options": {
      "browser": "chromium",
      "headless": true
    }
  }
}
```

```json
{
  "name": "playwright",
  "parameters": {
    "action": "navigate",
    "url": "https://example.com"
  }
}
```

```json
{
  "name": "playwright",
  "parameters": {
    "action": "getText",
    "selector": "h1"
  }
}
```

**Session Management:** The tool maintains browser sessions between calls. Use the same session for multiple operations, then close when done.

## Web Tools

### web_fetch

Fetches content from a URL and converts it to a format suitable for AI processing.

**Parameters:**
- `url` (string, required): The URL to fetch

**Returns:**
- Converted content based on content type:
  - HTML: Converted to clean text
  - JSON: Pretty-printed JSON
  - PDF: Extracted text content
  - XML: Preserved structure
  - Plain text: Original content

**Example:**
```json
{
  "name": "web_fetch",
  "parameters": {
    "url": "https://api.example.com/data.json"
  }
}
```

**Features:**
- Automatic content type detection
- HTML to text conversion for readability
- PDF text extraction
- Handles various encodings

### tavily_search

Advanced web search using the Tavily API. Provides high-quality, recent search results.

**Requirements:** Requires `TAVILY_API_KEY` environment variable.

**Parameters:**
- `query` (string, required): Search query
- `search_depth` (string, optional): "basic" or "advanced" (default: "advanced")
- `topic` (string, optional): "general", "news", or "research" (default: "general")
- `days` (integer, optional): Limit results to last N days
- `max_results` (integer, optional): Maximum results to return (default: 5)

**Returns:**
- `answer`: Direct answer to the query (if available)
- `results`: Array of search results with:
  - `title`: Page title
  - `url`: Source URL
  - `content`: Relevant content snippet
  - `score`: Relevance score

**Example:**
```json
{
  "name": "tavily_search",
  "parameters": {
    "query": "latest PHP 8.3 features",
    "topic": "general",
    "days": 30,
    "max_results": 10
  }
}
```

### tavily_extract

Extracts clean, formatted content from a URL using the Tavily API.

**Requirements:** Requires `TAVILY_API_KEY` environment variable.

**Parameters:**
- `url` (string, required): URL to extract content from

**Returns:**
- `content`: Extracted content in markdown format

**Example:**
```json
{
  "name": "tavily_extract",
  "parameters": {
    "url": "https://docs.example.com/guide"
  }
}
```

**Use Cases:**
- Documentation extraction
- Article content retrieval
- Clean text extraction from complex web pages

## Toolkit System

Toolkits group related tools and provide additional context for their usage.

### TavilyToolkit

Groups the Tavily search and extract tools.

**Registration:**
```php
$toolkit = new TavilyToolkit($apiKey);
$tools = $toolkit->getTools($logger);
```

**Usage Guidelines:**
The toolkit provides:
- Automatic API key validation
- Grouped registration of search and extract tools
- Consistent error handling for API failures

### Creating Custom Toolkits

Implement the `Toolkit` interface:

```php
interface Toolkit
{
    public function getName(): string;
    public function getDescription(): string;
    public function getTools(LoggerInterface $logger): array;
    public function getUsageGuidelines(): string;
}
```

Or extend `AbstractToolkit` for filtering functionality:

```php
class MyToolkit extends AbstractToolkit
{
    public function getName(): string
    {
        return 'my_toolkit';
    }
    
    public function getDescription(): string
    {
        return 'My custom toolkit';
    }
    
    protected function getAllTools(LoggerInterface $logger): array
    {
        return [
            new MyTool1($logger),
            new MyTool2($logger),
        ];
    }
}
```

## Creating Custom Tools

### Step 1: Create Tool Class

```php
namespace Swarm\Tools;

use Swarm\Contracts\Tool;
use Swarm\Core\ToolResponse;
use Psr\Log\LoggerInterface;

class MyCustomTool extends Tool
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}
    
    public function name(): string
    {
        return 'my_tool';
    }
    
    public function description(): string
    {
        return 'Does something useful';
    }
    
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'input' => [
                    'type' => 'string',
                    'description' => 'Input parameter'
                ]
            ]
        ];
    }
    
    public function required(): array
    {
        return ['input'];
    }
    
    public function execute(array $params): ToolResponse
    {
        try {
            $input = $params['input'];
            
            // Tool logic here
            $result = $this->processInput($input);
            
            return new ToolResponse(true, $result);
        } catch (\Exception $e) {
            $this->logger->error('Tool execution failed', [
                'error' => $e->getMessage()
            ]);
            return new ToolResponse(false, null, $e->getMessage());
        }
    }
    
    private function processInput(string $input): mixed
    {
        // Implementation
        return "Processed: $input";
    }
}
```

### Step 2: Register the Tool

In `Swarm::createFromEnvironment()`:

```php
$executor->register(new MyCustomTool($logger));
```

### Step 3: Tool Best Practices

1. **Error Handling**: Always catch exceptions and return appropriate ToolResponse
2. **Logging**: Use the injected logger for debugging and error tracking
3. **Validation**: Validate parameters before processing
4. **Atomicity**: Make operations atomic where possible
5. **Timeouts**: Implement timeouts for long-running operations
6. **Security**: Validate file paths and sanitize inputs
7. **Documentation**: Provide clear descriptions and parameter documentation

### Advanced Features

#### Progress Reporting

Tools can report progress through callbacks:

```php
public function execute(array $params): ToolResponse
{
    $this->reportProgress('Starting operation...');
    
    // Long operation
    for ($i = 0; $i < 100; $i++) {
        $this->reportProgress("Processing: $i%");
        // Work here
    }
    
    return new ToolResponse(true, $result);
}
```

#### Tool Composition

Tools can use other tools:

```php
public function execute(array $params): ToolResponse
{
    // Read a file using another tool
    $readResult = $this->executor->dispatch('read_file', [
        'path' => $params['config_path']
    ]);
    
    if (!$readResult->success) {
        return $readResult;
    }
    
    // Process the content
    $config = json_decode($readResult->result['content']);
    // ...
}
```

## Tool Guidelines for AI Agent

When the AI agent uses tools:

1. **Minimize Tool Calls**: Batch operations when possible
2. **Check Prerequisites**: Ensure files/directories exist before operations
3. **Handle Errors Gracefully**: Check success status and handle failures
4. **Use Appropriate Tools**: Use grep for searching, not bash with find
5. **Respect Timeouts**: Long operations should be broken into steps
6. **Provide Feedback**: Report progress on long operations

## Performance Considerations

1. **File Operations**: 
   - Read large files in chunks if needed
   - Use grep for searching instead of reading entire files

2. **Web Operations**:
   - Cache results when appropriate
   - Use Tavily for search instead of web_fetch + parsing

3. **Command Execution**:
   - Set appropriate timeouts
   - Avoid resource-intensive commands

4. **Browser Automation**:
   - Reuse browser sessions
   - Close browsers when done
   - Use headless mode for better performance