# AI/LLM Optimization Implementation Guide

Based on analysis of the current codebase and IMPROVEMENTS_V4.md recommendations, here are specific, actionable optimizations for the Swarm AI assistant.

## Current State Analysis

### 1. Prompt Engineering
- **Location**: `/src/Prompts/PromptTemplates.php`
- **Current Implementation**: 
  - Basic system prompts without few-shot examples
  - Chain-of-thought only in classification (lines 44-52)
  - No dynamic prompt adaptation based on complexity

### 2. Context Management
- **Location**: `/src/Agent/CodingAgent.php` (lines 1075-1124)
- **Current Implementation**:
  - Simple sliding window (last 20 messages)
  - Filters out 'tool' and 'error' messages
  - No importance scoring or intelligent pruning
  - Basic 50-message cap on total history

### 3. Model & Temperature
- **Location**: `/src/Agent/CodingAgent.php`
- **Current Settings**:
  - Fixed model: `gpt-4.1` (line 30)
  - Fixed base temperature: `0.3` (line 31)
  - Hardcoded variations: `0.3` for classification, `0.5` for planning

## Specific Improvements

### 1. Enhanced Prompt Templates with Few-Shot Examples

**File**: `/src/Prompts/PromptTemplates.php`

#### Add Few-Shot Examples to Classification (Line 44)

```php
public static function classificationSystemWithExamples(): string
{
    return 'You are an expert at understanding user intent in coding requests using Chain of Thought reasoning. ' .
        "\n\n## Few-Shot Examples:\n\n" .
        "Q: 'Show me how to implement a singleton pattern in PHP'\n" .
        "Reasoning: User wants to see code example, not create files\n" .
        "A: {\"request_type\": \"demonstration\", \"requires_tools\": false, \"confidence\": 0.95}\n\n" .
        
        "Q: 'Create a UserController.php file with CRUD operations'\n" .
        "Reasoning: User explicitly asks to create a file with specific content\n" .
        "A: {\"request_type\": \"implementation\", \"requires_tools\": true, \"confidence\": 0.90}\n\n" .
        
        "Q: 'What is dependency injection and why use it?'\n" .
        "Reasoning: User asking for explanation of concept\n" .
        "A: {\"request_type\": \"explanation\", \"requires_tools\": false, \"confidence\": 0.95}\n\n" .
        
        "Q: 'Debug this code: function add($a, $b) { return $a + $b }'\n" .
        "Reasoning: User wants help analyzing/fixing code\n" .
        "A: {\"request_type\": \"query\", \"requires_tools\": false, \"confidence\": 0.85}\n\n" .
        
        "Q: 'Refactor all controllers to use dependency injection'\n" .
        "Reasoning: User wants to modify multiple existing files\n" .
        "A: {\"request_type\": \"implementation\", \"requires_tools\": true, \"confidence\": 0.92}\n\n" .
        
        "\nIMPORTANT distinctions:\n" .
        "- When users mention 'task list' or 'my tasks', they usually mean INTERNAL task management, NOT file creation\n" .
        "- 'Create a file with tasks' or 'write tasks to a file' means FILE creation\n" .
        "- 'Add to task list' or 'update my tasks' means INTERNAL task tracking\n\n" .
        'Think step by step about what the user is asking for before classifying.';
}
```

#### Add Method for Classification (Update line 111)

```php
public static function classifyRequest(string $input): string
{
    return "Analyze this request step by step:\n\n" .
        "\"{$input}\"\n\n" .
        "## Chain of Thought Process:\n" .
        "1. What is the user literally asking for?\n" .
        "2. What is their underlying intent?\n" .
        "3. Are they asking about internal task management or file operations?\n" .
        "4. Do they need tools, or can this be answered directly?\n" .
        "5. How confident are you in this classification?\n\n" .
        "Based on your analysis, classify this request.";
}
```

### 2. Context Optimization with Importance Scoring

**File**: `/src/Agent/CodingAgent.php`

#### Add Context Optimizer Method (After line 1124)

```php
/**
 * Optimize context with importance scoring for better token management
 * 
 * @param array $history Conversation history
 * @param int $maxTokens Maximum tokens to use for context
 * @return array Optimized message history
 */
protected function optimizeContext(array $history, int $maxTokens = 3000): array
{
    if (empty($history)) {
        return [];
    }
    
    $scored = [];
    $historyCount = count($history);
    
    foreach ($history as $index => $message) {
        $score = 0;
        
        // Recency score (more recent = higher score)
        $recencyScore = (($historyCount - $index) / $historyCount) * 10;
        $score += $recencyScore;
        
        // Role importance
        if ($message['role'] === 'system') {
            $score += 20; // System messages are critical
        } elseif ($message['role'] === 'user') {
            $score += 8; // User messages provide context
        }
        
        // Content importance indicators
        $content = $message['content'] ?? '';
        
        // Error messages are important
        if (stripos($content, 'error') !== false || stripos($content, 'exception') !== false) {
            $score += 15;
        }
        
        // File operations are important
        if (preg_match('/\.(php|js|py|java|go|rs|ts|tsx|jsx)/', $content)) {
            $score += 10;
        }
        
        // Code blocks are valuable
        if (strpos($content, '```') !== false) {
            $score += 8;
        }
        
        // Function calls are important
        if (isset($message['function_call']) || strpos($content, 'function_call') !== false) {
            $score += 12;
        }
        
        // Short messages might be commands/important
        if (strlen($content) < 200 && strlen($content) > 0) {
            $score += 5;
        }
        
        // Task-related content
        if (preg_match('/task|plan|step|execute|implement/', $content, PREG_CASE_INSENSITIVE)) {
            $score += 7;
        }
        
        $scored[] = [
            'message' => $message,
            'score' => $score,
            'tokens' => $this->estimateTokens($content),
        ];
    }
    
    // Sort by score (highest first)
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    
    // Build optimized context within token budget
    $optimized = [];
    $currentTokens = 0;
    $mustInclude = [];
    $others = [];
    
    // Separate must-include messages
    foreach ($scored as $item) {
        if ($item['message']['role'] === 'system' || $item['score'] >= 25) {
            $mustInclude[] = $item;
        } else {
            $others[] = $item;
        }
    }
    
    // Always include high-priority messages
    foreach ($mustInclude as $item) {
        if ($currentTokens + $item['tokens'] <= $maxTokens) {
            $optimized[] = $item['message'];
            $currentTokens += $item['tokens'];
        }
    }
    
    // Add other messages by score until budget exhausted
    foreach ($others as $item) {
        if ($currentTokens + $item['tokens'] <= $maxTokens) {
            $optimized[] = $item['message'];
            $currentTokens += $item['tokens'];
        }
    }
    
    // Maintain chronological order
    usort($optimized, function($a, $b) {
        $timeA = $a['timestamp'] ?? 0;
        $timeB = $b['timestamp'] ?? 0;
        return $timeA <=> $timeB;
    });
    
    $this->logger?->debug('Context optimization complete', [
        'original_count' => count($history),
        'optimized_count' => count($optimized),
        'token_usage' => $currentTokens,
        'token_limit' => $maxTokens,
    ]);
    
    return $optimized;
}

/**
 * Estimate token count for a string (rough approximation)
 * 
 * @param string $text
 * @return int Estimated token count
 */
protected function estimateTokens(string $text): int
{
    // Rough estimation: ~4 characters per token for English
    // Adjust for code (more dense) vs natural language
    $hasCode = strpos($text, '```') !== false || preg_match('/[{};()[\]]/', $text);
    $charsPerToken = $hasCode ? 3.5 : 4;
    
    return (int) ceil(strlen($text) / $charsPerToken);
}
```

#### Update buildMessagesWithHistory Method (Line 1096)

```php
protected function buildMessagesWithHistory(string $currentPrompt, ?string $systemPrompt = null): array
{
    // Default system prompt if none provided
    if ($systemPrompt === null) {
        $systemPrompt = PromptTemplates::defaultSystem($this->toolExecutor->getRegisteredTools());
    }
    
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
    ];
    
    // Add conversation history (skip the last 'user' message if it's the current prompt)
    $historyToInclude = $this->conversationHistory;
    $lastMessage = end($historyToInclude);
    
    if ($lastMessage && $lastMessage['role'] === 'user' && $lastMessage['content'] === $currentPrompt) {
        array_pop($historyToInclude);
    }
    
    // Use intelligent context optimization instead of simple slicing
    $contextBudget = $this->getContextBudget();
    $optimizedHistory = $this->optimizeContext($historyToInclude, $contextBudget);
    
    foreach ($optimizedHistory as $msg) {
        // Skip tool messages in history as they need special formatting
        if ($msg['role'] === 'tool' || $msg['role'] === 'error') {
            continue;
        }
        
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content'],
        ];
    }
    
    // Add current user prompt
    $messages[] = ['role' => 'user', 'content' => $currentPrompt];
    
    $this->logger?->debug('Messages built with optimized history', [
        'total_messages' => count($messages),
        'history_messages' => count($optimizedHistory),
        'system_prompt_length' => strlen($systemPrompt),
        'current_prompt_length' => strlen($currentPrompt),
    ]);
    
    return $messages;
}

/**
 * Get context budget based on model and task
 */
protected function getContextBudget(): int
{
    // Adjust based on model context window
    return match($this->model) {
        'gpt-4-turbo', 'gpt-4-turbo-preview' => 8000,
        'gpt-4', 'gpt-4.1' => 4000,
        'gpt-3.5-turbo', 'gpt-4.1-nano' => 2000,
        default => 3000,
    };
}
```

### 3. Dynamic Temperature Scaling

**File**: `/src/Agent/CodingAgent.php`

#### Add Temperature Management (After constructor)

```php
/**
 * Get optimal temperature for specific task type
 * 
 * @param string $taskType Type of task being performed
 * @param array $context Additional context for temperature decision
 * @return float Temperature value between 0 and 1
 */
protected function getTemperatureForTask(string $taskType, array $context = []): float
{
    // Base temperatures for different task types
    $temperatures = [
        // Deterministic tasks (need consistency)
        'classification' => 0.1,
        'extraction' => 0.1,
        'parsing' => 0.1,
        
        // Structured tasks (need some creativity but mostly structure)
        'planning' => 0.3,
        'execution' => 0.3,
        'analysis' => 0.3,
        
        // Balanced tasks
        'explanation' => 0.5,
        'demonstration' => 0.5,
        'refactoring' => 0.5,
        
        // Creative tasks
        'conversation' => 0.7,
        'brainstorming' => 0.8,
        'code_generation' => 0.6,
    ];
    
    $baseTemp = $temperatures[$taskType] ?? $this->temperature;
    
    // Adjust based on context
    if (isset($context['retry_count']) && $context['retry_count'] > 0) {
        // Increase temperature on retries for different results
        $baseTemp = min(1.0, $baseTemp + (0.1 * $context['retry_count']));
    }
    
    if (isset($context['error_recovery']) && $context['error_recovery']) {
        // Lower temperature for error recovery
        $baseTemp = max(0.1, $baseTemp - 0.2);
    }
    
    if (isset($context['complexity']) && $context['complexity'] === 'high') {
        // Slightly higher temperature for complex tasks
        $baseTemp = min(1.0, $baseTemp + 0.1);
    }
    
    $this->logger?->debug('Temperature selected for task', [
        'task_type' => $taskType,
        'base_temperature' => $temperatures[$taskType] ?? null,
        'adjusted_temperature' => $baseTemp,
        'context' => $context,
    ]);
    
    return $baseTemp;
}
```

#### Update Classification Method (Line 302)

```php
protected function classifyRequest(string $input): array
{
    // ... existing code ...
    
    $temperature = $this->getTemperatureForTask('classification');
    
    $result = $this->llmClient->chat()->create([
        'model' => $this->model,
        'messages' => $messages,
        'temperature' => $temperature, // Use dynamic temperature
        'response_format' => [
            // ... existing response format ...
        ],
    ]);
    
    // ... rest of method ...
}
```

### 4. Model Router for Task-Appropriate Model Selection

**File**: Create `/src/AI/ModelRouter.php`

```php
<?php

namespace HelgeSverre\Swarm\AI;

use Psr\Log\LoggerInterface;

class ModelRouter
{
    protected array $models = [
        'simple' => 'gpt-4.1-nano',      // Fast, cheap for simple tasks
        'standard' => 'gpt-4.1',          // Balanced performance
        'complex' => 'gpt-4-turbo',      // Best for complex reasoning
        'code' => 'gpt-4.1',              // Optimized for code
        'long' => 'gpt-4-turbo',          // Long context window
    ];
    
    public function __construct(
        protected ?LoggerInterface $logger = null
    ) {}
    
    /**
     * Select the optimal model based on task characteristics
     */
    public function selectModel(
        string $taskType,
        int $contextLength,
        int $complexity = 5,
        bool $requiresCode = false
    ): string {
        // Long context tasks need models with larger windows
        if ($contextLength > 50000) {
            $this->logger?->debug('Selected long-context model', [
                'context_length' => $contextLength,
            ]);
            return $this->models['long'];
        }
        
        // Code-heavy tasks
        if ($requiresCode || in_array($taskType, ['implementation', 'refactor', 'debug', 'generate'])) {
            $this->logger?->debug('Selected code-optimized model', [
                'task_type' => $taskType,
            ]);
            return $this->models['code'];
        }
        
        // Simple classification/extraction
        if (in_array($taskType, ['classification', 'extraction']) && $complexity <= 3) {
            $this->logger?->debug('Selected simple model', [
                'task_type' => $taskType,
                'complexity' => $complexity,
            ]);
            return $this->models['simple'];
        }
        
        // Complex reasoning tasks
        if ($complexity >= 8 || in_array($taskType, ['planning', 'analysis'])) {
            $this->logger?->debug('Selected complex model', [
                'task_type' => $taskType,
                'complexity' => $complexity,
            ]);
            return $this->models['complex'];
        }
        
        // Default to standard model
        $this->logger?->debug('Selected standard model', [
            'task_type' => $taskType,
            'complexity' => $complexity,
        ]);
        return $this->models['standard'];
    }
    
    /**
     * Get model configuration including token limits
     */
    public function getModelConfig(string $model): array
    {
        $configs = [
            'gpt-4.1-nano' => [
                'max_tokens' => 4096,
                'context_window' => 8192,
                'cost_per_1k_input' => 0.0001,
                'cost_per_1k_output' => 0.0002,
            ],
            'gpt-4.1' => [
                'max_tokens' => 4096,
                'context_window' => 8192,
                'cost_per_1k_input' => 0.01,
                'cost_per_1k_output' => 0.03,
            ],
            'gpt-4-turbo' => [
                'max_tokens' => 4096,
                'context_window' => 128000,
                'cost_per_1k_input' => 0.01,
                'cost_per_1k_output' => 0.03,
            ],
        ];
        
        return $configs[$model] ?? $configs['gpt-4.1'];
    }
}
```

### 5. Response Cache for Token Optimization

**File**: Create `/src/AI/ResponseCache.php`

```php
<?php

namespace HelgeSverre\Swarm\AI;

use Psr\Log\LoggerInterface;

class ResponseCache
{
    protected array $cache = [];
    protected int $maxSize = 100;
    protected int $ttl = 300; // 5 minutes
    
    public function __construct(
        protected ?LoggerInterface $logger = null
    ) {}
    
    /**
     * Get cached response if available
     */
    public function get(string $prompt, string $model, float $temperature): ?array
    {
        $key = $this->generateKey($prompt, $model, $temperature);
        
        if (isset($this->cache[$key])) {
            $entry = $this->cache[$key];
            
            // Check if still valid
            if (time() - $entry['timestamp'] < $this->ttl) {
                $this->logger?->debug('Cache hit', [
                    'key' => $key,
                    'age' => time() - $entry['timestamp'],
                ]);
                
                // Move to end (LRU)
                unset($this->cache[$key]);
                $this->cache[$key] = $entry;
                
                return $entry['response'];
            }
            
            // Expired, remove it
            unset($this->cache[$key]);
        }
        
        return null;
    }
    
    /**
     * Store response in cache
     */
    public function set(string $prompt, string $model, float $temperature, array $response): void
    {
        $key = $this->generateKey($prompt, $model, $temperature);
        
        // Add to cache
        $this->cache[$key] = [
            'response' => $response,
            'timestamp' => time(),
            'prompt_length' => strlen($prompt),
        ];
        
        // Enforce size limit (LRU eviction)
        if (count($this->cache) > $this->maxSize) {
            // Remove oldest (first) entry
            array_shift($this->cache);
        }
        
        $this->logger?->debug('Response cached', [
            'key' => $key,
            'cache_size' => count($this->cache),
        ]);
    }
    
    /**
     * Generate cache key
     */
    protected function generateKey(string $prompt, string $model, float $temperature): string
    {
        // Include temperature in key since it affects output
        return md5($prompt . '|' . $model . '|' . $temperature);
    }
    
    /**
     * Clear expired entries
     */
    public function cleanup(): void
    {
        $now = time();
        $expired = 0;
        
        foreach ($this->cache as $key => $entry) {
            if ($now - $entry['timestamp'] >= $this->ttl) {
                unset($this->cache[$key]);
                $expired++;
            }
        }
        
        if ($expired > 0) {
            $this->logger?->debug('Cache cleanup', [
                'expired_entries' => $expired,
                'remaining_entries' => count($this->cache),
            ]);
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $totalSize = 0;
        $ages = [];
        $now = time();
        
        foreach ($this->cache as $entry) {
            $totalSize += $entry['prompt_length'];
            $ages[] = $now - $entry['timestamp'];
        }
        
        return [
            'entries' => count($this->cache),
            'total_size' => $totalSize,
            'avg_age' => $ages ? array_sum($ages) / count($ages) : 0,
            'max_age' => $ages ? max($ages) : 0,
        ];
    }
}
```

### 6. Token Budget Manager

**File**: Create `/src/AI/TokenBudgetManager.php`

```php
<?php

namespace HelgeSverre\Swarm\AI;

use Psr\Log\LoggerInterface;

class TokenBudgetManager
{
    protected int $dailyLimit;
    protected int $used = 0;
    protected string $resetTime;
    protected string $storageFile;
    
    public function __construct(
        int $dailyLimit = 100000,
        protected ?LoggerInterface $logger = null
    ) {
        $this->dailyLimit = $dailyLimit;
        $this->storageFile = storage_path('token_usage.json');
        $this->resetTime = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $this->loadUsage();
    }
    
    /**
     * Check if we can afford the token cost
     */
    public function canAfford(int $estimatedTokens): bool
    {
        $this->resetIfNeeded();
        return ($this->used + $estimatedTokens) <= $this->dailyLimit;
    }
    
    /**
     * Track token usage
     */
    public function track(int $promptTokens, int $completionTokens, string $model, float $cost = 0): void
    {
        $this->resetIfNeeded();
        
        $totalTokens = $promptTokens + $completionTokens;
        $this->used += $totalTokens;
        
        $this->saveUsage();
        
        $percentage = round(($this->used / $this->dailyLimit) * 100, 2);
        
        $this->logger?->info('Token usage tracked', [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'daily_used' => $this->used,
            'daily_limit' => $this->dailyLimit,
            'percentage' => $percentage,
            'model' => $model,
            'cost' => $cost,
        ]);
        
        // Warn if approaching limit
        if ($percentage > 80) {
            $this->logger?->warning('Approaching daily token limit', [
                'remaining' => $this->dailyLimit - $this->used,
                'percentage' => $percentage,
            ]);
        }
    }
    
    /**
     * Get remaining tokens for today
     */
    public function getRemaining(): int
    {
        $this->resetIfNeeded();
        return max(0, $this->dailyLimit - $this->used);
    }
    
    /**
     * Get usage statistics
     */
    public function getStats(): array
    {
        $this->resetIfNeeded();
        
        return [
            'used' => $this->used,
            'limit' => $this->dailyLimit,
            'remaining' => $this->getRemaining(),
            'percentage' => round(($this->used / $this->dailyLimit) * 100, 2),
            'reset_time' => $this->resetTime,
        ];
    }
    
    /**
     * Reset usage if new day
     */
    protected function resetIfNeeded(): void
    {
        if (time() > strtotime($this->resetTime)) {
            $this->used = 0;
            $this->resetTime = date('Y-m-d 00:00:00', strtotime('+1 day'));
            $this->saveUsage();
            
            $this->logger?->info('Daily token usage reset', [
                'next_reset' => $this->resetTime,
            ]);
        }
    }
    
    /**
     * Load usage from storage
     */
    protected function loadUsage(): void
    {
        if (file_exists($this->storageFile)) {
            $data = json_decode(file_get_contents($this->storageFile), true);
            
            if ($data && isset($data['reset_time']) && $data['reset_time'] === $this->resetTime) {
                $this->used = $data['used'] ?? 0;
            }
        }
    }
    
    /**
     * Save usage to storage
     */
    protected function saveUsage(): void
    {
        $data = [
            'used' => $this->used,
            'reset_time' => $this->resetTime,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        file_put_contents($this->storageFile, json_encode($data, JSON_PRETTY_PRINT));
    }
}
```

## Integration Instructions

### 1. Update CodingAgent Constructor

```php
use HelgeSverre\Swarm\AI\ModelRouter;
use HelgeSverre\Swarm\AI\ResponseCache;
use HelgeSverre\Swarm\AI\TokenBudgetManager;

public function __construct(
    protected readonly ToolExecutor $toolExecutor,
    protected readonly TaskManager $taskManager,
    protected readonly OpenAI\Contracts\ClientContract $llmClient,
    protected readonly ?LoggerInterface $logger = null,
    protected readonly string $model = 'gpt-4.1',
    protected readonly float $temperature = 0.3,
    protected readonly ?ModelRouter $modelRouter = null,
    protected readonly ?ResponseCache $responseCache = null,
    protected readonly ?TokenBudgetManager $tokenManager = null
) {
    $this->modelRouter = $modelRouter ?? new ModelRouter($logger);
    $this->responseCache = $responseCache ?? new ResponseCache($logger);
    $this->tokenManager = $tokenManager ?? new TokenBudgetManager(100000, $logger);
}
```

### 2. Update Application Container Registration

**File**: `/src/Core/Application.php` (around line 207)

```php
// Register AI optimization services
$this->container->singleton(ModelRouter::class, function () {
    return new ModelRouter($this->logger);
});

$this->container->singleton(ResponseCache::class, function () {
    return new ResponseCache($this->logger);
});

$this->container->singleton(TokenBudgetManager::class, function () {
    $dailyLimit = (int) ($_ENV['TOKEN_DAILY_LIMIT'] ?? 100000);
    return new TokenBudgetManager($dailyLimit, $this->logger);
});

// Update CodingAgent registration
$this->container->singleton(CodingAgent::class, function () {
    return new CodingAgent(
        $this->container->get(ToolExecutor::class),
        $this->container->get(TaskManager::class),
        $this->container->get(ClientContract::class),
        $this->logger,
        $_ENV['OPENAI_MODEL'] ?? 'gpt-4.1',
        (float) ($_ENV['OPENAI_TEMPERATURE'] ?? 0.3),
        $this->container->get(ModelRouter::class),
        $this->container->get(ResponseCache::class),
        $this->container->get(TokenBudgetManager::class)
    );
});
```

### 3. Environment Variables

Add to `.env`:

```env
# Token Management
TOKEN_DAILY_LIMIT=100000

# Model Selection
OPENAI_MODEL_SIMPLE="gpt-4.1-nano"
OPENAI_MODEL_STANDARD="gpt-4.1"
OPENAI_MODEL_COMPLEX="gpt-4-turbo"

# Cache Settings
RESPONSE_CACHE_TTL=300
RESPONSE_CACHE_SIZE=100
```

## Testing

### Unit Tests

**File**: Create `/tests/Unit/AI/ModelRouterTest.php`

```php
<?php

use HelgeSverre\Swarm\AI\ModelRouter;

test('selects appropriate model for task type', function () {
    $router = new ModelRouter();
    
    // Simple tasks use nano model
    expect($router->selectModel('classification', 1000, 2))
        ->toBe('gpt-4.1-nano');
    
    // Code tasks use standard model
    expect($router->selectModel('implementation', 5000, 5, true))
        ->toBe('gpt-4.1');
    
    // Complex tasks use turbo model
    expect($router->selectModel('planning', 10000, 9))
        ->toBe('gpt-4-turbo');
    
    // Long context uses turbo
    expect($router->selectModel('analysis', 60000, 5))
        ->toBe('gpt-4-turbo');
});
```

**File**: Create `/tests/Unit/AI/ResponseCacheTest.php`

```php
<?php

use HelgeSverre\Swarm\AI\ResponseCache;

test('caches and retrieves responses', function () {
    $cache = new ResponseCache();
    
    $prompt = "Test prompt";
    $model = "gpt-4";
    $temp = 0.5;
    $response = ['content' => 'Test response'];
    
    // Store in cache
    $cache->set($prompt, $model, $temp, $response);
    
    // Retrieve from cache
    $cached = $cache->get($prompt, $model, $temp);
    expect($cached)->toBe($response);
    
    // Different temperature returns null
    $notCached = $cache->get($prompt, $model, 0.7);
    expect($notCached)->toBeNull();
});

test('respects TTL', function () {
    $cache = new ResponseCache();
    // Would need to mock time() for proper testing
});
```

## Performance Metrics

After implementation, monitor:

1. **Token Usage**:
   - Average tokens per request
   - Daily token consumption
   - Cost per operation

2. **Response Times**:
   - Cache hit rate
   - Average response time with/without cache
   - Model selection distribution

3. **Context Optimization**:
   - Average context size
   - Important message retention rate
   - Token savings from optimization

4. **Model Performance**:
   - Accuracy by model type
   - Task completion rate
   - Error rate by model

## Next Steps

1. Implement the changes in order:
   - Enhanced prompts with few-shot examples
   - Context optimization
   - Dynamic temperature
   - Model router
   - Response cache
   - Token budget manager

2. Test each component individually

3. Monitor performance metrics

4. Fine-tune thresholds based on real usage patterns

5. Consider adding:
   - Prompt versioning system
   - A/B testing framework
   - Advanced token prediction
   - Multi-provider support (Anthropic, Cohere, etc.)