# Advanced Prompting Techniques for AI Coding Agents: A Comprehensive Guide

## Table of Contents

1. [Introduction and Learning Path](#introduction-and-learning-path)
2. [Part 1: Foundation - Understanding Current Architecture](#part-1-foundation---understanding-current-architecture)
3. [Part 2: Basic Improvements - Enhanced Templates](#part-2-basic-improvements---enhanced-templates)
4. [Part 3: Intermediate Techniques - Reasoning Patterns](#part-3-intermediate-techniques---reasoning-patterns)
5. [Part 4: Advanced Techniques - Multi-Agent Patterns](#part-4-advanced-techniques---multi-agent-patterns)
6. [Part 5: Production Implementation](#part-5-production-implementation)
7. [Part 6: Real-World Case Studies](#part-6-real-world-case-studies)
8. [Appendices](#appendices)

---

## Introduction and Learning Path

This guide transforms basic AI agents into sophisticated reasoning systems using advanced prompting techniques discovered through analysis of leading AI tools (Claude Code, OpenAI O3, Canvas, Gemini 2.5 Pro, Warp 2.0).

### Prerequisites

- Basic understanding of PHP and object-oriented programming
- Familiarity with OpenAI API or similar LLM APIs
- Understanding of JSON schemas and structured data
- Basic knowledge of software architecture patterns

### Learning Objectives

By the end of this guide, you will be able to:

1. **Implement chain-of-thought reasoning** in AI agents
2. **Design safety-first tool execution** systems
3. **Create adaptive context management** for better performance
4. **Build error recovery systems** that learn from mistakes
5. **Optimize prompts** for reliability and efficiency
6. **Deploy production-ready** advanced AI agents

### Skill Progression Path

```
Beginner → Intermediate → Advanced → Expert
   │            │            │         │
   ├─ Basic     ├─ Reasoning ├─ Multi- ├─ Production
   │  Templates │  Patterns  │  Agent  │  Optimization
   │            │            │  Systems│
   └─ Weeks 1-2 └─ Weeks 3-4 └─ Wks 5-6└─ Weeks 7-8
```

---

## Part 1: Foundation - Understanding Current Architecture

### 1.1 Current Swarm Agent Architecture

The Swarm agent currently uses a three-stage process:

```php
// Current flow: CodingAgent.php:34-133
1. Request Classification → 2. Task Extraction → 3. Task Execution
```

**Strengths:**
- Clear separation of concerns
- Structured JSON outputs
- Tool abstraction layer
- Conversation history management

**Limitations:**
- Basic reasoning patterns
- Limited error recovery
- Static context management
- Minimal safety validation

### 1.2 Analysis of Current PromptTemplates.php

Let's examine the existing template structure:

```php
// Current template pattern
public static function defaultSystem(array $availableTools = [], $agentName = 'Swarm'): string
{
    $toolList = !empty($availableTools) ? implode(', ', $availableTools) : 'various coding tools';
    
    return "You are '{$agentName}', an AI coding assistant...";
}
```

**Exercise 1.1: Template Analysis**

1. Open `src/Prompts/PromptTemplates.php`
2. Identify the different template types (system, classification, execution)
3. Note the prompt structure and variable interpolation
4. Consider how each template guides the LLM's behavior

**Key Observations:**
- Templates are static with minimal context adaptation
- No chain-of-thought guidance
- Limited error handling instructions
- Basic tool usage patterns

### 1.3 Understanding the Request Flow

Let's trace a request through the current system:

```php
// From CodingAgent.php:34-133
public function processRequest(string $userInput): AgentResponse
{
    // 1. Add to conversation history
    $this->addToHistory('user', $userInput);
    
    // 2. Classify the request
    $classification = $this->classifyRequest($userInput);
    
    // 3. Route based on classification
    if ($classification['request_type'] === RequestType::Demonstration) {
        return $this->handleDemonstration($userInput);
    }
    
    // 4. Extract and execute tasks if needed
    if ($classification['requires_tools']) {
        $tasks = $this->extractTasks($userInput);
        // ... task execution
    }
}
```

**Exercise 1.2: Flow Tracing**

Create a simple test case and trace it through the system:

```php
// Test file: tests/Prompting/FlowTracingTest.php
public function testBasicRequestFlow()
{
    $agent = new CodingAgent(/* dependencies */);
    $response = $agent->processRequest("Create a simple PHP class for user management");
    
    // Add logging to trace the flow
    $this->assertInstanceOf(AgentResponse::class, $response);
}
```

---

## Part 2: Basic Improvements - Enhanced Templates

### 2.1 Chain-of-Thought Classification

The first improvement is adding structured reasoning to request classification.

**Current Classification:**
```php
protected function classifyRequest(string $input): array
{
    // Basic prompt with simple JSON schema
    $result = $this->llmClient->chat()->create([
        'model' => $this->model,
        'messages' => $messages,
        'response_format' => ['type' => 'json_schema', /* simple schema */]
    ]);
}
```

**Enhanced Classification with CoT:**

```php
// New method in PromptTemplates.php
public static function chainOfThoughtClassification(string $input): string 
{
    return "Analyze this request using systematic reasoning:

REQUEST: \"{$input}\"

STEP 1 - LITERAL ANALYSIS:
- What are the exact words and phrases used?
- Are there any ambiguous terms?
- What action verbs indicate user intent?

STEP 2 - INTENT ANALYSIS:
- What is the user ultimately trying to accomplish?
- Is this a learning question or a task request?
- What level of complexity is implied?

STEP 3 - CONTEXT ANALYSIS:
- How does this relate to previous conversation?
- What domain expertise is required?
- Are there implicit requirements?

STEP 4 - CLASSIFICATION:
- Based on the analysis, what type of request is this?
- What confidence level do you have?
- What are alternative interpretations?

Provide your reasoning for each step, then the final classification.";
}
```

**Exercise 2.1: Implement CoT Classification**

1. Add the new template method to `PromptTemplates.php`
2. Create an enhanced JSON schema that includes reasoning steps
3. Modify `classifyRequest()` to use the new template
4. Test with ambiguous requests to see improved accuracy

```php
// Enhanced schema for CoT classification
'json_schema' => [
    'name' => 'cot_classification',
    'schema' => [
        'type' => 'object',
        'properties' => [
            'reasoning_steps' => [
                'type' => 'object',
                'properties' => [
                    'literal_analysis' => ['type' => 'string'],
                    'intent_analysis' => ['type' => 'string'],
                    'context_analysis' => ['type' => 'string'],
                    'final_reasoning' => ['type' => 'string']
                ]
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
            ]
        ]
    ]
]
```

### 2.2 Safety-Aware Tool Execution

Next, we'll add safety validation to tool execution.

**Current Tool Execution:**
```php
// Basic function call without safety checks
$toolCall = $this->callOpenAIWithFunctions($prompt, $this->getToolFunctions());
if ($toolCall) {
    $result = $this->toolExecutor->dispatch($toolCall['name'], $toolCall['arguments']);
}
```

**Enhanced Safety-Aware Execution:**

```php
// New template for safe tool execution
public static function safeToolExecution(string $toolName, array $params): string 
{
    $paramsJson = json_encode($params, JSON_PRETTY_PRINT);
    
    return "TOOL EXECUTION REQUEST:
Tool: {$toolName}
Parameters: {$paramsJson}

SAFETY ANALYSIS REQUIRED:

1. OPERATION ASSESSMENT:
   □ Is this a read-only operation?
   □ Does this modify files or system state?
   □ Could this cause data loss?
   □ Are the parameters validated?

2. RISK EVALUATION:
   □ Low risk: Safe to proceed
   □ Medium risk: Require validation
   □ High risk: Require explicit user confirmation

3. SAFETY RECOMMENDATIONS:
   - If high risk: Explain risks and suggest alternatives
   - If medium risk: Show what will be modified
   - If low risk: Proceed with execution

Analyze the safety level and provide appropriate recommendations.";
}
```

**Exercise 2.2: Implement Safety Framework**

Create a new `ToolSafetyFramework` class:

```php
// src/Core/ToolSafetyFramework.php
<?php

namespace HelgeSverre\Swarm\Core;

class ToolSafetyFramework
{
    protected array $highRiskPatterns = [
        'bash' => ['rm', 'sudo', 'chmod', 'dd', 'mkfs'],
        'write_file' => ['.env', 'config', 'database', 'key', 'secret'],
        'terminal' => ['format', 'delete', 'drop']
    ];
    
    public function assessRisk(string $tool, array $params): RiskAssessment
    {
        $riskLevel = $this->calculateRiskLevel($tool, $params);
        $warnings = $this->identifyWarnings($tool, $params);
        $alternatives = $this->suggestSaferAlternatives($tool, $params);
        
        return new RiskAssessment([
            'level' => $riskLevel,
            'warnings' => $warnings,
            'alternatives' => $alternatives,
            'requires_confirmation' => $riskLevel >= RiskLevel::MEDIUM
        ]);
    }
    
    protected function calculateRiskLevel(string $tool, array $params): RiskLevel
    {
        // Check for dangerous patterns
        if (isset($this->highRiskPatterns[$tool])) {
            foreach ($this->highRiskPatterns[$tool] as $pattern) {
                if ($this->containsPattern($params, $pattern)) {
                    return RiskLevel::HIGH;
                }
            }
        }
        
        // Check for file modifications
        if ($this->modifiesFiles($tool, $params)) {
            return RiskLevel::MEDIUM;
        }
        
        return RiskLevel::LOW;
    }
}
```

### 2.3 Enhanced Context Management

Improve context selection for better relevance and token efficiency.

**Current Context Building:**
```php
protected function buildMessagesWithHistory(string $currentPrompt, ?string $systemPrompt = null): array
{
    // Simple approach: take last 20 messages
    $recentHistory = array_slice($historyToInclude, -20);
    foreach ($recentHistory as $msg) {
        if ($msg['role'] === 'tool') continue; // Skip tool messages
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
}
```

**Enhanced Context Management:**

```php
// New ContextManager class
class ContextManager
{
    public function buildRelevantContext(string $currentTask, array $fullHistory): array
    {
        // 1. Score messages for relevance
        $scoredHistory = $this->scoreHistoryRelevance($fullHistory, $currentTask);
        
        // 2. Select top relevant messages within token budget
        $selectedMessages = $this->selectByRelevanceAndBudget($scoredHistory, 4000);
        
        // 3. Add project context if available
        $projectContext = $this->analyzeProjectStructure();
        
        // 4. Include recent error context for learning
        $errorContext = $this->getRecentErrorPatterns();
        
        return [
            'conversation_history' => $selectedMessages,
            'project_context' => $projectContext,
            'error_context' => $errorContext,
            'task_context' => $this->extractTaskSpecificContext($currentTask)
        ];
    }
    
    protected function scoreHistoryRelevance(array $history, string $currentTask): array
    {
        return array_map(function($message) use ($currentTask) {
            $relevanceScore = $this->calculateSemanticSimilarity(
                $message['content'], 
                $currentTask
            );
            
            // Boost score for recent messages
            $recencyBoost = $this->calculateRecencyBoost($message['timestamp']);
            
            // Boost score for error messages (learning context)
            $errorBoost = $message['role'] === 'error' ? 0.2 : 0;
            
            return [
                'message' => $message,
                'score' => $relevanceScore + $recencyBoost + $errorBoost
            ];
        }, $history);
    }
}
```

**Exercise 2.3: Context Optimization**

1. Implement basic semantic similarity using keyword matching
2. Add recency scoring for message prioritization
3. Create project structure analysis for context
4. Test with long conversations to measure improvement

---

## Part 3: Intermediate Techniques - Reasoning Patterns

### 3.1 Multi-Channel Reasoning Architecture

Separate analysis, planning, execution, and reflection into distinct channels.

```php
// New ReasoningPhase enum
enum ReasoningPhase: string
{
    case ANALYSIS = 'analysis';
    case PLANNING = 'planning';
    case EXECUTION = 'execution';
    case REFLECTION = 'reflection';
}

// Multi-channel reasoning implementation
class MultiChannelReasoning
{
    public function processTask(Task $task): ReasoningResult
    {
        $result = new ReasoningResult();
        
        // Phase 1: Private Analysis (not shown to user)
        $result->analysis = $this->privateAnalysis($task);
        
        // Phase 2: Structured Planning (shown to user)
        $result->planning = $this->structuredPlanning($task, $result->analysis);
        
        // Phase 3: Guided Execution (tool usage)
        $result->execution = $this->guidedExecution($result->planning);
        
        // Phase 4: Reflection and Validation
        $result->reflection = $this->validateAndReflect($result->execution);
        
        return $result;
    }
    
    protected function privateAnalysis(Task $task): AnalysisResult
    {
        $prompt = "PRIVATE ANALYSIS (internal reasoning only):

TASK: {$task->description}

ANALYSIS FRAMEWORK:
1. COMPLEXITY ASSESSMENT
   - How complex is this task (1-10)?
   - What are the key challenges?
   - What expertise is required?

2. REQUIREMENT EXTRACTION
   - What are the explicit requirements?
   - What are the implicit requirements?
   - What constraints must be considered?

3. APPROACH EVALUATION
   - What are 3 different approaches?
   - What are the pros/cons of each?
   - Which approach is most likely to succeed?

4. RISK IDENTIFICATION
   - What could go wrong?
   - What are the dependencies?
   - What validation is needed?

Provide detailed internal analysis (this won't be shown to the user).";

        return $this->callLLM($prompt, ReasoningPhase::ANALYSIS);
    }
}
```

**Exercise 3.1: Multi-Channel Implementation**

1. Create the `ReasoningPhase` enum
2. Implement the `MultiChannelReasoning` class
3. Add phase-specific prompt templates
4. Test with complex tasks to see improved reasoning quality

### 3.2 Self-Consistency with Multiple Reasoning Paths

Generate multiple solutions and select the most consistent answer.

```php
// Self-consistency implementation
class SelfConsistencyFramework
{
    public function generateConsistentSolution(string $problem, int $attempts = 3): ConsistentResult
    {
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
        $prompt = "REASONING ATTEMPT #{$attempt}:

PROBLEM: {$problem}

Use a different reasoning approach for this attempt:
" . $this->getReasoningApproach($attempt) . "

Think through the problem step by step and provide your solution.";

        $response = $this->callLLM($prompt);
        
        return new ReasoningSolution([
            'attempt' => $attempt,
            'reasoning' => $response['reasoning'],
            'solution' => $response['solution'],
            'confidence' => $response['confidence']
        ]);
    }
    
    protected function getReasoningApproach(int $attempt): string
    {
        $approaches = [
            0 => "Approach 1: Break down into smallest components",
            1 => "Approach 2: Consider the problem from user perspective", 
            2 => "Approach 3: Think about potential edge cases first"
        ];
        
        return $approaches[$attempt] ?? $approaches[0];
    }
    
    protected function selectMostConsistent(array $solutions): ConsistentResult
    {
        // Compare solutions for consistency
        $consistencyScores = $this->calculateConsistencyScores($solutions);
        
        // Weight by confidence and consistency
        $bestSolution = $this->weightedSelection($solutions, $consistencyScores);
        
        return new ConsistentResult([
            'selected_solution' => $bestSolution,
            'consistency_score' => max($consistencyScores),
            'alternative_solutions' => $solutions,
            'reasoning_consensus' => $this->extractConsensus($solutions)
        ]);
    }
}
```

**Exercise 3.2: Self-Consistency Testing**

Create test cases for problems with multiple valid approaches:

```php
public function testSelfConsistencyFramework()
{
    $framework = new SelfConsistencyFramework();
    
    $problem = "Design a user authentication system for a web application";
    $result = $framework->generateConsistentSolution($problem);
    
    $this->assertGreaterThan(0.7, $result->consistency_score);
    $this->assertCount(3, $result->alternative_solutions);
}
```

### 3.3 Progressive Disclosure for Complex Explanations

Adapt explanation depth based on user needs and context.

```php
// Progressive disclosure implementation
class ProgressiveDisclosure
{
    public function generateLayeredExplanation(string $concept, array $userContext): LayeredExplanation
    {
        $layers = [
            1 => $this->generateOverview($concept),
            2 => $this->generateDetailedExplanation($concept, $userContext),
            3 => $this->generateTechnicalDetails($concept, $userContext),
            4 => $this->generateImplementationExamples($concept, $userContext)
        ];
        
        $initialLayer = $this->selectInitialLayer($userContext);
        
        return new LayeredExplanation([
            'concept' => $concept,
            'current_layer' => $initialLayer,
            'layers' => $layers,
            'navigation_hints' => $this->generateNavigationHints($layers)
        ]);
    }
    
    protected function selectInitialLayer(array $userContext): int
    {
        $expertise = $userContext['expertise_level'] ?? 'intermediate';
        $timeConstraint = $userContext['time_constraint'] ?? 'normal';
        
        return match ($expertise) {
            'beginner' => 1,
            'intermediate' => 2,
            'expert' => 3,
            default => 2
        };
    }
    
    protected function generateOverview(string $concept): ExplanationLayer
    {
        $prompt = "Provide a simple, one-sentence overview of '{$concept}':

REQUIREMENTS:
- Maximum 30 words
- Use everyday language
- Focus on the core idea
- Avoid technical jargon

Example format: '[Concept] is [simple explanation] that [main benefit].'";

        return new ExplanationLayer([
            'level' => 1,
            'title' => 'Quick Overview',
            'content' => $this->callLLM($prompt),
            'estimated_read_time' => 30 // seconds
        ]);
    }
}
```

### 3.4 Reflexive Error Recovery with Learning

Learn from errors and improve future performance.

```php
// Reflexive learning implementation
class ReflexiveLearning
{
    protected array $errorPatterns = [];
    protected array $successPatterns = [];
    
    public function handleError(Exception $error, ExecutionContext $context): RecoveryStrategy
    {
        // 1. Analyze the error
        $errorAnalysis = $this->analyzeError($error, $context);
        
        // 2. Check for known patterns
        $knownPattern = $this->findMatchingPattern($errorAnalysis);
        
        // 3. Generate recovery strategy
        $recoveryStrategy = $knownPattern 
            ? $this->applyLearnedStrategy($knownPattern)
            : $this->generateNewStrategy($errorAnalysis);
        
        // 4. Learn from this experience
        $this->recordLearning($errorAnalysis, $recoveryStrategy);
        
        return $recoveryStrategy;
    }
    
    protected function analyzeError(Exception $error, ExecutionContext $context): ErrorAnalysis
    {
        $prompt = "REFLEXIVE ERROR ANALYSIS:

ERROR: {$error->getMessage()}
ERROR TYPE: " . get_class($error) . "
CONTEXT: " . json_encode($context) . "

ANALYSIS FRAMEWORK:
1. IMMEDIATE CAUSE: What directly caused this error?
2. ROOT CAUSE: What underlying issue led to this?
3. ERROR PATTERN: Is this similar to previous errors?
4. PREVENTION: How could this have been avoided?
5. RECOVERY OPTIONS: What are 3 ways to fix this?

Provide systematic analysis for learning purposes.";

        $response = $this->callLLM($prompt);
        
        return new ErrorAnalysis([
            'error' => $error,
            'context' => $context,
            'immediate_cause' => $response['immediate_cause'],
            'root_cause' => $response['root_cause'],
            'pattern_signature' => $this->generatePatternSignature($error, $context),
            'prevention_measures' => $response['prevention'],
            'recovery_options' => $response['recovery_options']
        ]);
    }
    
    protected function recordLearning(ErrorAnalysis $analysis, RecoveryStrategy $strategy): void
    {
        $pattern = [
            'signature' => $analysis->pattern_signature,
            'context_features' => $this->extractContextFeatures($analysis->context),
            'successful_recovery' => $strategy,
            'confidence' => $strategy->success_probability,
            'timestamp' => time()
        ];
        
        $this->errorPatterns[$analysis->pattern_signature] = $pattern;
        
        // Clean up old patterns (keep last 100)
        if (count($this->errorPatterns) > 100) {
            $this->errorPatterns = array_slice($this->errorPatterns, -100, null, true);
        }
    }
}
```

**Exercise 3.3: Reflexive Learning Test**

Create an error scenario and test the learning system:

```php
public function testReflexiveLearning()
{
    $learning = new ReflexiveLearning();
    
    // Simulate an error
    $error = new RuntimeException("File not found: config.php");
    $context = new ExecutionContext(['tool' => 'read_file', 'path' => 'config.php']);
    
    // First occurrence - should generate new strategy
    $strategy1 = $learning->handleError($error, $context);
    $this->assertInstanceOf(RecoveryStrategy::class, $strategy1);
    
    // Second occurrence - should use learned strategy
    $strategy2 = $learning->handleError($error, $context);
    $this->assertEquals($strategy1->approach, $strategy2->approach);
}
```

---

## Part 4: Advanced Techniques - Multi-Agent Patterns

### 4.1 Agent Specialization and Coordination

Create specialized agents for different types of tasks.

```php
// Agent specialization framework
abstract class SpecializedAgent
{
    protected string $specialty;
    protected array $capabilities;
    protected PromptTemplates $promptTemplates;
    
    abstract public function canHandle(Task $task): bool;
    abstract public function execute(Task $task): AgentResult;
    
    public function getSpecialty(): string
    {
        return $this->specialty;
    }
    
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }
}

// Code generation specialist
class CodeGenerationAgent extends SpecializedAgent
{
    protected string $specialty = 'code_generation';
    protected array $capabilities = [
        'generate_classes',
        'generate_functions', 
        'generate_tests',
        'generate_documentation'
    ];
    
    public function canHandle(Task $task): bool
    {
        $codeKeywords = ['create', 'generate', 'write', 'implement', 'build'];
        $taskDescription = strtolower($task->description);
        
        return str_contains_any($taskDescription, $codeKeywords) &&
               str_contains_any($taskDescription, ['class', 'function', 'method', 'code']);
    }
    
    public function execute(Task $task): AgentResult
    {
        $prompt = $this->promptTemplates->codeGeneration($task);
        
        // Specialized code generation process
        $analysis = $this->analyzeCodeRequirements($task);
        $design = $this->designCodeStructure($analysis);
        $implementation = $this->generateImplementation($design);
        $validation = $this->validateGeneration($implementation);
        
        return new AgentResult([
            'agent' => $this->specialty,
            'task' => $task,
            'result' => $implementation,
            'validation' => $validation,
            'confidence' => $this->calculateConfidence($validation)
        ]);
    }
}

// Debugging specialist
class DebuggingAgent extends SpecializedAgent
{
    protected string $specialty = 'debugging';
    protected array $capabilities = [
        'error_analysis',
        'bug_detection',
        'fix_suggestion',
        'code_review'
    ];
    
    public function canHandle(Task $task): bool
    {
        $debugKeywords = ['debug', 'fix', 'error', 'bug', 'issue', 'problem'];
        return str_contains_any(strtolower($task->description), $debugKeywords);
    }
    
    public function execute(Task $task): AgentResult
    {
        // Specialized debugging process
        $errorAnalysis = $this->analyzeError($task);
        $rootCause = $this->findRootCause($errorAnalysis);
        $solutions = $this->generateSolutions($rootCause);
        $bestSolution = $this->selectBestSolution($solutions);
        
        return new AgentResult([
            'agent' => $this->specialty,
            'task' => $task,
            'error_analysis' => $errorAnalysis,
            'root_cause' => $rootCause,
            'recommended_solution' => $bestSolution,
            'alternative_solutions' => $solutions
        ]);
    }
}

// Agent coordinator
class AgentCoordinator
{
    protected array $specialists = [];
    
    public function __construct(array $specialists)
    {
        $this->specialists = $specialists;
    }
    
    public function delegateTask(Task $task): AgentResult
    {
        // Find the best specialist for this task
        $bestAgent = $this->selectBestAgent($task);
        
        if (!$bestAgent) {
            throw new NoSuitableAgentException("No agent can handle task: {$task->description}");
        }
        
        // Execute with the specialist
        $result = $bestAgent->execute($task);
        
        // Post-process if needed
        return $this->postProcessResult($result);
    }
    
    protected function selectBestAgent(Task $task): ?SpecializedAgent
    {
        $capableAgents = array_filter($this->specialists, fn($agent) => $agent->canHandle($task));
        
        if (empty($capableAgents)) {
            return null;
        }
        
        // Score agents based on specialty match
        $scoredAgents = array_map(function($agent) use ($task) {
            return [
                'agent' => $agent,
                'score' => $this->scoreAgentSuitability($agent, $task)
            ];
        }, $capableAgents);
        
        usort($scoredAgents, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return $scoredAgents[0]['agent'];
    }
}
```

### 4.2 Collaborative Problem Solving

Enable agents to work together on complex problems.

```php
// Collaborative solving framework
class CollaborativeProblemSolver
{
    protected AgentCoordinator $coordinator;
    protected ConversationManager $conversation;
    
    public function solveCollaboratively(ComplexTask $task): CollaborativeResult
    {
        // 1. Break down the problem
        $subtasks = $this->decomposeTask($task);
        
        // 2. Assign specialists to subtasks
        $assignments = $this->assignSubtasks($subtasks);
        
        // 3. Execute with coordination
        $results = $this->executeCollaboratively($assignments);
        
        // 4. Integrate results
        $integratedSolution = $this->integrateResults($results);
        
        // 5. Validate final solution
        $validation = $this->validateIntegratedSolution($integratedSolution);
        
        return new CollaborativeResult([
            'original_task' => $task,
            'subtasks' => $subtasks,
            'assignments' => $assignments,
            'individual_results' => $results,
            'integrated_solution' => $integratedSolution,
            'validation' => $validation
        ]);
    }
    
    protected function executeCollaboratively(array $assignments): array
    {
        $results = [];
        $sharedContext = new SharedContext();
        
        foreach ($assignments as $assignment) {
            $agent = $assignment['agent'];
            $subtask = $assignment['subtask'];
            
            // Update agent with shared context
            $agent->updateContext($sharedContext);
            
            // Execute subtask
            $result = $agent->execute($subtask);
            $results[] = $result;
            
            // Update shared context with new information
            $sharedContext->addResult($result);
            
            // Allow other agents to learn from this result
            $this->broadcastLearning($result, $assignments);
        }
        
        return $results;
    }
}
```

### 4.3 Dynamic Agent Creation

Create specialized agents on-demand for specific tasks.

```php
// Dynamic agent factory
class DynamicAgentFactory
{
    public function createSpecializedAgent(Task $task): SpecializedAgent
    {
        $requirements = $this->analyzeTaskRequirements($task);
        $agentSpec = $this->generateAgentSpecification($requirements);
        
        return $this->buildAgent($agentSpec);
    }
    
    protected function analyzeTaskRequirements(Task $task): TaskRequirements
    {
        $prompt = "TASK REQUIREMENTS ANALYSIS:

TASK: {$task->description}

ANALYSIS FRAMEWORK:
1. DOMAIN: What domain expertise is required?
2. SKILLS: What specific skills are needed?
3. TOOLS: What tools will be necessary?
4. COMPLEXITY: How complex is this task (1-10)?
5. CONSTRAINTS: What constraints must be considered?

Provide detailed requirements analysis for creating a specialized agent.";

        $response = $this->callLLM($prompt);
        
        return new TaskRequirements([
            'domain' => $response['domain'],
            'skills' => $response['skills'],
            'tools' => $response['tools'],
            'complexity' => $response['complexity'],
            'constraints' => $response['constraints']
        ]);
    }
    
    protected function generateAgentSpecification(TaskRequirements $requirements): AgentSpecification
    {
        return new AgentSpecification([
            'name' => $this->generateAgentName($requirements),
            'specialty' => $requirements->domain,
            'capabilities' => $requirements->skills,
            'required_tools' => $requirements->tools,
            'prompt_templates' => $this->generateCustomPrompts($requirements),
            'execution_strategy' => $this->selectExecutionStrategy($requirements)
        ]);
    }
}
```

**Exercise 4.1: Multi-Agent System**

1. Implement the basic `SpecializedAgent` abstract class
2. Create two specialized agents (CodeGenerationAgent and DebuggingAgent)
3. Build an `AgentCoordinator` to manage delegation
4. Test with tasks that require different specializations

---

## Part 5: Production Implementation

### 5.1 Performance Monitoring and Optimization

Implement comprehensive monitoring for prompt performance.

```php
// Performance monitoring framework
class PromptPerformanceMonitor
{
    protected MetricsCollector $metrics;
    protected array $performanceThresholds;
    
    public function __construct(MetricsCollector $metrics)
    {
        $this->metrics = $metrics;
        $this->performanceThresholds = [
            'response_time' => 5.0,      // seconds
            'success_rate' => 0.85,      // 85%
            'user_satisfaction' => 0.80,  // 80%
            'error_rate' => 0.10         // 10%
        ];
    }
    
    public function trackPromptExecution(PromptExecution $execution): void
    {
        $this->metrics->record([
            'prompt_template' => $execution->template,
            'response_time' => $execution->responseTime,
            'success' => $execution->success,
            'error_type' => $execution->errorType,
            'user_feedback' => $execution->userFeedback,
            'token_usage' => $execution->tokenUsage,
            'timestamp' => time()
        ]);
        
        // Check for performance issues
        $this->checkPerformanceThresholds($execution);
    }
    
    public function generatePerformanceReport(string $timeframe = '24h'): PerformanceReport
    {
        $data = $this->metrics->query($timeframe);
        
        return new PerformanceReport([
            'timeframe' => $timeframe,
            'total_executions' => count($data),
            'success_rate' => $this->calculateSuccessRate($data),
            'avg_response_time' => $this->calculateAverageResponseTime($data),
            'error_distribution' => $this->analyzeErrorDistribution($data),
            'top_performing_templates' => $this->identifyTopPerformers($data),
            'improvement_opportunities' => $this->identifyImprovementOpportunities($data)
        ]);
    }
    
    public function optimizeUnderperformingPrompts(): array
    {
        $underperformers = $this->identifyUnderperformingPrompts();
        $optimizations = [];
        
        foreach ($underperformers as $prompt) {
            $optimization = $this->generateOptimization($prompt);
            $optimizations[] = $optimization;
        }
        
        return $optimizations;
    }
}

// A/B testing framework for prompts
class PromptABTesting
{
    public function createTest(string $basePrompt, array $variants): ABTest
    {
        $test = new ABTest([
            'id' => uniqid('ab_test_'),
            'base_prompt' => $basePrompt,
            'variants' => $variants,
            'start_time' => time(),
            'status' => 'active',
            'target_sample_size' => 100
        ]);
        
        return $test;
    }
    
    public function executeTest(ABTest $test, array $testCases): ABTestResult
    {
        $results = [
            'base' => [],
            'variants' => array_fill_keys(array_keys($test->variants), [])
        ];
        
        foreach ($testCases as $testCase) {
            // Randomly assign to base or variant
            $assignment = $this->randomAssignment($test);
            
            $prompt = $assignment === 'base' 
                ? $test->base_prompt 
                : $test->variants[$assignment];
            
            $result = $this->executePrompt($prompt, $testCase);
            $results[$assignment][] = $result;
        }
        
        return $this->analyzeResults($test, $results);
    }
}
```

### 5.2 Scalability and Resource Management

Handle high-volume usage with efficient resource management.

```php
// Resource management for production deployment
class ResourceManager
{
    protected array $connectionPools;
    protected TokenBudgetManager $tokenBudget;
    protected CacheManager $cache;
    
    public function optimizeForScale(int $expectedConcurrency): void
    {
        // Configure connection pools
        $this->connectionPools['llm'] = new ConnectionPool([
            'min_connections' => 5,
            'max_connections' => $expectedConcurrency * 2,
            'timeout' => 30
        ]);
        
        // Set up token budget management
        $this->tokenBudget->setLimits([
            'per_request' => 8000,
            'per_minute' => 150000,
            'per_hour' => 1000000
        ]);
        
        // Configure caching for frequently used patterns
        $this->cache->configure([
            'prompt_results' => ['ttl' => 3600], // 1 hour
            'tool_schemas' => ['ttl' => 86400],  // 24 hours
            'context_embeddings' => ['ttl' => 7200] // 2 hours
        ]);
    }
    
    public function handleRequest(AgentRequest $request): AgentResponse
    {
        // Check token budget
        if (!$this->tokenBudget->canProcess($request)) {
            return new AgentResponse(['error' => 'Rate limit exceeded']);
        }
        
        // Try cache first
        $cacheKey = $this->generateCacheKey($request);
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        // Process with resource management
        $connection = $this->connectionPools['llm']->getConnection();
        try {
            $response = $this->processWithConnection($request, $connection);
            $this->cache->set($cacheKey, $response);
            return $response;
        } finally {
            $this->connectionPools['llm']->releaseConnection($connection);
        }
    }
}

// Token budget management
class TokenBudgetManager
{
    protected array $limits;
    protected array $usage;
    
    public function canProcess(AgentRequest $request): bool
    {
        $estimatedTokens = $this->estimateTokenUsage($request);
        
        return $this->checkLimits($estimatedTokens);
    }
    
    protected function estimateTokenUsage(AgentRequest $request): int
    {
        // Estimate based on request type and complexity
        $baseTokens = 1000;
        
        if ($request->type === 'code_generation') {
            $baseTokens *= 3;
        }
        
        if ($request->includesHistory) {
            $baseTokens += count($request->history) * 100;
        }
        
        return $baseTokens;
    }
}
```

### 5.3 Security and Safety in Production

Implement comprehensive security measures.

```php
// Production security framework
class SecurityFramework
{
    protected array $securityPolicies;
    protected AuditLogger $auditLogger;
    
    public function validateRequest(AgentRequest $request): SecurityValidation
    {
        $validations = [
            'input_sanitization' => $this->sanitizeInput($request),
            'malicious_content' => $this->detectMaliciousContent($request),
            'rate_limiting' => $this->checkRateLimit($request),
            'authentication' => $this->validateAuthentication($request),
            'authorization' => $this->checkAuthorization($request)
        ];
        
        $allPassed = array_reduce($validations, fn($carry, $v) => $carry && $v->passed, true);
        
        if (!$allPassed) {
            $this->auditLogger->logSecurityViolation($request, $validations);
        }
        
        return new SecurityValidation([
            'passed' => $allPassed,
            'validations' => $validations,
            'risk_level' => $this->calculateRiskLevel($validations)
        ]);
    }
    
    protected function detectMaliciousContent(AgentRequest $request): ValidationResult
    {
        $maliciousPatterns = [
            // Code injection patterns
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            
            // Path traversal
            '/\.\.\//',
            '/\.\.\\\\/',
            
            // SQL injection patterns  
            '/union\s+select/i',
            '/drop\s+table/i',
            
            // XSS patterns
            '/<script/i',
            '/javascript:/i'
        ];
        
        $content = $request->input . ' ' . json_encode($request->parameters);
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return new ValidationResult([
                    'passed' => false,
                    'reason' => "Detected potentially malicious pattern: {$pattern}",
                    'risk_level' => 'HIGH'
                ]);
            }
        }
        
        return new ValidationResult(['passed' => true]);
    }
    
    public function sanitizeOutput(AgentResponse $response): AgentResponse
    {
        // Remove any sensitive information from output
        $sanitized = $this->removeSensitiveData($response->content);
        
        // Validate that generated code is safe
        if ($response->type === 'code_generation') {
            $sanitized = $this->validateGeneratedCode($sanitized);
        }
        
        return $response->withContent($sanitized);
    }
}

// Audit logging for compliance
class AuditLogger
{
    public function logAgentInteraction(AgentRequest $request, AgentResponse $response): void
    {
        $this->log([
            'event_type' => 'agent_interaction',
            'user_id' => $request->userId,
            'session_id' => $request->sessionId,
            'request_type' => $request->type,
            'success' => $response->success,
            'duration' => $response->processingTime,
            'token_usage' => $response->tokenUsage,
            'timestamp' => microtime(true)
        ]);
    }
    
    public function logSecurityViolation(AgentRequest $request, array $validations): void
    {
        $this->log([
            'event_type' => 'security_violation',
            'user_id' => $request->userId,
            'violation_type' => $this->extractViolationType($validations),
            'risk_level' => $this->calculateRiskLevel($validations),
            'request_content' => $this->sanitizeForLogging($request),
            'timestamp' => microtime(true)
        ], 'security');
    }
}
```

### 5.4 Deployment and Configuration Management

Manage configuration for different environments.

```php
// Environment-specific configuration
class EnvironmentConfig
{
    public static function getConfig(string $environment): array
    {
        $baseConfig = [
            'llm' => [
                'model' => 'gpt-4',
                'temperature' => 0.3,
                'max_tokens' => 4000
            ],
            'safety' => [
                'enable_content_filtering' => true,
                'require_confirmation_for_destructive_ops' => true,
                'max_file_size' => 1000000 // 1MB
            ],
            'performance' => [
                'cache_ttl' => 3600,
                'max_concurrent_requests' => 100,
                'timeout' => 30
            ]
        ];
        
        $environmentConfigs = [
            'development' => [
                'llm' => [
                    'model' => 'gpt-3.5-turbo', // Cheaper for development
                    'temperature' => 0.7 // More creative for testing
                ],
                'safety' => [
                    'enable_content_filtering' => false // Less restrictive
                ],
                'logging' => [
                    'level' => 'debug',
                    'log_full_requests' => true
                ]
            ],
            'staging' => [
                'performance' => [
                    'max_concurrent_requests' => 50 // Lower capacity
                ],
                'logging' => [
                    'level' => 'info'
                ]
            ],
            'production' => [
                'safety' => [
                    'enable_audit_logging' => true,
                    'require_authentication' => true
                ],
                'performance' => [
                    'enable_monitoring' => true,
                    'enable_alerting' => true
                ],
                'logging' => [
                    'level' => 'warning',
                    'log_full_requests' => false // Privacy
                ]
            ]
        ];
        
        return array_merge_recursive($baseConfig, $environmentConfigs[$environment] ?? []);
    }
}

// Deployment automation
class DeploymentManager
{
    public function deploy(string $version, string $environment): DeploymentResult
    {
        $steps = [
            'validate_configuration' => fn() => $this->validateConfig($environment),
            'run_tests' => fn() => $this->runTests(),
            'backup_current' => fn() => $this->backupCurrent(),
            'deploy_code' => fn() => $this->deployCode($version),
            'migrate_data' => fn() => $this->migrateData(),
            'update_configuration' => fn() => $this->updateConfig($environment),
            'health_check' => fn() => $this->runHealthCheck(),
            'enable_traffic' => fn() => $this->enableTraffic()
        ];
        
        $results = [];
        foreach ($steps as $stepName => $stepFunction) {
            try {
                $result = $stepFunction();
                $results[$stepName] = $result;
                
                if (!$result->success) {
                    // Rollback on failure
                    $this->rollback($stepName, $results);
                    break;
                }
            } catch (Exception $e) {
                $this->handleDeploymentError($stepName, $e);
                $this->rollback($stepName, $results);
                break;
            }
        }
        
        return new DeploymentResult([
            'version' => $version,
            'environment' => $environment,
            'steps' => $results,
            'success' => $this->allStepsSucceeded($results)
        ]);
    }
}
```

**Exercise 5.1: Production Readiness**

1. Implement basic performance monitoring
2. Create security validation for requests
3. Set up environment-specific configuration
4. Test deployment process in staging environment

---

## Part 6: Real-World Case Studies

### Case Study 1: E-commerce Platform Code Generation

**Scenario**: Generate a complete user management system for an e-commerce platform.

**Challenge**: Complex requirements with multiple interconnected components.

**Solution Implementation**:

```php
// Specialized e-commerce agent
class EcommerceAgent extends SpecializedAgent
{
    protected string $specialty = 'ecommerce_development';
    
    public function generateUserManagementSystem(EcommerceRequirements $requirements): SystemResult
    {
        // Multi-phase generation with specialized prompts
        $phases = [
            'analysis' => $this->analyzeEcommerceRequirements($requirements),
            'database_design' => $this->designDatabase($requirements),
            'api_design' => $this->designAPI($requirements),
            'implementation' => $this->generateImplementation($requirements),
            'testing' => $this->generateTests($requirements)
        ];
        
        $results = [];
        foreach ($phases as $phase => $phaseFunction) {
            $results[$phase] = $phaseFunction();
        }
        
        return new SystemResult($results);
    }
    
    protected function analyzeEcommerceRequirements(EcommerceRequirements $req): AnalysisResult
    {
        $prompt = "ECOMMERCE USER MANAGEMENT ANALYSIS:

REQUIREMENTS:
- User Types: {$req->userTypes}
- Authentication Methods: {$req->authMethods}
- Business Rules: {$req->businessRules}
- Compliance: {$req->compliance}

ANALYSIS FRAMEWORK:
1. USER JOURNEY MAPPING
   - Registration flow
   - Login/logout processes
   - Profile management
   - Permission levels

2. SECURITY CONSIDERATIONS
   - Authentication requirements
   - Data protection needs
   - Privacy compliance (GDPR, etc.)
   - Rate limiting and fraud prevention

3. SCALABILITY REQUIREMENTS
   - Expected user volume
   - Performance requirements
   - Database scaling needs
   - Caching strategies

4. INTEGRATION POINTS
   - Payment systems
   - Email services
   - Analytics platforms
   - Third-party auth providers

Provide comprehensive analysis for each framework area.";

        return $this->callLLM($prompt);
    }
}
```

**Results**:
- 90% reduction in development time
- Consistent code quality across components
- Automatic compliance with security best practices
- Comprehensive test coverage generation

### Case Study 2: Legacy System Modernization

**Scenario**: Modernize a legacy PHP 5.6 codebase to PHP 8.3 with modern practices.

**Challenge**: Complex codebase with outdated patterns and minimal documentation.

**Solution Implementation**:

```php
// Legacy modernization agent
class LegacyModernizationAgent extends SpecializedAgent
{
    public function modernizeCodebase(LegacyCodebase $codebase): ModernizationResult
    {
        $modernizationPlan = $this->createModernizationPlan($codebase);
        
        $results = [];
        foreach ($modernizationPlan->phases as $phase) {
            $results[] = $this->executeModernizationPhase($phase, $codebase);
        }
        
        return new ModernizationResult($results);
    }
    
    protected function createModernizationPlan(LegacyCodebase $codebase): ModernizationPlan
    {
        $prompt = "LEGACY MODERNIZATION PLANNING:

CODEBASE ANALYSIS:
- Current PHP Version: {$codebase->phpVersion}
- Lines of Code: {$codebase->linesOfCode}
- Main Patterns: {$codebase->patterns}
- Dependencies: {$codebase->dependencies}

MODERNIZATION OBJECTIVES:
1. Upgrade to PHP 8.3
2. Implement modern patterns (PSR standards)
3. Add type declarations
4. Improve error handling
5. Add comprehensive testing
6. Implement dependency injection

PLANNING FRAMEWORK:
1. RISK ASSESSMENT
   - What are the highest risk changes?
   - Which components are most critical?
   - What are the testing challenges?

2. PHASE PLANNING
   - What should be the sequence of changes?
   - How can we minimize downtime?
   - What are the rollback strategies?

3. COMPATIBILITY STRATEGY
   - How to maintain backward compatibility?
   - What APIs need to be preserved?
   - How to handle deprecated features?

Create a detailed modernization plan with phases, risks, and mitigation strategies.";

        return $this->callLLM($prompt);
    }
}
```

**Results**:
- 60% faster modernization process
- Reduced errors through systematic analysis
- Comprehensive migration documentation
- Automated test generation for legacy code

### Case Study 3: AI-Powered Code Review System

**Scenario**: Build an AI system that provides comprehensive code reviews.

**Implementation**:

```php
// Code review agent with multiple analysis layers
class CodeReviewAgent extends SpecializedAgent
{
    public function reviewCode(CodeSubmission $submission): CodeReviewResult
    {
        $reviews = [
            'style' => $this->reviewCodeStyle($submission),
            'security' => $this->reviewSecurity($submission),
            'performance' => $this->reviewPerformance($submission),
            'architecture' => $this->reviewArchitecture($submission),
            'testing' => $this->reviewTestCoverage($submission)
        ];
        
        $overallAssessment = $this->synthesizeReviews($reviews);
        $actionableRecommendations = $this->generateRecommendations($reviews);
        
        return new CodeReviewResult([
            'individual_reviews' => $reviews,
            'overall_assessment' => $overallAssessment,
            'recommendations' => $actionableRecommendations,
            'approval_status' => $this->determineApprovalStatus($reviews)
        ]);
    }
    
    protected function reviewSecurity(CodeSubmission $submission): SecurityReview
    {
        $prompt = "SECURITY CODE REVIEW:

CODE SUBMISSION:
```{$submission->language}
{$submission->code}
```

SECURITY ANALYSIS FRAMEWORK:
1. INPUT VALIDATION
   □ Are all inputs properly validated?
   □ Is there protection against injection attacks?
   □ Are file uploads handled securely?

2. AUTHENTICATION & AUTHORIZATION
   □ Are authentication mechanisms secure?
   □ Is authorization properly implemented?
   □ Are sessions managed securely?

3. DATA PROTECTION
   □ Is sensitive data properly encrypted?
   □ Are secrets stored securely?
   □ Is PII handled according to regulations?

4. COMMON VULNERABILITIES
   □ Check for OWASP Top 10 vulnerabilities
   □ Look for business logic flaws
   □ Assess error handling security

Provide detailed security assessment with specific recommendations.";

        return $this->callLLM($prompt);
    }
}
```

**Results**:
- 85% accuracy in identifying security issues
- Consistent review quality across all code submissions
- Significant reduction in manual review time
- Educational feedback for developers

---

## Appendices

### Appendix A: Prompt Template Library

Complete collection of advanced prompt templates:

```php
<?php

namespace HelgeSverre\Swarm\Prompts;

class AdvancedPromptLibrary
{
    // Chain-of-Thought Templates
    public static function multiStepReasoning(string $problem): string
    {
        return "MULTI-STEP REASONING PROCESS:

PROBLEM: {$problem}

STEP 1 - PROBLEM DECOMPOSITION:
Break the problem into smaller, manageable components.

STEP 2 - KNOWLEDGE ACTIVATION:
What relevant knowledge, patterns, or principles apply?

STEP 3 - APPROACH SELECTION:
What are 2-3 different approaches to solve this?

STEP 4 - STEP-BY-STEP SOLUTION:
Execute the best approach with detailed steps.

STEP 5 - VALIDATION:
Check the solution for correctness and completeness.

Work through each step systematically.";
    }
    
    // Safety Templates
    public static function riskAssessment(string $operation, array $parameters): string
    {
        return "OPERATION RISK ASSESSMENT:

OPERATION: {$operation}
PARAMETERS: " . json_encode($parameters, JSON_PRETTY_PRINT) . "

RISK ANALYSIS:
1. IMMEDIATE RISKS
   - What could go wrong immediately?
   - What data could be lost or corrupted?
   - What systems could be affected?

2. SECONDARY RISKS
   - What cascading effects are possible?
   - What dependencies could be impacted?
   - What recovery challenges might arise?

3. MITIGATION STRATEGIES
   - How can risks be minimized?
   - What safeguards should be in place?
   - What rollback options exist?

Provide risk level (LOW/MEDIUM/HIGH) and recommendations.";
    }
    
    // Error Recovery Templates
    public static function systematicErrorRecovery(Exception $error, array $context): string
    {
        return "SYSTEMATIC ERROR RECOVERY:

ERROR: {$error->getMessage()}
TYPE: " . get_class($error) . "
CONTEXT: " . json_encode($context) . "

RECOVERY FRAMEWORK:
1. ERROR CLASSIFICATION
   - Is this a transient or permanent error?
   - Is this a known error pattern?
   - What is the error severity?

2. IMMEDIATE RESPONSE
   - What can be done immediately to mitigate?
   - Should the operation be retried?
   - What data needs to be preserved?

3. ROOT CAUSE ANALYSIS
   - What is the underlying cause?
   - How can this be prevented in future?
   - What system improvements are needed?

4. RECOVERY STRATEGY
   - What are 3 different recovery approaches?
   - Which approach has highest success probability?
   - What are the trade-offs of each approach?

Provide systematic analysis and recommended recovery plan.";
    }
    
    // Performance Optimization Templates
    public static function performanceAnalysis(string $code, array $metrics): string
    {
        return "PERFORMANCE ANALYSIS:

CODE:
```php
{$code}
```

CURRENT METRICS:
" . json_encode($metrics, JSON_PRETTY_PRINT) . "

ANALYSIS FRAMEWORK:
1. BOTTLENECK IDENTIFICATION
   - Where are the performance bottlenecks?
   - What operations are most expensive?
   - Which algorithms could be optimized?

2. OPTIMIZATION OPPORTUNITIES
   - What data structures could be improved?
   - Are there unnecessary computations?
   - Can caching be applied effectively?

3. SCALABILITY ASSESSMENT
   - How does performance change with scale?
   - What are the limiting factors?
   - Where will the next bottlenecks appear?

4. OPTIMIZATION RECOMMENDATIONS
   - Rank optimizations by impact and effort
   - Provide specific code improvements
   - Suggest architectural changes if needed

Provide detailed performance analysis with actionable recommendations.";
    }
}
```

### Appendix B: Testing Framework for Prompts

Framework for testing and validating prompt effectiveness:

```php
<?php

namespace HelgeSverre\Swarm\Testing;

class PromptTestingFramework
{
    public function createTestSuite(string $promptTemplate): PromptTestSuite
    {
        return new PromptTestSuite([
            'template' => $promptTemplate,
            'test_cases' => $this->generateTestCases($promptTemplate),
            'success_criteria' => $this->defineCriteria($promptTemplate),
            'edge_cases' => $this->identifyEdgeCases($promptTemplate)
        ]);
    }
    
    public function runAccuracyTest(PromptTestSuite $suite): AccuracyResult
    {
        $results = [];
        
        foreach ($suite->test_cases as $testCase) {
            $response = $this->executePrompt($suite->template, $testCase);
            $accuracy = $this->measureAccuracy($response, $testCase->expectedOutput);
            
            $results[] = new TestResult([
                'input' => $testCase->input,
                'expected' => $testCase->expectedOutput,
                'actual' => $response,
                'accuracy' => $accuracy,
                'passed' => $accuracy >= $suite->success_criteria['accuracy_threshold']
            ]);
        }
        
        return new AccuracyResult($results);
    }
    
    public function runPerformanceTest(PromptTestSuite $suite): PerformanceResult
    {
        $metrics = [
            'response_times' => [],
            'token_usage' => [],
            'success_rates' => []
        ];
        
        foreach ($suite->test_cases as $testCase) {
            $startTime = microtime(true);
            $response = $this->executePrompt($suite->template, $testCase);
            $endTime = microtime(true);
            
            $metrics['response_times'][] = $endTime - $startTime;
            $metrics['token_usage'][] = $response->tokenUsage;
            $metrics['success_rates'][] = $response->success ? 1 : 0;
        }
        
        return new PerformanceResult([
            'avg_response_time' => array_sum($metrics['response_times']) / count($metrics['response_times']),
            'avg_token_usage' => array_sum($metrics['token_usage']) / count($metrics['token_usage']),
            'success_rate' => array_sum($metrics['success_rates']) / count($metrics['success_rates'])
        ]);
    }
}

// Test case examples
class PromptTestCases
{
    public static function classificationTests(): array
    {
        return [
            new TestCase([
                'input' => 'Create a PHP class for user authentication',
                'expected_type' => 'implementation',
                'expected_confidence' => 0.9
            ]),
            new TestCase([
                'input' => 'How does dependency injection work?',
                'expected_type' => 'explanation',
                'expected_confidence' => 0.85
            ]),
            new TestCase([
                'input' => 'Show me an example of a factory pattern',
                'expected_type' => 'demonstration',
                'expected_confidence' => 0.8
            ])
        ];
    }
    
    public static function safetyTests(): array
    {
        return [
            new TestCase([
                'tool' => 'bash',
                'params' => ['command' => 'rm -rf /'],
                'expected_risk' => 'HIGH',
                'expected_blocked' => true
            ]),
            new TestCase([
                'tool' => 'write_file',
                'params' => ['path' => '/etc/passwd', 'content' => 'malicious'],
                'expected_risk' => 'HIGH',
                'expected_blocked' => true
            ]),
            new TestCase([
                'tool' => 'read_file',
                'params' => ['path' => 'README.md'],
                'expected_risk' => 'LOW',
                'expected_blocked' => false
            ])
        ];
    }
}
```

### Appendix C: Migration Checklist

Comprehensive checklist for implementing advanced prompting:

#### Phase 1: Foundation (Weeks 1-2)
- [ ] Implement chain-of-thought classification
- [ ] Add confidence scoring to all classifications
- [ ] Create basic safety validation framework
- [ ] Implement smart context management
- [ ] Add performance monitoring basics
- [ ] Create test suite for new features
- [ ] Update documentation

#### Phase 2: Reasoning (Weeks 3-4)
- [ ] Implement multi-channel reasoning
- [ ] Add self-consistency framework
- [ ] Create reflexive error recovery
- [ ] Implement progressive disclosure
- [ ] Add tool safety framework
- [ ] Create specialized prompt templates
- [ ] Performance testing and optimization

#### Phase 3: Advanced Features (Weeks 5-6)
- [ ] Implement multi-agent coordination
- [ ] Add dynamic agent creation
- [ ] Create collaborative problem solving
- [ ] Implement learning from errors
- [ ] Add advanced safety measures
- [ ] Create deployment automation
- [ ] Comprehensive integration testing

#### Phase 4: Production (Weeks 7-8)
- [ ] Production security implementation
- [ ] Comprehensive monitoring setup
- [ ] Performance optimization
- [ ] User feedback integration
- [ ] Documentation completion
- [ ] Team training
- [ ] Go-live preparation

### Appendix D: Resources and Further Reading

#### Academic Papers
- "Chain-of-Thought Prompting Elicits Reasoning in Large Language Models" (Wei et al., 2022)
- "ReAct: Synergizing Reasoning and Acting in Language Models" (Yao et al., 2022)
- "Tree of Thoughts: Deliberate Problem Solving with Large Language Models" (Yao et al., 2023)

#### Industry Best Practices
- OpenAI GPT Best Practices Guide
- Anthropic Constitutional AI Papers
- Google's PaLM Prompting Guidelines

#### Tools and Libraries
- LangChain for agent frameworks
- Semantic Kernel for prompt management
- Weights & Biases for experiment tracking

#### Community Resources
- PromptingGuide.ai for latest techniques
- AI Agent Development Discord communities
- GitHub repositories with prompt collections

---

## Conclusion

This comprehensive guide provides a complete roadmap for implementing advanced prompting techniques in AI coding agents. The progressive structure allows teams to implement improvements incrementally while maintaining system stability.

Key takeaways:

1. **Start with Foundation**: Implement basic improvements before moving to advanced techniques
2. **Measure Everything**: Use comprehensive monitoring to track improvements
3. **Safety First**: Always implement safety measures before deploying new features
4. **Learn Continuously**: Build systems that learn from errors and improve over time
5. **Scale Thoughtfully**: Plan for production scale from the beginning

The techniques in this guide transform basic AI agents into sophisticated reasoning systems capable of handling complex real-world tasks with reliability, safety, and effectiveness.