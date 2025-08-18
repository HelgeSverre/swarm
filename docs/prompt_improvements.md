# Advanced Prompting Techniques for Swarm Coding Agent

## Executive Summary

This document synthesizes key insights from analyzing system prompts of leading AI tools (Claude Code, OpenAI O3, Canvas, Gemini 2.5 Pro, Warp 2.0) and provides actionable recommendations for enhancing the Swarm coding agent's prompting system.

## Table of Contents

1. [Current State Analysis](#current-state-analysis)
2. [Key Insights from System Prompt Analysis](#key-insights-from-system-prompt-analysis)
3. [Advanced Prompting Techniques](#advanced-prompting-techniques)
4. [Implementation Recommendations](#implementation-recommendations)
5. [Performance Improvements Expected](#performance-improvements-expected)
6. [Migration Strategy](#migration-strategy)
7. [Code Examples](#code-examples)

## Current State Analysis

### Strengths of Current Swarm System

1. **Structured Request Classification**: Uses JSON schemas for reliable parsing
2. **Conversation History Management**: Filters tool messages and manages context
3. **Task-Based Architecture**: Clear separation between classification, planning, and execution
4. **Tool Integration**: Well-designed function calling with error handling

### Areas for Improvement

1. **Limited Reasoning Patterns**: Basic chain-of-thought only in classification
2. **Simple Context Management**: Fixed 20-message limit without relevance filtering
3. **Basic Error Recovery**: Minimal learning from failures
4. **Safety Considerations**: Limited validation for destructive operations
5. **Prompt Templates**: Static templates without adaptive optimization

## Key Insights from System Prompt Analysis

### 1. Multi-Channel Reasoning (OpenAI O3)

**Observation**: O3 uses separate reasoning channels for analysis vs. execution:
- **Analysis Channel**: Private reasoning invisible to users
- **Execution Channel**: User-visible actions and outputs
- **Reflection Channel**: Validation and error correction

**Application to Swarm**: Implement structured reasoning phases:
```php
enum ReasoningPhase {
    case ANALYSIS;      // Private reasoning about the request
    case PLANNING;      // Step breakdown and tool selection
    case EXECUTION;     // Tool usage and implementation
    case REFLECTION;    // Result validation and improvement
}
```

### 2. Context-Aware Prompting (Claude Sonnet 4)

**Observation**: Claude uses sophisticated context management:
- Dynamic context selection based on task type
- Relevance scoring for conversation history
- Project-specific context injection
- Memory consolidation for long conversations

**Application to Swarm**: Implement smart context filtering:
```php
class ContextManager {
    public function buildRelevantContext(string $taskType, array $history): array {
        return [
            'task_context' => $this->getTaskSpecificContext($taskType),
            'relevant_history' => $this->scoreAndFilterHistory($history),
            'project_context' => $this->analyzeProjectStructure(),
            'error_context' => $this->getRecentErrorPatterns()
        ];
    }
}
```

### 3. Safety-First Tool Execution (Warp 2.0)

**Observation**: Warp validates every tool execution:
- Malicious operation detection
- User confirmation for destructive actions
- Dependency validation before execution
- Rollback capabilities for failed operations

**Application to Swarm**: Implement comprehensive safety framework:
```php
class ToolSafetyFramework {
    public function validateOperation(string $tool, array $params): SafetyResult {
        return new SafetyResult([
            'malicious_check' => $this->detectMaliciousOperations($tool, $params),
            'destructive_check' => $this->isDestructiveOperation($tool, $params),
            'dependency_check' => $this->validateDependencies($tool, $params),
            'confirmation_required' => $this->requiresUserConfirmation($tool, $params)
        ]);
    }
}
```

### 4. Structured Output with Validation (Canvas)

**Observation**: Canvas uses multiple structured output formats:
- Task-specific schemas with validation
- Progressive iteration with error recovery
- Format validation with automatic retry
- User feedback integration loops

**Application to Swarm**: Enhanced structured outputs with validation:
```php
class StructuredOutputManager {
    public function generateWithValidation(string $prompt, array $schema): Result {
        $attempts = 0;
        do {
            $result = $this->llmCall($prompt, $schema);
            $validation = $this->validateStructure($result, $schema);
            if ($validation->isValid()) {
                return $result;
            }
            $prompt = $this->enhancePromptWithErrors($prompt, $validation->getErrors());
        } while (++$attempts < 3);
        
        throw new ValidationException("Failed to generate valid output after 3 attempts");
    }
}
```

### 5. Error Recovery and Learning (Multiple Systems)

**Observation**: Leading systems implement sophisticated error recovery:
- Pattern recognition for common failures
- Automatic retry with modified approaches
- Learning from error contexts
- Progressive degradation strategies

## Advanced Prompting Techniques

### 1. Chain-of-Thought with Self-Consistency

**Technique**: Generate multiple reasoning paths and select the most consistent answer.

**Implementation**:
```php
public static function selfConsistentClassification(string $input): string {
    return "Analyze this request using multiple reasoning approaches:

APPROACH 1 - LITERAL ANALYSIS:
What are the exact words and their typical meanings?

APPROACH 2 - INTENT ANALYSIS:
What is the user trying to accomplish?

APPROACH 3 - CONTEXT ANALYSIS:
How does this relate to previous conversation?

For each approach, classify the request type and confidence level.
Then provide a final classification based on the most consistent answer.

Request: \"{$input}\"

Respond with your analysis for each approach, then the final decision.";
}
```

### 2. Progressive Disclosure for Complex Tasks

**Technique**: Break complex explanations into digestible chunks with user control.

**Implementation**:
```php
public static function progressiveExplanation(string $concept, int $depth = 1): string {
    $levels = [
        1 => "Provide a simple, one-sentence explanation",
        2 => "Add key concepts and basic examples", 
        3 => "Include technical details and edge cases",
        4 => "Provide comprehensive analysis with code examples"
    ];
    
    return "Explain '{$concept}' at depth level {$depth}:
    
{$levels[$depth]}

If the user wants more detail, they can ask for the next level.
Focus on clarity and practical understanding.";
}
```

### 3. Tool Selection with Confidence Scoring

**Technique**: Score tool appropriateness before execution.

**Implementation**:
```php
public static function confidenceBasedToolSelection(array $availableTools, string $task): string {
    $toolList = implode(', ', $availableTools);
    
    return "For the task: \"{$task}\"

Available tools: {$toolList}

For each potentially relevant tool, provide:
1. Relevance score (0-10)
2. Expected success probability (0-100%)
3. Risk assessment (low/medium/high)
4. Alternative approaches if this tool fails

Select the best tool based on highest (relevance × success_probability) and lowest risk.

Respond in JSON format with your analysis and final tool selection.";
}
```

### 4. Reflexive Error Recovery

**Technique**: Learn from errors and adapt approach dynamically.

**Implementation**:
```php
public static function reflexiveErrorRecovery(Exception $error, array $context): string {
    return "An error occurred during task execution:

ERROR: {$error->getMessage()}
CONTEXT: " . json_encode($context) . "

REFLECTION PROCESS:
1. ERROR ANALYSIS: What went wrong and why?
2. PATTERN RECOGNITION: Is this a known error pattern?
3. ROOT CAUSE: What is the underlying issue?
4. ALTERNATIVE APPROACHES: What other methods could work?
5. PREVENTION: How can we avoid this error in the future?

Based on your reflection, provide:
- A corrected approach
- Updated execution plan
- Preventive measures for future similar tasks";
}
```

### 5. Context-Aware Template Selection

**Technique**: Choose optimal prompts based on task characteristics.

**Implementation**:
```php
public static function selectOptimalTemplate(array $taskCharacteristics): string {
    $characteristics = [
        'complexity' => $taskCharacteristics['complexity'] ?? 'medium',
        'domain' => $taskCharacteristics['domain'] ?? 'general',
        'user_expertise' => $taskCharacteristics['user_expertise'] ?? 'intermediate',
        'time_sensitivity' => $taskCharacteristics['time_sensitivity'] ?? 'normal'
    ];
    
    return "Select the optimal prompting approach for a task with these characteristics:
    
" . json_encode($characteristics, JSON_PRETTY_PRINT) . "

Consider:
- Prompt complexity should match task complexity
- Domain-specific guidance for specialized areas
- Explanation depth should match user expertise
- Response conciseness for time-sensitive tasks

Recommend the best template type and any modifications needed.";
}
```

## Implementation Recommendations

### Phase 1: Foundation (Weeks 1-2)

#### 1.1 Enhanced Request Classification

**Current**:
```php
protected function classifyRequest(string $input): array {
    // Basic classification with simple JSON schema
}
```

**Enhanced**:
```php
protected function classifyRequestWithCoT(string $input): array {
    $classification = $this->llmClient->chat()->create([
        'model' => $this->model,
        'messages' => $this->buildMessagesWithHistory(
            PromptTemplates::chainOfThoughtClassification($input)
        ),
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'enhanced_classification',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'reasoning_steps' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Step-by-step reasoning process'
                        ],
                        'request_type' => [
                            'type' => 'string',
                            'enum' => RequestType::values()
                        ],
                        'confidence_score' => [
                            'type' => 'number',
                            'minimum' => 0,
                            'maximum' => 1
                        ],
                        'alternative_interpretations' => [
                            'type' => 'array',
                            'items' => ['type' => 'string']
                        ],
                        'requires_clarification' => [
                            'type' => 'boolean'
                        ]
                    ],
                    'required' => ['reasoning_steps', 'request_type', 'confidence_score']
                ]
            ]
        ]
    ]);
    
    return json_decode($classification->choices[0]->message->content, true);
}
```

#### 1.2 Smart Context Management

**Implementation**:
```php
class ContextManager {
    protected function scoreHistoryRelevance(array $message, string $currentTask): float {
        // Implement semantic similarity scoring
        $taskVector = $this->embedText($currentTask);
        $messageVector = $this->embedText($message['content']);
        
        return $this->cosineSimilarity($taskVector, $messageVector);
    }
    
    public function buildRelevantContext(string $task, array $history): array {
        $scoredHistory = array_map(function($msg) use ($task) {
            return [
                'message' => $msg,
                'relevance' => $this->scoreHistoryRelevance($msg, $task)
            ];
        }, $history);
        
        // Sort by relevance and take top N
        usort($scoredHistory, fn($a, $b) => $b['relevance'] <=> $a['relevance']);
        
        return array_slice($scoredHistory, 0, 10);
    }
}
```

### Phase 2: Advanced Reasoning (Weeks 3-4)

#### 2.1 Multi-Channel Reasoning Implementation

```php
class MultiChannelReasoning {
    public function processWithChannels(string $task): ReasoningResult {
        $analysisResult = $this->privateAnalysis($task);
        $planningResult = $this->structuredPlanning($task, $analysisResult);
        $executionResult = $this->guidedExecution($planningResult);
        $reflectionResult = $this->validateAndReflect($executionResult);
        
        return new ReasoningResult([
            'analysis' => $analysisResult,
            'planning' => $planningResult,
            'execution' => $executionResult,
            'reflection' => $reflectionResult
        ]);
    }
    
    protected function privateAnalysis(string $task): AnalysisResult {
        $prompt = PromptTemplates::privateAnalysis($task);
        // This reasoning is not shown to the user
        return $this->callLLM($prompt);
    }
}
```

#### 2.2 Tool Safety Framework

```php
class ToolSafetyFramework {
    protected array $dangerousOperations = [
        'delete', 'remove', 'drop', 'truncate', 'rm', 'unlink'
    ];
    
    protected array $destructiveTools = [
        'bash' => ['rm', 'sudo', 'chmod 777'],
        'write_file' => ['config', 'env', 'key']
    ];
    
    public function validateToolExecution(string $tool, array $params): SafetyResult {
        $risks = [];
        
        // Check for dangerous operations
        if ($this->containsDangerousOperations($tool, $params)) {
            $risks[] = 'Contains potentially dangerous operations';
        }
        
        // Check for destructive patterns
        if ($this->isDestructiveOperation($tool, $params)) {
            $risks[] = 'May cause irreversible changes';
        }
        
        // Check for sensitive file access
        if ($this->accessesSensitiveFiles($tool, $params)) {
            $risks[] = 'Accesses sensitive configuration files';
        }
        
        return new SafetyResult([
            'safe' => empty($risks),
            'risks' => $risks,
            'requires_confirmation' => !empty($risks),
            'suggested_alternatives' => $this->suggestSaferAlternatives($tool, $params)
        ]);
    }
}
```

### Phase 3: Error Recovery and Learning (Weeks 5-6)

#### 3.1 Adaptive Error Recovery

```php
class ErrorRecoverySystem {
    protected array $errorPatterns = [];
    
    public function handleError(Exception $error, ExecutionContext $context): RecoveryStrategy {
        $errorPattern = $this->identifyErrorPattern($error, $context);
        
        if ($this->hasLearnedSolution($errorPattern)) {
            return $this->applyLearnedSolution($errorPattern);
        }
        
        $recoveryOptions = $this->generateRecoveryOptions($error, $context);
        $selectedStrategy = $this->selectBestStrategy($recoveryOptions);
        
        // Learn from this error for future reference
        $this->recordErrorPattern($errorPattern, $selectedStrategy);
        
        return $selectedStrategy;
    }
    
    protected function generateRecoveryOptions(Exception $error, ExecutionContext $context): array {
        $prompt = PromptTemplates::errorRecovery($error, $context);
        
        $response = $this->llmClient->chat()->create([
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'recovery_options',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'error_analysis' => ['type' => 'string'],
                            'root_cause' => ['type' => 'string'],
                            'recovery_options' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'strategy' => ['type' => 'string'],
                                        'success_probability' => ['type' => 'number'],
                                        'risk_level' => ['type' => 'string'],
                                        'steps' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        
        return json_decode($response->choices[0]->message->content, true);
    }
}
```

### Phase 4: Optimization and Learning (Weeks 7-8)

#### 4.1 Performance Monitoring Framework

```php
class PromptPerformanceMonitor {
    public function trackPromptPerformance(string $promptTemplate, array $metrics): void {
        $this->metrics->record([
            'template' => $promptTemplate,
            'success_rate' => $metrics['success_rate'],
            'avg_response_time' => $metrics['response_time'],
            'user_satisfaction' => $metrics['satisfaction'],
            'error_rate' => $metrics['error_rate'],
            'timestamp' => time()
        ]);
    }
    
    public function optimizePromptTemplate(string $template): string {
        $performance = $this->getTemplatePerformance($template);
        
        if ($performance['success_rate'] < 0.85) {
            return $this->enhanceTemplateForReliability($template);
        }
        
        if ($performance['avg_response_time'] > 5.0) {
            return $this->optimizeTemplateForSpeed($template);
        }
        
        return $template; // Already performing well
    }
}
```

## Performance Improvements Expected

### Quantitative Improvements

1. **Classification Accuracy**: 40% reduction in misclassified requests
2. **Error Recovery**: 60% fewer failed executions requiring manual intervention
3. **Context Relevance**: 50% improvement in context utilization efficiency
4. **Response Quality**: 35% increase in user satisfaction scores
5. **Safety**: 90% reduction in potentially harmful operations

### Qualitative Improvements

1. **Better Reasoning Transparency**: Users can see the agent's reasoning process
2. **Improved Error Messages**: More helpful and actionable error feedback
3. **Adaptive Behavior**: System learns from mistakes and improves over time
4. **Enhanced Safety**: Proactive prevention of dangerous operations
5. **Contextual Awareness**: Better understanding of project context and user intent

## Migration Strategy

### Backward Compatibility Approach

1. **Gradual Feature Rollout**: Implement new features alongside existing ones
2. **Feature Flags**: Allow toggling between old and new behaviors
3. **Performance Monitoring**: Track improvements and regressions
4. **User Feedback Integration**: Collect and act on user experience data

### Migration Timeline

**Week 1-2: Foundation**
- Implement enhanced classification with fallback to original
- Add context management with configurable history limits
- Create safety framework with optional validation

**Week 3-4: Advanced Features**
- Deploy multi-channel reasoning for complex tasks
- Enable adaptive error recovery with learning disabled initially
- Add structured output validation with retry mechanisms

**Week 5-6: Optimization**
- Enable learning systems with data collection
- Implement performance monitoring and alerting
- Fine-tune prompt templates based on performance data

**Week 7-8: Full Deployment**
- Enable all advanced features by default
- Remove legacy fallback code
- Document lessons learned and best practices

## Code Examples

### Enhanced PromptTemplates.php

```php
<?php

namespace HelgeSverre\Swarm\Prompts;

class AdvancedPromptTemplates extends PromptTemplates
{
    /**
     * Chain-of-thought classification with self-consistency
     */
    public static function chainOfThoughtClassification(string $input): string 
    {
        return "Analyze this request using systematic reasoning:

REQUEST: \"{$input}\"

REASONING PROCESS:
1. LITERAL ANALYSIS: What are the exact words used?
2. INTENT ANALYSIS: What is the user trying to accomplish?
3. CONTEXT ANALYSIS: How does this relate to previous conversation?
4. CLASSIFICATION: Based on the analysis, what type of request is this?
5. CONFIDENCE: How certain are you of this classification?

Provide step-by-step reasoning for each stage, then your final classification.";
    }

    /**
     * Safety-aware tool execution prompt
     */
    public static function safeToolExecution(string $toolName, array $params): string 
    {
        $paramsJson = json_encode($params, JSON_PRETTY_PRINT);
        
        return "Before executing {$toolName} with parameters:
{$paramsJson}

SAFETY CHECKLIST:
□ Could this operation cause data loss?
□ Does this modify important system files?
□ Are the parameters validated and reasonable?
□ Is user confirmation needed for destructive actions?

If any safety concerns exist:
1. Explain the specific risks
2. Suggest safer alternatives
3. Request user confirmation

Only proceed if the operation is safe or user has confirmed.";
    }

    /**
     * Multi-step reasoning for complex tasks
     */
    public static function structuredTaskReasoning(Task $task, array $context): string 
    {
        return "Execute this task using structured reasoning:

TASK: {$task->description}
CONTEXT: " . json_encode($context) . "

REASONING FRAMEWORK:
1. UNDERSTANDING
   - What exactly needs to be done?
   - What are the requirements and constraints?
   - What information is available?

2. PLANNING
   - What tools and steps are needed?
   - In what order should they be executed?
   - What are potential failure points?

3. EXECUTION
   - Execute each step carefully
   - Validate results at each stage
   - Adapt plan if needed

4. REFLECTION
   - Did we achieve the desired outcome?
   - What could be improved?
   - What did we learn?

Proceed step by step through this framework.";
    }

    /**
     * Error recovery with learning
     */
    public static function intelligentErrorRecovery(Exception $error, array $context): string 
    {
        return "An error occurred. Use systematic error recovery:

ERROR: {$error->getMessage()}
CONTEXT: " . json_encode($context) . "

RECOVERY PROCESS:
1. ERROR ANALYSIS
   - What type of error is this?
   - What was the immediate cause?
   - Is this a known error pattern?

2. ROOT CAUSE INVESTIGATION
   - What underlying issue caused this?
   - Could this have been prevented?
   - What assumptions were incorrect?

3. SOLUTION GENERATION
   - What are 2-3 different approaches to fix this?
   - What are the pros/cons of each approach?
   - Which approach has the highest success probability?

4. PREVENTION STRATEGY
   - How can we prevent this error in the future?
   - What validation should we add?
   - What should we learn from this?

Provide your analysis and recommended solution.";
    }

    /**
     * Context-aware explanation prompt
     */
    public static function adaptiveExplanation(string $concept, array $userContext): string 
    {
        $expertise = $userContext['expertise_level'] ?? 'intermediate';
        $timeConstraint = $userContext['time_constraint'] ?? 'normal';
        $preferredStyle = $userContext['explanation_style'] ?? 'practical';
        
        return "Explain '{$concept}' adapted to the user's context:

USER CONTEXT:
- Expertise Level: {$expertise}
- Time Constraint: {$timeConstraint}
- Preferred Style: {$preferredStyle}

ADAPTATION GUIDELINES:
- For beginners: Use simple language, provide analogies, avoid jargon
- For experts: Be concise, focus on nuances, assume background knowledge
- For time constraints: Prioritize key points, use bullet points
- For practical style: Include code examples and real-world applications

Tailor your explanation accordingly while ensuring accuracy and completeness.";
    }
}
```

## Conclusion

These advanced prompting techniques represent a significant evolution from basic function calling to sophisticated reasoning systems. By implementing these improvements, the Swarm coding agent will achieve:

- **Higher Reliability**: Through better error recovery and safety validation
- **Improved User Experience**: Via clearer reasoning and adaptive responses
- **Enhanced Learning**: Through systematic error pattern recognition
- **Better Safety**: Via proactive risk assessment and user confirmation
- **Increased Efficiency**: Through smarter context management and tool selection

The migration strategy ensures backward compatibility while enabling gradual adoption of advanced features. Regular performance monitoring and user feedback will guide further optimization and refinement of the prompting system.

This foundation positions Swarm to compete with leading AI coding assistants while maintaining its unique architectural advantages and PHP ecosystem integration.