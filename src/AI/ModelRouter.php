<?php

namespace HelgeSverre\Swarm\AI;

use Psr\Log\LoggerInterface;

/**
 * Intelligent model router that selects the optimal LLM model based on task characteristics.
 * 
 * This router considers:
 * - Task type and complexity
 * - Context window requirements
 * - Code generation needs
 * - Cost optimization
 * 
 * Models are selected to balance performance, cost, and capability requirements.
 */
class ModelRouter
{
    /**
     * Available models with their primary use cases
     */
    protected array $models = [
        'simple' => 'gpt-4.1-nano',      // Fast, cheap for simple tasks
        'standard' => 'gpt-4.1',          // Balanced performance
        'complex' => 'gpt-4-turbo',      // Best for complex reasoning
        'code' => 'gpt-4.1',              // Optimized for code generation
        'long' => 'gpt-4-turbo',          // Large context window
    ];
    
    /**
     * Model configurations with capabilities and costs
     */
    protected array $modelConfigs = [
        'gpt-4.1-nano' => [
            'max_tokens' => 4096,
            'context_window' => 8192,
            'cost_per_1k_input' => 0.0001,
            'cost_per_1k_output' => 0.0002,
            'strengths' => ['speed', 'cost', 'simple_tasks'],
        ],
        'gpt-4.1' => [
            'max_tokens' => 4096,
            'context_window' => 8192,
            'cost_per_1k_input' => 0.01,
            'cost_per_1k_output' => 0.03,
            'strengths' => ['code', 'reasoning', 'balanced'],
        ],
        'gpt-4-turbo' => [
            'max_tokens' => 4096,
            'context_window' => 128000,
            'cost_per_1k_input' => 0.01,
            'cost_per_1k_output' => 0.03,
            'strengths' => ['complex', 'long_context', 'analysis'],
        ],
        'gpt-3.5-turbo' => [
            'max_tokens' => 4096,
            'context_window' => 16384,
            'cost_per_1k_input' => 0.0005,
            'cost_per_1k_output' => 0.0015,
            'strengths' => ['speed', 'cost', 'general'],
        ],
    ];
    
    public function __construct(
        protected ?LoggerInterface $logger = null
    ) {
        // Allow environment override of model mappings
        if (isset($_ENV['OPENAI_MODEL_SIMPLE'])) {
            $this->models['simple'] = $_ENV['OPENAI_MODEL_SIMPLE'];
        }
        if (isset($_ENV['OPENAI_MODEL_STANDARD'])) {
            $this->models['standard'] = $_ENV['OPENAI_MODEL_STANDARD'];
        }
        if (isset($_ENV['OPENAI_MODEL_COMPLEX'])) {
            $this->models['complex'] = $_ENV['OPENAI_MODEL_COMPLEX'];
        }
    }
    
    /**
     * Select the optimal model based on task characteristics
     * 
     * @param string $taskType Type of task (classification, implementation, etc.)
     * @param int $contextLength Estimated context length in tokens
     * @param int $complexity Task complexity score (1-10)
     * @param bool $requiresCode Whether the task involves code generation
     * @return string The selected model identifier
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
                'reason' => 'context_length_exceeded',
                'context_length' => $contextLength,
                'model' => $this->models['long'],
            ]);
            return $this->models['long'];
        }
        
        // Code-heavy tasks benefit from code-optimized models
        if ($requiresCode || in_array($taskType, ['implementation', 'refactor', 'debug', 'generate', 'test'])) {
            $this->logger?->debug('Selected code-optimized model', [
                'reason' => 'code_generation_required',
                'task_type' => $taskType,
                'model' => $this->models['code'],
            ]);
            return $this->models['code'];
        }
        
        // Simple classification/extraction can use lighter models
        if (in_array($taskType, ['classification', 'extraction', 'parsing']) && $complexity <= 3) {
            $this->logger?->debug('Selected simple model for efficiency', [
                'reason' => 'simple_task',
                'task_type' => $taskType,
                'complexity' => $complexity,
                'model' => $this->models['simple'],
            ]);
            return $this->models['simple'];
        }
        
        // Complex reasoning tasks need more capable models
        if ($complexity >= 8 || in_array($taskType, ['planning', 'analysis', 'architecture'])) {
            $this->logger?->debug('Selected complex model for reasoning', [
                'reason' => 'high_complexity',
                'task_type' => $taskType,
                'complexity' => $complexity,
                'model' => $this->models['complex'],
            ]);
            return $this->models['complex'];
        }
        
        // Default to standard model for balanced performance
        $this->logger?->debug('Selected standard model', [
            'reason' => 'default_selection',
            'task_type' => $taskType,
            'complexity' => $complexity,
            'model' => $this->models['standard'],
        ]);
        
        return $this->models['standard'];
    }
    
    /**
     * Get model configuration including token limits and costs
     * 
     * @param string $model Model identifier
     * @return array Model configuration
     */
    public function getModelConfig(string $model): array
    {
        return $this->modelConfigs[$model] ?? $this->modelConfigs['gpt-4.1'];
    }
    
    /**
     * Estimate the cost of using a model for given token counts
     * 
     * @param string $model Model identifier
     * @param int $inputTokens Number of input tokens
     * @param int $outputTokens Number of output tokens
     * @return float Estimated cost in dollars
     */
    public function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $config = $this->getModelConfig($model);
        
        $inputCost = ($inputTokens / 1000) * $config['cost_per_1k_input'];
        $outputCost = ($outputTokens / 1000) * $config['cost_per_1k_output'];
        
        return round($inputCost + $outputCost, 4);
    }
    
    /**
     * Check if a model can handle the required context length
     * 
     * @param string $model Model identifier
     * @param int $contextLength Required context length
     * @return bool Whether the model can handle the context
     */
    public function canHandleContext(string $model, int $contextLength): bool
    {
        $config = $this->getModelConfig($model);
        return $contextLength <= $config['context_window'];
    }
    
    /**
     * Get a model recommendation with reasoning
     * 
     * @param string $taskType Task type
     * @param int $contextLength Context length
     * @param int $complexity Complexity score
     * @param bool $requiresCode Code generation requirement
     * @return array Model recommendation with reasoning
     */
    public function recommend(
        string $taskType,
        int $contextLength,
        int $complexity = 5,
        bool $requiresCode = false
    ): array {
        $model = $this->selectModel($taskType, $contextLength, $complexity, $requiresCode);
        $config = $this->getModelConfig($model);
        
        $reasons = [];
        
        if ($contextLength > 50000) {
            $reasons[] = 'Large context window required';
        }
        
        if ($requiresCode) {
            $reasons[] = 'Code generation capabilities needed';
        }
        
        if ($complexity >= 8) {
            $reasons[] = 'Complex reasoning required';
        } elseif ($complexity <= 3) {
            $reasons[] = 'Simple task, optimizing for cost';
        }
        
        return [
            'model' => $model,
            'reasons' => $reasons,
            'config' => $config,
            'estimated_cost_per_1k' => $config['cost_per_1k_input'] + $config['cost_per_1k_output'],
        ];
    }
    
    /**
     * Get all available models with their configurations
     * 
     * @return array All model configurations
     */
    public function getAvailableModels(): array
    {
        return $this->modelConfigs;
    }
}