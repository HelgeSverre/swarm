# Extending Swarm - Developer Guide

This guide explains how to extend Swarm with new capabilities, including adding tools, creating toolkits, customizing the agent, and contributing to the project.

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Adding New Tools](#adding-new-tools)
3. [Creating Toolkits](#creating-toolkits)
4. [Customizing the Agent](#customizing-the-agent)
5. [Modifying the UI](#modifying-the-ui)
6. [Adding New Request Types](#adding-new-request-types)
7. [Testing Your Extensions](#testing-your-extensions)
8. [Contributing Guidelines](#contributing-guidelines)

## Architecture Overview

Before extending Swarm, understand the key components:

```
User Input → Swarm CLI → IPC Child Process → CodingAgent → Tool Execution → Response
```

Key extension points:
- **Tools**: Add new capabilities for file operations, API calls, etc.
- **Toolkits**: Group related tools with shared configuration
- **Request Handlers**: Add new ways to process user requests
- **UI Components**: Customize the terminal interface
- **Prompt Templates**: Modify AI behavior

## Adding New Tools

### Basic Tool Implementation

1. **Create a new tool class** in `src/Tools/`:

```php
<?php

namespace Swarm\Tools;

use Swarm\Contracts\Tool;
use Swarm\Core\ToolResponse;
use Psr\Log\LoggerInterface;

class DatabaseQueryTool extends Tool
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly \PDO $pdo
    ) {}
    
    public function name(): string
    {
        return 'db_query';
    }
    
    public function description(): string
    {
        return 'Execute a database query and return results';
    }
    
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'SQL query to execute'
                ],
                'params' => [
                    'type' => 'array',
                    'description' => 'Query parameters for prepared statements',
                    'items' => ['type' => 'string']
                ]
            ]
        ];
    }
    
    public function required(): array
    {
        return ['query'];
    }
    
    public function execute(array $params): ToolResponse
    {
        try {
            $query = $params['query'];
            $queryParams = $params['params'] ?? [];
            
            // Only allow SELECT queries for safety
            if (!preg_match('/^\s*SELECT/i', $query)) {
                throw new \Exception('Only SELECT queries are allowed');
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($queryParams);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->logger->info('Database query executed', [
                'query' => $query,
                'row_count' => count($results)
            ]);
            
            return new ToolResponse(true, [
                'rows' => $results,
                'count' => count($results)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Database query failed', [
                'error' => $e->getMessage(),
                'query' => $params['query'] ?? 'unknown'
            ]);
            
            return new ToolResponse(false, null, $e->getMessage());
        }
    }
}
```

2. **Register the tool** in `Swarm::createFromEnvironment()`:

```php
// In the tool registration section
if ($databaseUrl = $_ENV['DATABASE_URL'] ?? null) {
    $pdo = new \PDO($databaseUrl);
    $executor->register(new DatabaseQueryTool($logger, $pdo));
}
```

### Advanced Tool Features

#### Progress Reporting

For long-running operations, report progress:

```php
class DataProcessingTool extends Tool
{
    private ?\Closure $progressCallback = null;
    
    public function setProgressCallback(?\Closure $callback): void
    {
        $this->progressCallback = $callback;
    }
    
    public function execute(array $params): ToolResponse
    {
        $items = $this->loadItems($params['source']);
        $total = count($items);
        
        foreach ($items as $index => $item) {
            $this->reportProgress(sprintf(
                'Processing item %d/%d', 
                $index + 1, 
                $total
            ));
            
            $this->processItem($item);
        }
        
        return new ToolResponse(true, ['processed' => $total]);
    }
    
    private function reportProgress(string $message): void
    {
        if ($this->progressCallback) {
            ($this->progressCallback)($message);
        }
    }
}
```

#### Tool Composition

Tools can use other tools via dependency injection:

```php
class GitTool extends Tool
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ToolExecutor $executor
    ) {}
    
    public function execute(array $params): ToolResponse
    {
        // Use the bash tool to run git commands
        $result = $this->executor->dispatch('bash', [
            'command' => 'git ' . $params['command'],
            'workingDirectory' => $params['repo_path'] ?? getcwd()
        ]);
        
        if (!$result->success) {
            return $result;
        }
        
        // Process git output
        $output = $this->parseGitOutput(
            $params['command'], 
            $result->result['stdout']
        );
        
        return new ToolResponse(true, $output);
    }
}
```

## Creating Toolkits

Toolkits group related tools and can provide shared configuration.

### Basic Toolkit

```php
<?php

namespace Swarm\Toolkits;

use Swarm\Contracts\Toolkit;
use Psr\Log\LoggerInterface;

class DatabaseToolkit implements Toolkit
{
    public function __construct(
        private readonly string $databaseUrl
    ) {}
    
    public function getName(): string
    {
        return 'database';
    }
    
    public function getDescription(): string
    {
        return 'Database interaction tools';
    }
    
    public function getTools(LoggerInterface $logger): array
    {
        $pdo = new \PDO($this->databaseUrl);
        
        return [
            new DatabaseQueryTool($logger, $pdo),
            new DatabaseSchemaTool($logger, $pdo),
            new DatabaseBackupTool($logger, $pdo),
        ];
    }
    
    public function getUsageGuidelines(): string
    {
        return <<<GUIDELINES
        Database Toolkit Usage:
        - Only SELECT queries are allowed through db_query
        - Use db_schema to explore table structure
        - Use db_backup before making changes
        - Connection is shared across all database tools
        GUIDELINES;
    }
}
```

### Advanced Toolkit with Filtering

Extend `AbstractToolkit` for selective tool loading:

```php
<?php

namespace Swarm\Toolkits;

use Swarm\Toolkits\AbstractToolkit;
use Psr\Log\LoggerInterface;

class CloudToolkit extends AbstractToolkit
{
    public function getName(): string
    {
        return 'cloud';
    }
    
    public function getDescription(): string
    {
        return 'Cloud service integration tools';
    }
    
    protected function getAllTools(LoggerInterface $logger): array
    {
        $tools = [];
        
        // Conditionally add tools based on available credentials
        if ($_ENV['AWS_ACCESS_KEY_ID'] ?? null) {
            $tools[] = new AwsS3Tool($logger);
            $tools[] = new AwsLambdaTool($logger);
        }
        
        if ($_ENV['GOOGLE_CLOUD_PROJECT'] ?? null) {
            $tools[] = new GoogleCloudStorageTool($logger);
            $tools[] = new GoogleCloudFunctionsTool($logger);
        }
        
        if ($_ENV['AZURE_SUBSCRIPTION_ID'] ?? null) {
            $tools[] = new AzureBlobTool($logger);
            $tools[] = new AzureFunctionsTool($logger);
        }
        
        return $tools;
    }
    
    public function getUsageGuidelines(): string
    {
        $available = array_map(
            fn($tool) => $tool->name(), 
            $this->getTools(new NullLogger())
        );
        
        return sprintf(
            "Cloud Toolkit - Available providers: %s\n" .
            "Set appropriate environment variables to enable providers.",
            implode(', ', $available)
        );
    }
}
```

### Registering Toolkits

In `Swarm::createFromEnvironment()`:

```php
// Register individual toolkit
if ($dbUrl = $_ENV['DATABASE_URL'] ?? null) {
    $toolkit = new DatabaseToolkit($dbUrl);
    foreach ($toolkit->getTools($logger) as $tool) {
        $executor->register($tool);
    }
}

// Or use a toolkit registry pattern
$toolkitRegistry = new ToolkitRegistry($logger);
$toolkitRegistry->register(new DatabaseToolkit($dbUrl));
$toolkitRegistry->register(new CloudToolkit());
$toolkitRegistry->registerAllTools($executor);
```

## Customizing the Agent

### Adding New Request Types

1. **Update the classification schema** in `CodingAgent::getClassificationSchema()`:

```php
private function getClassificationSchema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'request_type' => [
                'type' => 'string',
                'enum' => [
                    'demonstration',
                    'implementation', 
                    'explanation',
                    'query',
                    'conversation',
                    'analysis',  // New type
                    'refactoring' // New type
                ],
                'description' => 'The type of request'
            ],
            // ... rest of schema
        ]
    ];
}
```

2. **Add handler method**:

```php
private function handleAnalysis(string $request): AgentResponse
{
    $messages = $this->buildMessagesWithHistory([
        ['role' => 'system', 'content' => PromptTemplates::analysisPrompt()],
        ['role' => 'user', 'content' => $request]
    ]);
    
    $completion = $this->callOpenAI($messages);
    
    return new AgentResponse(
        AgentResponseType::Analysis,
        $completion
    );
}
```

3. **Update routing logic** in `processRequest()`:

```php
$response = match ($classification['request_type']) {
    'demonstration' => $this->handleDemonstration($request),
    'explanation' => $this->handleExplanation($request),
    'conversation' => $this->handleConversation($request),
    'analysis' => $this->handleAnalysis($request),
    'refactoring' => $this->handleRefactoring($request),
    default => $this->handleImplementation($request, $classification)
};
```

### Custom Prompt Templates

Add new prompts to `PromptTemplates` class:

```php
public static function analysisPrompt(): string
{
    return <<<PROMPT
    You are a code analysis expert. Analyze the provided code or system and provide:
    1. Architecture overview
    2. Potential issues or code smells
    3. Performance considerations
    4. Security concerns
    5. Recommendations for improvement
    
    Focus on actionable insights and be specific about locations and examples.
    PROMPT;
}
```

### Modifying Agent Behavior

Create a custom agent by extending `CodingAgent`:

```php
class SpecializedCodingAgent extends CodingAgent
{
    protected function getSystemPrompt(): string
    {
        return parent::getSystemPrompt() . "\n\n" . 
               "Additional instructions: Always consider security implications.";
    }
    
    protected function preprocessRequest(string $request): string
    {
        // Add preprocessing logic
        return $request;
    }
    
    protected function postprocessResponse(AgentResponse $response): AgentResponse
    {
        // Add postprocessing logic
        return $response;
    }
}
```

## Modifying the UI

### Adding New UI Components

1. **Create new activity types**:

```php
<?php

namespace Swarm\CLI\Activity;

class AnalysisEntry extends ActivityEntry
{
    public function __construct(
        public readonly string $analysis,
        public readonly array $metrics,
        \DateTimeImmutable $timestamp
    ) {
        parent::__construct($timestamp);
    }
    
    public function format(): string
    {
        $output = "\033[1;35m📊 Analysis\033[0m\n";
        $output .= $this->analysis . "\n";
        
        if (!empty($this->metrics)) {
            $output .= "\033[90mMetrics: " . 
                      json_encode($this->metrics) . 
                      "\033[0m\n";
        }
        
        return $output;
    }
}
```

2. **Update UI to handle new types**:

```php
// In UI::addToHistory()
public function addToHistory(ActivityEntry $entry): void
{
    $this->history[] = $entry;
    
    // Trim history if needed
    if (count($this->history) > self::MAX_HISTORY) {
        array_shift($this->history);
    }
}
```

### Custom UI Themes

Create a theme system:

```php
class Theme
{
    public const COLORS = [
        'default' => [
            'primary' => '34',    // Blue
            'success' => '32',    // Green
            'warning' => '33',    // Yellow
            'error' => '31',      // Red
            'info' => '36',       // Cyan
        ],
        'dark' => [
            'primary' => '94',    // Light blue
            'success' => '92',    // Light green
            'warning' => '93',    // Light yellow
            'error' => '91',      // Light red
            'info' => '96',       // Light cyan
        ]
    ];
    
    public static function get(string $theme = 'default'): array
    {
        return self::COLORS[$theme] ?? self::COLORS['default'];
    }
}
```

## Testing Your Extensions

### Unit Testing Tools

```php
<?php

use PHPUnit\Framework\TestCase;
use Swarm\Tools\DatabaseQueryTool;
use Psr\Log\NullLogger;

class DatabaseQueryToolTest extends TestCase
{
    private DatabaseQueryTool $tool;
    private \PDO $pdo;
    
    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE users (id INTEGER, name TEXT)');
        $this->pdo->exec("INSERT INTO users VALUES (1, 'Alice'), (2, 'Bob')");
        
        $this->tool = new DatabaseQueryTool(new NullLogger(), $this->pdo);
    }
    
    public function testSuccessfulQuery(): void
    {
        $response = $this->tool->execute([
            'query' => 'SELECT * FROM users WHERE id = ?',
            'params' => ['1']
        ]);
        
        $this->assertTrue($response->success);
        $this->assertCount(1, $response->result['rows']);
        $this->assertEquals('Alice', $response->result['rows'][0]['name']);
    }
    
    public function testRejectsNonSelectQuery(): void
    {
        $response = $this->tool->execute([
            'query' => 'DELETE FROM users'
        ]);
        
        $this->assertFalse($response->success);
        $this->assertStringContainsString('Only SELECT', $response->error);
    }
}
```

### Integration Testing

Test tools with the agent:

```php
class AgentIntegrationTest extends TestCase
{
    private CodingAgent $agent;
    
    protected function setUp(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('chat')->willReturn([
            'choices' => [[
                'message' => ['content' => 'Test response']
            ]]
        ]);
        
        $this->agent = new CodingAgent(
            $client,
            new TaskManager(),
            new ToolExecutor(new NullLogger()),
            new NullLogger()
        );
    }
    
    public function testNewRequestType(): void
    {
        $response = $this->agent->processRequest(
            'Analyze the performance of this function'
        );
        
        $this->assertEquals(
            AgentResponseType::Analysis, 
            $response->type
        );
    }
}
```

### Manual Testing

Create test scripts:

```php
#!/usr/bin/env php
<?php
// test_new_tool.php

require __DIR__ . '/vendor/autoload.php';

use Swarm\Tools\MyNewTool;
use Psr\Log\NullLogger;

$tool = new MyNewTool(new NullLogger());

// Test various scenarios
$testCases = [
    ['input' => 'test1'],
    ['input' => 'test2', 'option' => true],
    ['input' => ''], // Edge case
];

foreach ($testCases as $params) {
    echo "Testing with: " . json_encode($params) . "\n";
    $response = $tool->execute($params);
    
    if ($response->success) {
        echo "✓ Success: " . json_encode($response->result) . "\n";
    } else {
        echo "✗ Failed: " . $response->error . "\n";
    }
    echo "\n";
}
```

## Contributing Guidelines

### Code Style

Follow PSR-12 coding standards:

```bash
# Format code
composer format

# Check style
composer check-style
```

### Pull Request Process

1. **Fork and branch**:
   ```bash
   git checkout -b feature/your-feature
   ```

2. **Write tests** for your changes

3. **Update documentation**:
   - Add to this guide if adding extension points
   - Update README.md if adding user-facing features
   - Add inline PHPDoc comments

4. **Run all checks**:
   ```bash
   composer test
   composer format
   composer check
   ```

5. **Submit PR** with:
   - Clear description of changes
   - Examples of usage
   - Any breaking changes noted

### Extension Best Practices

1. **Backward Compatibility**: Don't break existing tools/APIs
2. **Error Handling**: Always return ToolResponse with appropriate errors
3. **Logging**: Use appropriate log levels
4. **Security**: Validate all inputs, especially for file/command operations
5. **Performance**: Consider impact on response time
6. **Documentation**: Update docs with new features

### Common Patterns

#### Configuration Pattern

```php
class ConfigurableTool extends Tool
{
    private array $config;
    
    public function __construct(
        LoggerInterface $logger,
        array $config = []
    ) {
        parent::__construct($logger);
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }
    
    protected function getDefaultConfig(): array
    {
        return [
            'timeout' => 30,
            'retries' => 3,
            'cache_enabled' => true,
        ];
    }
}
```

#### Factory Pattern

```php
class ToolFactory
{
    public static function create(string $type, LoggerInterface $logger): Tool
    {
        return match($type) {
            'database' => new DatabaseQueryTool($logger, self::getPDO()),
            'cache' => new CacheTool($logger, self::getCache()),
            'queue' => new QueueTool($logger, self::getQueue()),
            default => throw new \InvalidArgumentException("Unknown tool type: $type")
        };
    }
}
```

#### Service Provider Pattern

```php
class ToolServiceProvider
{
    public function register(ToolExecutor $executor, Container $container): void
    {
        // Register tools with dependency injection
        $executor->register(
            $container->make(DatabaseQueryTool::class)
        );
        
        if ($container->has('cache')) {
            $executor->register(
                $container->make(CacheTool::class)
            );
        }
    }
}
```

## Advanced Topics

### Async Tool Execution

For CPU-intensive tools, use the AsyncProcessor:

```php
class HeavyProcessingTool extends Tool
{
    public function execute(array $params): ToolResponse
    {
        // Offload to background process
        $processor = new StreamingBackgroundProcessor();
        
        $result = $processor->launch(
            json_encode(['tool' => 'process_data', 'params' => $params])
        );
        
        return new ToolResponse(true, $result);
    }
}
```

### Tool Middleware

Add preprocessing/postprocessing to all tools:

```php
class LoggingMiddleware implements ToolMiddleware
{
    public function process(Tool $tool, array $params, callable $next): ToolResponse
    {
        $start = microtime(true);
        
        $response = $next($params);
        
        $duration = microtime(true) - $start;
        $this->logger->info('Tool executed', [
            'tool' => $tool->name(),
            'duration' => $duration,
            'success' => $response->success
        ]);
        
        return $response;
    }
}
```

### Custom Serialization

For complex tool responses:

```php
class DatasetTool extends Tool
{
    public function execute(array $params): ToolResponse
    {
        $dataset = $this->loadDataset($params['path']);
        
        // Custom serialization for large data
        return new ToolResponse(true, [
            'summary' => $dataset->getSummary(),
            'preview' => $dataset->head(10),
            'shape' => $dataset->shape(),
            '_handler' => DatasetHandler::class
        ]);
    }
}
```

## Conclusion

Swarm's architecture is designed for extensibility. Whether adding simple tools or complex features, follow these patterns for maintainable extensions. The key principles are:

1. **Separation of Concerns**: Keep tools focused on one task
2. **Error Handling**: Always handle failures gracefully  
3. **Documentation**: Document your extensions thoroughly
4. **Testing**: Write tests for reliability
5. **Security**: Validate inputs and limit operations

For questions or help, open an issue on GitHub or consult the main documentation.