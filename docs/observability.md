# Observability

## Overview

Swarm uses structured logging for observability, providing clear insights into operations without the complexity of distributed tracing frameworks.

## Current Approach

Swarm uses PSR-3 structured logging to provide comprehensive observability:

```php
// Simple, clean logging with context
$this->logger?->info('Processing request', [
    'input_length' => mb_strlen($userInput),
    'conversation_length' => count($this->conversationHistory),
]);

// LLM usage tracking
$this->logger?->debug('LLM usage', [
    'operation' => 'classify_request',
    'model' => $this->model,
    'prompt_tokens' => $result->usage->promptTokens,
    'completion_tokens' => $result->usage->completionTokens,
    'total_tokens' => $result->usage->totalTokens,
    'duration_ms' => round($duration * 1000, 2),
]);

// Tool execution tracking
$this->logger?->info('Tool dispatch completed', [
    'tool' => $tool,
    'log_id' => $logId,
    'duration_ms' => round($duration * 1000, 2),
    'success' => $response->isSuccess(),
]);
```

## Key Benefits

1. **Simple and maintainable** - No complex frameworks or abstractions
2. **No external dependencies** - Just PSR-3 logger interface
3. **Easy testing** - No special initialization or mocking required
4. **High performance** - Minimal overhead, no tracing costs
5. **Clear mental model** - Standard logging patterns everyone understands

## Log Levels and Usage

- **info** - Major operations (request processing, task execution, tool completion)
- **debug** - Detailed operations (LLM usage, token counts, durations)
- **warning** - Slow operations, retries, degraded functionality
- **error** - Failures, exceptions, unrecoverable errors

## Configuration

Logging is configured via environment variables:

```bash
LOG_ENABLED=true        # Enable/disable logging
LOG_PATH=logs          # Log directory path
LOG_LEVEL=debug        # Minimum log level
```

## Key Principle

Good observability doesn't require complex frameworks. For a CLI tool like Swarm, structured logging with meaningful context provides excellent visibility into operations without the overhead of distributed tracing.