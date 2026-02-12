<?php

return [
    /**
     * GPT-5 Model Configuration
     *
     * This configuration file manages GPT-5 specific settings for the Swarm agent system.
     */

    // Model Selection Strategy
    'models' => [
        // Use nano for high-volume, simple tasks
        'nano' => [
            'name' => 'gpt-5-nano',
            'use_for' => ['simple_queries', 'basic_completions', 'formatting'],
            'max_tokens' => 2000,
            'cost_per_1m_tokens' => 1.50,
            'speed' => 'fastest',
        ],

        // Use mini for standard operations (recommended default)
        'mini' => [
            'name' => 'gpt-5-mini',
            'use_for' => ['code_generation', 'refactoring', 'standard_tasks'],
            'max_tokens' => 4096,
            'cost_per_1m_tokens' => 3.00,
            'speed' => 'fast',
        ],

        // Use full GPT-5 for complex reasoning
        'full' => [
            'name' => 'gpt-5',
            'use_for' => ['complex_architecture', 'system_design', 'debugging', 'multimodal'],
            'max_tokens' => 8192,
            'cost_per_1m_tokens' => 15.00,
            'speed' => 'standard',
        ],
    ],

    // Default model selection
    'default_model' => env('GPT5_DEFAULT_MODEL', 'gpt-5-mini'),

    // Reasoning Effort Configuration
    'reasoning_effort' => [
        'default' => env('GPT5_REASONING_EFFORT', 'medium'),

        // Task-based reasoning mapping
        'task_mapping' => [
            'classification' => 'minimal',
            'simple_generation' => 'low',
            'standard_coding' => 'medium',
            'complex_planning' => 'high',
            'debugging' => 'high',
            'architecture_design' => 'high',
        ],

        // Automatic adjustment based on error rate
        'auto_adjust' => env('GPT5_AUTO_ADJUST_REASONING', true),
        'error_threshold' => 0.2, // Increase reasoning if error rate > 20%
    ],

    // Verbosity Settings
    'verbosity' => [
        'default' => env('GPT5_VERBOSITY', 'medium'),

        // Context-based verbosity
        'context_mapping' => [
            'user_facing' => 'high',
            'internal_processing' => 'low',
            'documentation' => 'high',
            'logging' => 'medium',
        ],
    ],

    // Custom Tools Configuration
    'custom_tools' => [
        'enabled' => env('GPT5_USE_CUSTOM_TOOLS', true),

        // Tools that benefit from custom format
        'custom_enabled_tools' => [
            'write_code',
            'generate_sql',
            'create_config',
            'write_documentation',
            'generate_tests',
        ],

        // Grammar constraints for custom tools
        'grammars' => [
            'php' => 'php-8.3',
            'javascript' => 'es2024',
            'typescript' => 'typescript-5.0',
            'python' => 'python-3.11',
            'sql' => 'ansi-sql',
        ],
    ],

    // Responses API Configuration
    'responses_api' => [
        'enabled' => env('GPT5_USE_RESPONSES_API', false),

        // Enable for specific operations
        'enable_for' => [
            'complex_debugging',
            'multi_step_planning',
            'code_review',
            'architecture_analysis',
        ],

        // Chain-of-thought settings
        'chain_of_thought' => [
            'max_depth' => 5,
            'preserve_context' => true,
        ],
    ],

    // Token Management
    'tokens' => [
        'max_completion_tokens' => env('GPT5_MAX_COMPLETION_TOKENS', 4096),

        // Dynamic token allocation
        'dynamic_allocation' => [
            'simple_task' => 500,
            'standard_task' => 2000,
            'complex_task' => 4096,
            'extensive_task' => 8192,
        ],

        // Token optimization
        'optimization' => [
            'use_caching' => true,
            'cache_ttl' => 3600, // 1 hour
            'compress_history' => true,
            'history_limit' => 20, // messages
        ],
    ],

    // Migration Settings
    'migration' => [
        // Gradual rollout configuration
        'rollout_percentage' => env('GPT5_ROLLOUT_PERCENTAGE', 10), // Start with 10%

        // A/B testing
        'ab_testing' => [
            'enabled' => env('GPT5_AB_TESTING', true),
            'control_model' => 'gpt-4.1',
            'test_model' => 'gpt-5-mini',
            'metrics_tracking' => true,
        ],

        // Fallback configuration
        'fallback' => [
            'enabled' => true,
            'to_model' => 'gpt-4.1',
            'on_errors' => ['rate_limit', 'timeout', 'invalid_response'],
            'max_retries' => 2,
        ],
    ],

    // Performance Monitoring
    'monitoring' => [
        'track_metrics' => true,

        'metrics' => [
            'response_time',
            'token_usage',
            'error_rate',
            'task_success_rate',
            'cost_per_request',
        ],

        // Alerting thresholds
        'alerts' => [
            'error_rate_threshold' => 0.05, // 5%
            'response_time_threshold' => 5000, // 5 seconds
            'cost_threshold_daily' => 100, // $100/day
        ],
    ],

    // Multimodal Configuration
    'multimodal' => [
        'enabled' => env('GPT5_MULTIMODAL_ENABLED', true),

        // Supported modalities
        'modalities' => [
            'text' => true,
            'image' => true,
            'audio' => true,
            'video' => false, // Coming soon
        ],

        // Processing settings
        'processing' => [
            'max_image_size' => '10MB',
            'supported_image_formats' => ['jpg', 'png', 'gif', 'webp'],
            'max_audio_duration' => 300, // 5 minutes
            'supported_audio_formats' => ['mp3', 'wav', 'ogg'],
        ],
    ],

    // Cost Optimization
    'cost_optimization' => [
        'enabled' => true,

        // Strategies
        'strategies' => [
            'model_routing' => true, // Route to appropriate model based on task
            'prompt_compression' => true, // Compress prompts when possible
            'result_caching' => true, // Cache frequent responses
            'batch_processing' => true, // Batch similar requests
        ],

        // Budget controls
        'budget' => [
            'daily_limit' => env('GPT5_DAILY_BUDGET', 100), // $100
            'monthly_limit' => env('GPT5_MONTHLY_BUDGET', 3000), // $3000
            'alert_at_percentage' => 80, // Alert at 80% of budget
        ],
    ],

    // Feature Flags
    'features' => [
        'use_structured_outputs' => true,
        'use_function_calling' => true,
        'use_parallel_tool_calls' => true,
        'use_prompt_caching' => true,
        'use_batch_api' => false, // For non-real-time operations
    ],

    // Prompt Templates
    'prompt_improvements' => [
        // GPT-5 specific prompt optimizations
        'use_capitals_for_emphasis' => true, // Instead of bold
        'prefer_bullet_points' => true, // Over paragraphs
        'use_labeled_sections' => true, // For clarity
        'explicit_stop_conditions' => true, // For agent tasks
    ],

    // Error Handling
    'error_handling' => [
        'retry_with_higher_reasoning' => true,
        'log_all_errors' => true,
        'report_to_monitoring' => true,

        // Error recovery strategies
        'recovery_strategies' => [
            'timeout' => 'retry_with_lower_model',
            'rate_limit' => 'queue_and_retry',
            'invalid_response' => 'increase_reasoning_effort',
            'tool_error' => 'retry_with_alternative_tool',
        ],
    ],
];
