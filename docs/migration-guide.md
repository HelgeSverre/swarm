# Migration Guide: Implementing Advanced Prompting Techniques

## Overview

This guide provides a step-by-step migration path for upgrading the Swarm coding agent with advanced prompting techniques. The migration is designed to be incremental, maintaining backward compatibility while adding sophisticated reasoning capabilities.

## Migration Timeline

```
Phase 1 (Weeks 1-2): Foundation
├── Enhanced Classification
├── Safety Framework
└── Context Management

Phase 2 (Weeks 3-4): Reasoning
├── Multi-Channel Reasoning
├── Self-Consistency
└── Error Recovery

Phase 3 (Weeks 5-6): Advanced Features
├── Agent Specialization
├── Performance Optimization
└── Production Readiness

Phase 4 (Weeks 7-8): Deployment
├── Security Hardening
├── Monitoring Setup
└── Documentation & Training
```

## Pre-Migration Checklist

### Prerequisites
- [ ] PHP 8.3+ with all required extensions
- [ ] Current Swarm agent running and tested
- [ ] Database backup completed
- [ ] Test environment configured
- [ ] Performance baseline established

### Risk Assessment
- [ ] Identify critical system dependencies
- [ ] Document current performance metrics
- [ ] Plan rollback procedures
- [ ] Set up monitoring and alerting
- [ ] Prepare staged deployment strategy

---

## Phase 1: Foundation (Weeks 1-2)

### Step 1.1: Enhanced Request Classification

**Objective**: Add chain-of-thought reasoning to request classification.

**Files to Modify**:
- `src/Prompts/PromptTemplates.php`
- `src/Agent/CodingAgent.php`

**Implementation**:

1. **Add new classification template**:

```php
// In src/Prompts/PromptTemplates.php

/**
 * Chain-of-thought classification with reasoning transparency
 */
public static function chainOfThoughtClassification(string $input): string 
{
    return "Analyze this request using systematic reasoning:

REQUEST: \"{$input}\"

REASONING PROCESS:
1. LITERAL ANALYSIS
   - What are the exact words and phrases used?
   - Are there any action verbs that indicate intent?
   - What technical terms or concepts are mentioned?

2. INTENT ANALYSIS  
   - What is the user ultimately trying to accomplish?
   - Is this a request for information or action?
   - What level of complexity is implied?

3. CONTEXT ANALYSIS
   - How does this relate to previous conversation?
   - What domain expertise might be required?
   - Are there implicit requirements or assumptions?

4. CLASSIFICATION DECISION
   - Based on the analysis above, what type of request is this?
   - How confident are you in this classification?
   - What alternative interpretations are possible?

Provide your step-by-step reasoning, then the final classification.";
}
```

2. **Update classification schema**:

```php
// Enhanced JSON schema in CodingAgent.php

'json_schema' => [
    'name' => 'enhanced_classification',
    'schema' => [
        'type' => 'object',
        'properties' => [
            'reasoning_steps' => [
                'type' => 'object',
                'properties' => [
                    'literal_analysis' => ['type' => 'string'],
                    'intent_analysis' => ['type' => 'string'],
                    'context_analysis' => ['type' => 'string'],
                    'classification_reasoning' => ['type' => 'string']
                ],
                'required' => ['literal_analysis', 'intent_analysis', 'context_analysis', 'classification_reasoning']
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
        'required' => ['reasoning_steps', 'request_type', 'confidence_score'],
        'additionalProperties' => false
    ]
]
```

3. **Add feature flag for gradual rollout**:

```php
// In CodingAgent.php constructor
private bool $useEnhancedClassification = true; // Feature flag

protected function classifyRequest(string $input): array
{
    if ($this->useEnhancedClassification) {
        return $this->classifyRequestWithCoT($input);
    }
    
    // Fallback to original implementation
    return $this->originalClassifyRequest($input);
}
```

**Testing Strategy**:
```bash
# Run classification tests
composer test -- --filter=ClassificationTest

# A/B test with sample requests
php tests/PromptTesting/ClassificationABTest.php
```

### Step 1.2: Basic Safety Framework

**Objective**: Implement safety validation for tool execution.

**New Files**:
- `src/Core/ToolSafetyFramework.php`
- `src/Core/RiskAssessment.php`
- `src/Enums/RiskLevel.php`

**Implementation**:

1. **Create RiskLevel enum**:

```php
<?php
// src/Enums/RiskLevel.php

namespace HelgeSverre\Swarm\Enums;

enum RiskLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}
```

2. **Create safety framework**:

```php
<?php
// src/Core/ToolSafetyFramework.php

namespace HelgeSverre\Swarm\Core;

use HelgeSverre\Swarm\Enums\RiskLevel;

class ToolSafetyFramework
{
    protected array $dangerousPatterns = [
        'bash' => [
            'rm -rf', 'sudo', 'chmod 777', 'dd if=', 'mkfs',
            'format', '> /dev/', 'curl', 'wget'
        ],
        'write_file' => [
            '.env', 'config.php', 'database.php', '.key', 
            '.secret', 'passwd', 'shadow'
        ]
    ];
    
    protected array $destructiveOperations = [
        'delete', 'remove', 'drop', 'truncate', 'destroy'
    ];
    
    public function assessRisk(string $tool, array $params): RiskAssessment
    {
        $riskLevel = $this->calculateRiskLevel($tool, $params);
        $warnings = $this->identifyWarnings($tool, $params);
        $alternatives = $this->suggestSaferAlternatives($tool, $params);
        
        return new RiskAssessment([
            'tool' => $tool,
            'parameters' => $params,
            'risk_level' => $riskLevel,
            'warnings' => $warnings,
            'alternatives' => $alternatives,
            'requires_confirmation' => $riskLevel->value >= RiskLevel::MEDIUM->value,
            'reasoning' => $this->explainRiskAssessment($tool, $params, $riskLevel)
        ]);
    }
    
    protected function calculateRiskLevel(string $tool, array $params): RiskLevel
    {
        // Check for dangerous patterns
        if (isset($this->dangerousPatterns[$tool])) {
            foreach ($this->dangerousPatterns[$tool] as $pattern) {
                if ($this->containsPattern($params, $pattern)) {
                    return RiskLevel::HIGH;
                }
            }
        }
        
        // Check for destructive operations
        foreach ($this->destructiveOperations as $operation) {
            if ($this->containsPattern($params, $operation)) {
                return RiskLevel::MEDIUM;
            }
        }
        
        // Check for system file access
        if ($this->accessesSystemFiles($tool, $params)) {
            return RiskLevel::MEDIUM;
        }
        
        return RiskLevel::LOW;
    }
    
    protected function containsPattern(array $params, string $pattern): bool
    {
        $searchString = strtolower(json_encode($params));
        return str_contains($searchString, strtolower($pattern));
    }
}
```

3. **Integrate with tool execution**:

```php
// In CodingAgent.php executeTask method

protected function executeTask(Task $task): void
{
    // ... existing code ...
    
    if ($toolCall) {
        // Add safety assessment
        $safetyAssessment = $this->toolSafetyFramework->assessRisk(
            $toolCall['name'], 
            $toolCall['arguments']
        );
        
        if ($safetyAssessment->requires_confirmation) {
            $this->reportProgress('safety_warning', [
                'message' => 'Operation requires safety review',
                'risk_level' => $safetyAssessment->risk_level->value,
                'warnings' => $safetyAssessment->warnings,
                'alternatives' => $safetyAssessment->alternatives
            ]);
            
            // In production, this would prompt user for confirmation
            // For now, we'll log and proceed with caution
            $this->logger?->warning('High-risk operation detected', [
                'tool' => $toolCall['name'],
                'params' => $toolCall['arguments'],
                'risk_assessment' => $safetyAssessment->toArray()
            ]);
        }
        
        // Continue with existing tool execution...
    }
}
```

**Testing Strategy**:
```bash
# Test safety framework
composer test -- --filter=SafetyTest

# Test with dangerous operations
php tests/Safety/DangerousOperationTest.php
```

### Step 1.3: Enhanced Context Management

**Objective**: Implement smart context selection for better relevance.

**New Files**:
- `src/Core/ContextManager.php`
- `src/Core/RelevanceScorer.php`

**Implementation**:

1. **Create context manager**:

```php
<?php
// src/Core/ContextManager.php

namespace HelgeSverre\Swarm\Core;

class ContextManager
{
    protected RelevanceScorer $relevanceScorer;
    protected int $maxContextTokens;
    
    public function __construct(RelevanceScorer $relevanceScorer, int $maxContextTokens = 4000)
    {
        $this->relevanceScorer = $relevanceScorer;
        $this->maxContextTokens = $maxContextTokens;
    }
    
    public function buildOptimalContext(string $currentTask, array $fullHistory): array
    {
        // 1. Score all messages for relevance
        $scoredHistory = $this->scoreHistoryRelevance($fullHistory, $currentTask);
        
        // 2. Select messages within token budget
        $selectedMessages = $this->selectWithinBudget($scoredHistory);
        
        // 3. Add project context
        $projectContext = $this->analyzeProjectStructure();
        
        // 4. Include recent errors for learning
        $errorContext = $this->extractRecentErrors($fullHistory);
        
        return [
            'conversation_history' => $selectedMessages,
            'project_context' => $projectContext,
            'error_context' => $errorContext,
            'context_stats' => [
                'total_messages' => count($fullHistory),
                'selected_messages' => count($selectedMessages),
                'estimated_tokens' => $this->estimateTokenCount($selectedMessages)
            ]
        ];
    }
    
    protected function scoreHistoryRelevance(array $history, string $currentTask): array
    {
        return array_map(function($message) use ($currentTask) {
            $relevanceScore = $this->relevanceScorer->calculateSimilarity(
                $message['content'], 
                $currentTask
            );
            
            // Apply recency boost (more recent = higher score)
            $recencyBoost = $this->calculateRecencyBoost($message['timestamp']);
            
            // Apply role boost (errors and tool results are valuable for learning)
            $roleBoost = $this->calculateRoleBoost($message['role']);
            
            return [
                'message' => $message,
                'relevance_score' => $relevanceScore,
                'recency_boost' => $recencyBoost,
                'role_boost' => $roleBoost,
                'total_score' => $relevanceScore + $recencyBoost + $roleBoost
            ];
        }, $history);
    }
    
    protected function selectWithinBudget(array $scoredHistory): array
    {
        // Sort by total score (highest first)
        usort($scoredHistory, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        
        $selected = [];
        $currentTokens = 0;
        
        foreach ($scoredHistory as $scoredMessage) {
            $messageTokens = $this->estimateMessageTokens($scoredMessage['message']);
            
            if ($currentTokens + $messageTokens <= $this->maxContextTokens) {
                $selected[] = $scoredMessage['message'];
                $currentTokens += $messageTokens;
            }
        }
        
        // Sort selected messages chronologically for context
        usort($selected, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        
        return $selected;
    }
}
```

2. **Create relevance scorer**:

```php
<?php
// src/Core/RelevanceScorer.php

namespace HelgeSverre\Swarm\Core;

class RelevanceScorer
{
    protected array $keywordWeights = [
        'high' => ['error', 'bug', 'fix', 'issue', 'problem', 'fail'],
        'medium' => ['create', 'implement', 'build', 'generate', 'code'],
        'low' => ['hello', 'thanks', 'please', 'help']
    ];
    
    public function calculateSimilarity(string $text1, string $text2): float
    {
        // Simple keyword-based similarity (can be enhanced with embeddings later)
        $keywords1 = $this->extractKeywords($text1);
        $keywords2 = $this->extractKeywords($text2);
        
        $intersection = array_intersect($keywords1, $keywords2);
        $union = array_unique(array_merge($keywords1, $keywords2));
        
        if (empty($union)) {
            return 0.0;
        }
        
        // Jaccard similarity with keyword weighting
        $weightedIntersection = $this->applyKeywordWeights($intersection);
        $weightedUnion = $this->applyKeywordWeights($union);
        
        return $weightedIntersection / $weightedUnion;
    }
    
    protected function extractKeywords(string $text): array
    {
        // Simple keyword extraction (tokenize and filter)
        $words = preg_split('/\W+/', strtolower($text));
        
        // Remove stop words and short words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        
        return array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });
    }
    
    protected function applyKeywordWeights(array $keywords): float
    {
        $totalWeight = 0;
        
        foreach ($keywords as $keyword) {
            if (in_array($keyword, $this->keywordWeights['high'])) {
                $totalWeight += 3.0;
            } elseif (in_array($keyword, $this->keywordWeights['medium'])) {
                $totalWeight += 2.0;
            } elseif (in_array($keyword, $this->keywordWeights['low'])) {
                $totalWeight += 0.5;
            } else {
                $totalWeight += 1.0;
            }
        }
        
        return $totalWeight;
    }
}
```

3. **Integrate with CodingAgent**:

```php
// In CodingAgent.php
protected ContextManager $contextManager;

public function __construct(
    // ... existing parameters ...
    ContextManager $contextManager = null
) {
    // ... existing assignments ...
    $this->contextManager = $contextManager ?? new ContextManager(new RelevanceScorer());
}

protected function buildMessagesWithHistory(string $currentPrompt, ?string $systemPrompt = null): array
{
    if ($systemPrompt === null) {
        $systemPrompt = PromptTemplates::defaultSystem($this->toolExecutor->getRegisteredTools());
    }

    // Use enhanced context management
    $contextData = $this->contextManager->buildOptimalContext($currentPrompt, $this->conversationHistory);
    
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
    ];
    
    // Add project context if available
    if (!empty($contextData['project_context'])) {
        $messages[] = [
            'role' => 'system', 
            'content' => 'Project Context: ' . json_encode($contextData['project_context'])
        ];
    }
    
    // Add optimally selected conversation history
    foreach ($contextData['conversation_history'] as $msg) {
        if (!in_array($msg['role'], ['tool', 'error'])) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }
    }
    
    // Add current user message
    $messages[] = ['role' => 'user', 'content' => $currentPrompt];
    
    // Log context optimization stats
    $this->logger?->debug('Context optimization', $contextData['context_stats']);
    
    return $messages;
}
```

**Testing Strategy**:
```bash
# Test context management
composer test -- --filter=ContextTest

# Performance test with long conversations
php tests/Performance/LongConversationTest.php
```

### Phase 1 Validation

**Success Criteria**:
- [ ] Classification accuracy improved by 20%+
- [ ] Safety framework blocks dangerous operations
- [ ] Context selection reduces irrelevant information by 40%+
- [ ] No regression in existing functionality
- [ ] Performance within acceptable limits

**Rollback Plan**:
If any issues are detected:
1. Set feature flags to false
2. Revert to original implementations
3. Investigate and fix issues
4. Re-enable features gradually

---

## Phase 2: Advanced Reasoning (Weeks 3-4)

### Step 2.1: Multi-Channel Reasoning

**Objective**: Implement separate analysis, planning, execution, and reflection channels.

**New Files**:
- `src/Enums/ReasoningPhase.php`
- `src/Core/MultiChannelReasoning.php`
- `src/ValueObjects/ReasoningResult.php`

**Implementation**:

1. **Create reasoning phases**:

```php
<?php
// src/Enums/ReasoningPhase.php

namespace HelgeSverre\Swarm\Enums;

enum ReasoningPhase: string
{
    case ANALYSIS = 'analysis';
    case PLANNING = 'planning';  
    case EXECUTION = 'execution';
    case REFLECTION = 'reflection';
}
```

2. **Implement multi-channel reasoning**:

```php
<?php
// src/Core/MultiChannelReasoning.php

namespace HelgeSverre\Swarm\Core;

use HelgeSverre\Swarm\Enums\ReasoningPhase;
use HelgeSverre\Swarm\Task\Task;
use HelgeSverre\Swarm\Prompts\PromptTemplates;

class MultiChannelReasoning
{
    protected $llmClient;
    protected $logger;
    
    public function processTask(Task $task, array $context): ReasoningResult
    {
        $result = new ReasoningResult(['task' => $task]);
        
        try {
            // Phase 1: Private Analysis (internal reasoning)
            $result->analysis = $this->privateAnalysis($task, $context);
            
            // Phase 2: Structured Planning (user-visible)
            $result->planning = $this->structuredPlanning($task, $result->analysis);
            
            // Phase 3: Guided Execution (tool usage)
            $result->execution = $this->guidedExecution($task, $result->planning);
            
            // Phase 4: Reflection and Validation
            $result->reflection = $this->validateAndReflect($result);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger?->error('Multi-channel reasoning failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'phase' => $result->getCurrentPhase()
            ]);
            
            throw $e;
        }
    }
    
    protected function privateAnalysis(Task $task, array $context): AnalysisResult
    {
        $prompt = PromptTemplates::privateAnalysis($task, $context);
        
        $response = $this->llmClient->chat()->create([
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'private_analysis',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'complexity_assessment' => [
                                'type' => 'object',
                                'properties' => [
                                    'complexity_score' => ['type' => 'number', 'minimum' => 1, 'maximum' => 10],
                                    'key_challenges' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'required_expertise' => ['type' => 'array', 'items' => ['type' => 'string']]
                                ]
                            ],
                            'requirement_analysis' => [
                                'type' => 'object',
                                'properties' => [
                                    'explicit_requirements' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'implicit_requirements' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'constraints' => ['type' => 'array', 'items' => ['type' => 'string']]
                                ]
                            ],
                            'approach_evaluation' => [
                                'type' => 'object',
                                'properties' => [
                                    'possible_approaches' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'name' => ['type' => 'string'],
                                                'description' => ['type' => 'string'],
                                                'pros' => ['type' => 'array', 'items' => ['type' => 'string']],
                                                'cons' => ['type' => 'array', 'items' => ['type' => 'string']],
                                                'success_probability' => ['type' => 'number']
                                            ]
                                        ]
                                    ],
                                    'recommended_approach' => ['type' => 'string'],
                                    'reasoning' => ['type' => 'string']
                                ]
                            ],
                            'risk_identification' => [
                                'type' => 'object',
                                'properties' => [
                                    'potential_failures' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'dependencies' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'validation_needs' => ['type' => 'array', 'items' => ['type' => 'string']]
                                ]
                            ]
                        ],
                        'required' => ['complexity_assessment', 'requirement_analysis', 'approach_evaluation', 'risk_identification']
                    ]
                ]
            ]
        ]);
        
        return new AnalysisResult(json_decode($response->choices[0]->message->content, true));
    }
}
```

3. **Add multi-channel prompts**:

```php
// In PromptTemplates.php

/**
 * Private analysis prompt for internal reasoning
 */
public static function privateAnalysis(Task $task, array $context): string
{
    return "PRIVATE ANALYSIS (Internal reasoning - not shown to user):

TASK: {$task->description}
CONTEXT: " . json_encode($context, JSON_PRETTY_PRINT) . "

SYSTEMATIC ANALYSIS FRAMEWORK:

1. COMPLEXITY ASSESSMENT
   - Rate complexity (1-10): Consider technical difficulty, scope, dependencies
   - Identify key challenges: What are the main obstacles?
   - Required expertise: What skills/knowledge are needed?

2. REQUIREMENT ANALYSIS
   - Explicit requirements: What the user directly stated
   - Implicit requirements: What they likely expect but didn't mention
   - Constraints: Technical, business, or resource limitations

3. APPROACH EVALUATION
   - Generate 3 different approaches to solve this
   - For each: pros, cons, success probability
   - Select the best approach with reasoning

4. RISK IDENTIFICATION
   - What could go wrong during execution?
   - What dependencies might cause issues?
   - What validation steps are critical?

This analysis is for internal planning only. Provide comprehensive analysis.";
}

/**
 * Structured planning prompt for user-visible planning
 */
public static function structuredPlanning(Task $task, AnalysisResult $analysis): string
{
    return "TASK PLANNING (User-visible output):

TASK: {$task->description}

Based on internal analysis, create a clear execution plan:

PLANNING FRAMEWORK:
1. APPROACH SUMMARY
   - Briefly explain the chosen approach
   - Highlight key benefits and considerations

2. EXECUTION STEPS
   - Break down into clear, actionable steps
   - Specify tools needed for each step
   - Estimate complexity/time for each step

3. SUCCESS CRITERIA
   - How will we know the task is complete?
   - What validation steps are needed?
   - What are the expected outcomes?

4. CONTINGENCY PLANNING
   - What are potential issues?
   - How will we handle unexpected problems?
   - What are alternative approaches if needed?

Provide a clear, structured plan that builds user confidence.";
}
```

**Testing Strategy**:
```bash
# Test multi-channel reasoning
composer test -- --filter=MultiChannelTest

# Integration test with complex tasks
php tests/Integration/ComplexTaskTest.php
```

### Step 2.2: Self-Consistency Framework

**Objective**: Generate multiple reasoning paths and select the most consistent answer.

**New Files**:
- `src/Core/SelfConsistencyFramework.php`
- `src/ValueObjects/ConsistentResult.php`

**Implementation**:

```php
<?php
// src/Core/SelfConsistencyFramework.php

namespace HelgeSverre\Swarm\Core;

class SelfConsistencyFramework
{
    protected $llmClient;
    protected int $defaultAttempts = 3;
    
    public function generateConsistentSolution(string $problem, int $attempts = null): ConsistentResult
    {
        $attempts = $attempts ?? $this->defaultAttempts;
        $solutions = [];
        
        // Generate multiple reasoning paths
        for ($i = 0; $i < $attempts; $i++) {
            $solution = $this->generateSingleSolution($problem, $i);
            $solutions[] = $solution;
        }
        
        // Analyze consistency and select best answer
        return $this->selectMostConsistent($solutions);
    }
    
    protected function generateSingleSolution(string $problem, int $attempt): ReasoningSolution
    {
        $approaches = [
            0 => "Approach 1 - Component Breakdown: Break the problem into smallest possible components and solve each independently.",
            1 => "Approach 2 - User Perspective: Consider the problem from the end user's perspective and work backwards.",
            2 => "Approach 3 - Edge Case Analysis: Start by identifying edge cases and potential failures, then build a robust solution."
        ];
        
        $approach = $approaches[$attempt] ?? $approaches[0];
        
        $prompt = "REASONING ATTEMPT #{$attempt + 1}:

PROBLEM: {$problem}

REASONING APPROACH:
{$approach}

Using this specific approach, think through the problem step by step:

1. PROBLEM UNDERSTANDING
   - What exactly needs to be solved?
   - What are the key requirements?

2. APPROACH APPLICATION
   - How does your assigned approach apply here?
   - What does this perspective reveal?

3. SOLUTION DEVELOPMENT
   - What is your step-by-step solution?
   - What tools/techniques will you use?

4. VALIDATION
   - How confident are you in this solution?
   - What could go wrong?

Provide your complete reasoning and final solution.";

        $response = $this->llmClient->chat()->create([
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'reasoning_solution',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'approach_used' => ['type' => 'string'],
                            'problem_understanding' => ['type' => 'string'],
                            'reasoning_steps' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'solution' => ['type' => 'string'],
                            'confidence_score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'potential_issues' => ['type' => 'array', 'items' => ['type' => 'string']]
                        ],
                        'required' => ['approach_used', 'problem_understanding', 'reasoning_steps', 'solution', 'confidence_score']
                    ]
                ]
            ]
        ]);
        
        $data = json_decode($response->choices[0]->message->content, true);
        
        return new ReasoningSolution([
            'attempt' => $attempt,
            'approach' => $data['approach_used'],
            'reasoning' => $data['reasoning_steps'],
            'solution' => $data['solution'],
            'confidence' => $data['confidence_score'],
            'potential_issues' => $data['potential_issues'] ?? []
        ]);
    }
    
    protected function selectMostConsistent(array $solutions): ConsistentResult
    {
        // Calculate consistency scores between solutions
        $consistencyMatrix = $this->calculateConsistencyMatrix($solutions);
        
        // Find the solution with highest average consistency
        $consistencyScores = array_map(function($i) use ($consistencyMatrix) {
            $scores = array_column($consistencyMatrix[$i], 'score');
            return array_sum($scores) / count($scores);
        }, array_keys($solutions));
        
        $bestIndex = array_keys($consistencyScores, max($consistencyScores))[0];
        $bestSolution = $solutions[$bestIndex];
        
        // Extract consensus points
        $consensus = $this->extractConsensus($solutions);
        
        return new ConsistentResult([
            'selected_solution' => $bestSolution,
            'all_solutions' => $solutions,
            'consistency_scores' => $consistencyScores,
            'best_consistency_score' => max($consistencyScores),
            'consensus_points' => $consensus,
            'agreement_level' => $this->calculateAgreementLevel($solutions)
        ]);
    }
    
    protected function calculateConsistencyMatrix(array $solutions): array
    {
        $matrix = [];
        
        for ($i = 0; $i < count($solutions); $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < count($solutions); $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = ['score' => 1.0, 'reasoning' => 'same solution'];
                } else {
                    $matrix[$i][$j] = $this->compareSolutions($solutions[$i], $solutions[$j]);
                }
            }
        }
        
        return $matrix;
    }
    
    protected function compareSolutions(ReasoningSolution $solution1, ReasoningSolution $solution2): array
    {
        // Simple similarity comparison (can be enhanced with more sophisticated methods)
        $similarity = $this->calculateTextSimilarity($solution1->solution, $solution2->solution);
        
        return [
            'score' => $similarity,
            'reasoning' => "Solutions have {$similarity}% similarity"
        ];
    }
}
```

### Phase 2 Validation

**Success Criteria**:
- [ ] Multi-channel reasoning provides clearer task breakdown
- [ ] Self-consistency improves solution quality by 25%+
- [ ] Error recovery reduces failed executions by 50%+
- [ ] User feedback indicates improved transparency
- [ ] Performance remains within acceptable limits

---

## Phase 3: Production Optimization (Weeks 5-6)

### Step 3.1: Performance Monitoring

**Objective**: Implement comprehensive monitoring for production deployment.

**New Files**:
- `src/Monitoring/PromptPerformanceMonitor.php`
- `src/Monitoring/MetricsCollector.php`

**Implementation**:

```php
<?php
// src/Monitoring/PromptPerformanceMonitor.php

namespace HelgeSverre\Swarm\Monitoring;

class PromptPerformanceMonitor
{
    protected MetricsCollector $metrics;
    protected array $thresholds;
    
    public function __construct(MetricsCollector $metrics)
    {
        $this->metrics = $metrics;
        $this->thresholds = [
            'response_time' => 5.0,     // seconds
            'success_rate' => 0.85,     // 85%
            'error_rate' => 0.10,       // 10%
            'token_efficiency' => 0.80   // 80%
        ];
    }
    
    public function trackExecution(PromptExecution $execution): void
    {
        $this->metrics->record([
            'template' => $execution->template,
            'phase' => $execution->phase,
            'response_time' => $execution->responseTime,
            'success' => $execution->success,
            'error_type' => $execution->errorType,
            'token_usage' => $execution->tokenUsage,
            'user_satisfaction' => $execution->userFeedback,
            'timestamp' => microtime(true)
        ]);
        
        // Real-time threshold checking
        $this->checkThresholds($execution);
    }
    
    public function generateDailyReport(): PerformanceReport
    {
        $data = $this->metrics->query('24h');
        
        return new PerformanceReport([
            'period' => '24h',
            'total_executions' => count($data),
            'success_rate' => $this->calculateSuccessRate($data),
            'avg_response_time' => $this->calculateAverageResponseTime($data),
            'error_distribution' => $this->analyzeErrorTypes($data),
            'top_performing_templates' => $this->identifyTopPerformers($data),
            'bottlenecks' => $this->identifyBottlenecks($data),
            'recommendations' => $this->generateRecommendations($data)
        ]);
    }
    
    protected function checkThresholds(PromptExecution $execution): void
    {
        $alerts = [];
        
        if ($execution->responseTime > $this->thresholds['response_time']) {
            $alerts[] = "Slow response time: {$execution->responseTime}s";
        }
        
        if (!$execution->success) {
            $alerts[] = "Execution failed: {$execution->errorType}";
        }
        
        if (!empty($alerts)) {
            $this->triggerAlert($execution, $alerts);
        }
    }
}
```

### Step 3.2: Security Hardening

**Objective**: Implement production-grade security measures.

**Enhanced Files**:
- `src/Core/ToolSafetyFramework.php` (add more sophisticated checks)
- `src/Security/SecurityValidator.php` (new)

**Implementation**:

```php
<?php
// src/Security/SecurityValidator.php

namespace HelgeSverre\Swarm\Security;

class SecurityValidator
{
    protected array $maliciousPatterns = [
        // Code injection
        '/eval\s*\(/i',
        '/exec\s*\(/i', 
        '/system\s*\(/i',
        '/shell_exec\s*\(/i',
        
        // File system attacks
        '/\.\.\//',
        '/\.\.\\\\/',
        '/\/etc\/passwd/',
        '/\/etc\/shadow/',
        
        // Network attacks
        '/curl\s+.*\|\s*sh/i',
        '/wget\s+.*\|\s*sh/i',
        
        // SQL injection
        '/union\s+select/i',
        '/drop\s+table/i',
        '/delete\s+from/i',
        
        // XSS patterns
        '/<script/i',
        '/javascript:/i',
        '/on\w+\s*=/i'
    ];
    
    public function validateRequest(AgentRequest $request): SecurityValidation
    {
        $validations = [
            'malicious_content' => $this->scanForMaliciousContent($request),
            'input_sanitization' => $this->validateInputSanitization($request),
            'rate_limiting' => $this->checkRateLimit($request),
            'authentication' => $this->validateAuthentication($request),
            'authorization' => $this->checkAuthorization($request)
        ];
        
        $riskLevel = $this->calculateRiskLevel($validations);
        $allPassed = array_reduce($validations, fn($carry, $v) => $carry && $v->passed, true);
        
        if (!$allPassed || $riskLevel === RiskLevel::HIGH) {
            $this->logSecurityIncident($request, $validations, $riskLevel);
        }
        
        return new SecurityValidation([
            'passed' => $allPassed && $riskLevel !== RiskLevel::HIGH,
            'validations' => $validations,
            'risk_level' => $riskLevel,
            'recommendations' => $this->generateSecurityRecommendations($validations)
        ]);
    }
    
    protected function scanForMaliciousContent(AgentRequest $request): ValidationResult
    {
        $content = $request->input . ' ' . json_encode($request->parameters);
        $detectedPatterns = [];
        
        foreach ($this->maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $detectedPatterns[] = $pattern;
            }
        }
        
        if (!empty($detectedPatterns)) {
            return new ValidationResult([
                'passed' => false,
                'reason' => 'Malicious patterns detected',
                'details' => $detectedPatterns,
                'risk_level' => RiskLevel::HIGH
            ]);
        }
        
        return new ValidationResult(['passed' => true]);
    }
}
```

### Phase 3 Validation

**Success Criteria**:
- [ ] Performance monitoring captures all metrics
- [ ] Security validation blocks 100% of test attacks
- [ ] System handles production load without degradation
- [ ] Monitoring alerts work correctly
- [ ] All security scans pass

---

## Phase 4: Deployment and Monitoring (Weeks 7-8)

### Step 4.1: Production Deployment

**Deployment Checklist**:

```bash
# Pre-deployment checks
□ All tests passing
□ Security scans completed
□ Performance benchmarks met
□ Database migrations ready
□ Configuration validated
□ Monitoring configured
□ Rollback plan prepared

# Deployment steps
1. Deploy to staging
2. Run full test suite
3. Performance testing
4. Security validation
5. User acceptance testing
6. Deploy to production
7. Monitor for 24 hours
8. Gradual feature enablement
```

**Deployment Script**:

```bash
#!/bin/bash
# deploy.sh

set -e

ENVIRONMENT=${1:-staging}
VERSION=${2:-latest}

echo "Deploying Swarm Agent v${VERSION} to ${ENVIRONMENT}"

# Pre-deployment validation
echo "Running pre-deployment checks..."
composer test
php artisan config:cache
php artisan route:cache

# Security scan
echo "Running security scan..."
./vendor/bin/psalm --security-analysis

# Deploy code
echo "Deploying code..."
git checkout $VERSION
composer install --no-dev --optimize-autoloader

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Update configuration
echo "Updating configuration..."
php artisan config:cache
php artisan view:cache

# Health check
echo "Running health check..."
php artisan health:check

# Enable features gradually
if [ "$ENVIRONMENT" = "production" ]; then
    echo "Enabling features gradually..."
    php artisan feature:enable enhanced_classification --percentage=10
    sleep 300  # Wait 5 minutes
    php artisan feature:enable enhanced_classification --percentage=50
    sleep 300  # Wait 5 minutes  
    php artisan feature:enable enhanced_classification --percentage=100
fi

echo "Deployment completed successfully!"
```

### Step 4.2: Post-Deployment Monitoring

**Monitoring Dashboard Setup**:

```php
// config/monitoring.php

return [
    'metrics' => [
        'prompt_performance' => [
            'response_time' => ['threshold' => 5.0, 'alert' => true],
            'success_rate' => ['threshold' => 0.85, 'alert' => true],
            'error_rate' => ['threshold' => 0.10, 'alert' => true]
        ],
        'security' => [
            'blocked_requests' => ['threshold' => 10, 'alert' => true],
            'failed_authentications' => ['threshold' => 5, 'alert' => true]
        ],
        'usage' => [
            'requests_per_minute' => ['threshold' => 100, 'alert' => false],
            'token_usage' => ['threshold' => 100000, 'alert' => true]
        ]
    ],
    
    'alerts' => [
        'email' => env('ALERT_EMAIL'),
        'slack' => env('ALERT_SLACK_WEBHOOK'),
        'sms' => env('ALERT_SMS_NUMBER')
    ]
];
```

### Final Validation

**Production Readiness Checklist**:

- [ ] All advanced features deployed and working
- [ ] Performance meets or exceeds baseline
- [ ] Security measures active and tested
- [ ] Monitoring and alerting functional
- [ ] User feedback positive
- [ ] Documentation complete
- [ ] Team trained on new features
- [ ] Support procedures updated

---

## Rollback Procedures

### Quick Rollback (Emergency)

```bash
# Emergency rollback script
#!/bin/bash

echo "EMERGENCY ROLLBACK INITIATED"

# Disable all new features
php artisan feature:disable enhanced_classification
php artisan feature:disable safety_framework
php artisan feature:disable multi_channel_reasoning

# Revert to previous version
git checkout $PREVIOUS_VERSION
composer install --no-dev

# Clear caches
php artisan config:clear
php artisan cache:clear

echo "Rollback completed. System reverted to safe state."
```

### Gradual Rollback

1. **Reduce traffic to new features**: Set feature flags to 0%
2. **Monitor for stability**: Ensure system is stable
3. **Investigate issues**: Analyze logs and metrics
4. **Fix and redeploy**: Address issues and redeploy
5. **Gradual re-enablement**: Slowly increase traffic again

---

## Success Metrics

### Performance Metrics
- **Classification Accuracy**: Target 95%+ (baseline: 75%)
- **Response Time**: Target <3s (baseline: 5s)
- **Error Recovery**: Target 90% auto-recovery (baseline: 40%)
- **User Satisfaction**: Target 90%+ (baseline: 70%)

### Business Metrics
- **Development Velocity**: Target 50% improvement
- **Code Quality**: Target 30% fewer bugs
- **Developer Adoption**: Target 95% team adoption
- **Support Tickets**: Target 40% reduction

### Technical Metrics
- **System Uptime**: Target 99.9%
- **Security Incidents**: Target 0 breaches
- **Performance Regression**: Target 0 degradation
- **Monitoring Coverage**: Target 100% of features

---

## Conclusion

This migration guide provides a comprehensive, phased approach to implementing advanced prompting techniques while maintaining system stability and user confidence. The key to success is:

1. **Incremental Implementation**: Each phase builds on the previous
2. **Comprehensive Testing**: Validate every change thoroughly
3. **Monitoring and Feedback**: Track performance and user satisfaction
4. **Rollback Readiness**: Always have a way back
5. **Team Alignment**: Ensure everyone understands the changes

Follow this guide step-by-step, and your Swarm agent will evolve from a basic function-calling system into a sophisticated reasoning engine comparable to leading AI tools.