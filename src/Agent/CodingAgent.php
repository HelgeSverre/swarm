<?php

namespace HelgeSverre\Swarm\Agent;

use Closure;
use Exception;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Enums\Agent\RequestType;
use HelgeSverre\Swarm\Prompts\PromptTemplates;
use HelgeSverre\Swarm\Task\Task;
use HelgeSverre\Swarm\Task\TaskManager;
use HelgeSverre\Swarm\Task\TaskStatus;
use OpenAI;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CodingAgent
{
    protected array $conversationHistory = [];

    protected ?Closure $progressCallback = null;

    public function __construct(
        protected readonly ToolExecutor $toolExecutor,
        protected readonly TaskManager $taskManager,
        protected readonly OpenAI\Contracts\ClientContract $llmClient,
        protected readonly ?LoggerInterface $logger = null,
        protected readonly string $model = 'gpt-4',
        protected readonly float $temperature = 0.7
    ) {}

    /**
     * Set a callback to report progress during processing
     */
    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    public function processRequest(string $userInput): AgentResponse
    {
        $this->logger?->info('Processing user request', [
            'input_length' => mb_strlen($userInput),
            'conversation_length' => count($this->conversationHistory),
        ]);
        $this->addToHistory('user', $userInput);

        // First, classify the request
        $this->reportProgress('classifying', ['message' => 'Analyzing request type...']);
        $classification = $this->classifyRequest($userInput);

        $this->logger?->debug('Request classified', [
            'type' => $classification['request_type']->value,
            'requires_tools' => $classification['requires_tools'],
            'confidence' => $classification['confidence'],
        ]);

        // Handle demonstration requests without tools
        if ($classification['request_type'] === RequestType::Demonstration && ! $classification['requires_tools']) {
            return $this->handleDemonstration($userInput);
        }

        // Handle explanation requests without tools
        if ($classification['request_type'] === RequestType::Explanation) {
            return $this->handleExplanation($userInput);
        }

        // Handle internal task management (like "show my tasks", "clear tasks", etc.)
        if ($classification['is_internal_task_management'] ?? false) {
            return $this->handleInternalTaskManagement($userInput);
        }

        // Check if request requires tools first
        if ($classification['requires_tools'] || $classification['request_type'] === RequestType::Implementation) {
            $this->reportProgress('extracting_tasks', ['message' => 'Identifying tasks to complete...']);
            $tasks = $this->extractTasks($userInput);

            if (! empty($tasks)) {
                $this->logger?->info('Tasks extracted', ['count' => count($tasks)]);
                $this->taskManager->addTasks($tasks);

                // Get the actual Task objects from the TaskManager
                $taskObjects = $this->taskManager->getTasks();

                // Report extracted tasks with full details for UI display
                $this->reportProgress('extracting_tasks', [
                    'phase' => 'extraction_complete',
                    'task_count' => count($taskObjects),
                    'tasks' => array_map(fn (Task $task) => [
                        'id' => $task->id,
                        'description' => $task->description,
                        'status' => $task->status->value,
                        'plan' => $task->plan,
                        'steps' => $task->steps,
                    ], $taskObjects),
                ]);

                // Plan each task
                foreach ($this->taskManager->getTasks() as $task) {
                    if ($task->status === TaskStatus::Pending) {
                        $this->reportProgress('planning_task', [
                            'message' => 'Planning: ' . $task->description,
                            'task_id' => $task->id,
                        ]);
                        $this->planTask($task);
                    }
                }

                // Execute tasks one by one
                $taskResults = [];
                while ($currentTask = $this->taskManager->getNextTask()) {
                    $this->reportProgress('executing_task', [
                        'message' => 'Starting task execution',
                        'task_id' => $currentTask->id,
                        'task_description' => $currentTask->description,
                        'total_steps' => count($currentTask->steps),
                        'current_step' => 0,
                    ]);
                    $this->executeTask($currentTask);
                    $this->taskManager->completeCurrentTask();
                    $taskResults[] = $currentTask->description;
                }

                // Generate a summary of what was done
                $this->reportProgress('generating_summary', ['message' => 'Generating summary...']);
                $summary = $this->generateTaskSummary($userInput, $taskResults);

                return AgentResponse::success($summary);
            }
        }

        // Handle general queries or conversations without tools
        if ($classification['request_type'] === RequestType::Query || $classification['request_type'] === RequestType::Conversation) {
            return $this->handleConversation($userInput);
        }

        // Default to conversation if no other handler matched
        return $this->handleConversation($userInput);
    }

    public function getStatus(): array
    {
        return [
            'tasks' => array_map(fn ($task) => $task->toArray(), $this->taskManager->getTasks()),
            'current_task' => $this->taskManager->currentTask?->toArray(),
        ];
    }

    /**
     * Get the task manager instance
     */
    public function getTaskManager(): TaskManager
    {
        return $this->taskManager;
    }

    /**
     * Get recent conversation history for display
     */
    public function getConversationHistory(int $limit = 10): array
    {
        // Get recent history, filtering out tool responses for cleaner display
        $history = array_slice($this->conversationHistory, -$limit);

        return array_map(function ($entry) {
            return [
                'role' => $entry['role'],
                'content' => $this->truncateContent($entry['content']),
                'timestamp' => $entry['timestamp'],
            ];
        }, array_filter($history, function ($entry) {
            // Include user, assistant messages, but not tool or error messages
            return in_array($entry['role'], ['user', 'assistant']);
        }));
    }

    /**
     * Set conversation history (for restoring from saved state)
     */
    public function setConversationHistory(array $history): void
    {
        $this->conversationHistory = $history;
    }

    /**
     * Truncate content for display purposes
     */
    protected function truncateContent(string $content, int $maxLength = 2000): string
    {
        if (mb_strlen($content) <= $maxLength) {
            return $content;
        }

        return mb_substr($content, 0, $maxLength - 3) . '...';
    }

    /**
     * Report progress to the callback if set
     */
    protected function reportProgress(string $operation, array $details = []): void
    {
        if ($this->progressCallback) {
            call_user_func($this->progressCallback, $operation, $details);
        }
    }

    /**
     * Get a preview of tool parameters for display
     */
    protected function getParamsPreview(array $params): string
    {
        if (empty($params)) {
            return 'No parameters';
        }

        $preview = [];
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                $preview[$key] = mb_strlen($value) > 50 ? mb_substr($value, 0, 50) . '...' : $value;
            } elseif (is_array($value)) {
                $preview[$key] = '[' . count($value) . ' items]';
            } else {
                $preview[$key] = $value;
            }
        }

        return json_encode($preview);
    }

    protected function getToolFunctions(): array
    {
        // Get schemas dynamically from registered tools
        return $this->toolExecutor->getToolSchemas();
    }

    protected function addToHistory(string $role, string $content): void
    {
        $this->conversationHistory[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time(),
        ];

        // Keep history manageable (last 50 messages)
        if (count($this->conversationHistory) > 50) {
            $this->conversationHistory = array_slice($this->conversationHistory, -50);
        }
    }

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

        // Include recent history (last 20 messages to manage token usage)
        $recentHistory = array_slice($historyToInclude, -20);
        foreach ($recentHistory as $msg) {
            // Skip tool messages in history as they need special formatting
            if ($msg['role'] === 'tool') {
                continue;
            }

            // Skip error messages in history
            if ($msg['role'] === 'error') {
                continue;
            }

            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // Add current user message
        $messages[] = ['role' => 'user', 'content' => $currentPrompt];

        return $messages;
    }

    protected function classifyRequest(string $input): array
    {
        $this->logger?->debug('Classifying request', [
            'input' => $input,
            'input_length' => mb_strlen($input),
        ]);

        // Report we're starting classification
        $this->reportProgress('classifying', [
            'message' => 'Analyzing request type...',
            'phase' => 'understanding_intent',
            'input_preview' => mb_substr($input, 0, 100) . (mb_strlen($input) > 100 ? '...' : ''),
        ]);

        try {
            $systemPrompt = PromptTemplates::classificationSystem();

            $messages = $this->buildMessagesWithHistory(
                PromptTemplates::classifyRequest($input),
                $systemPrompt
            );

            // Report we're calling AI
            $this->reportProgress('classifying', [
                'message' => 'Asking AI to classify request...',
                'phase' => 'calling_ai',
                'context_messages' => count($messages),
            ]);

            $startTime = microtime(true);

            $result = $this->llmClient->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.3, // Lower temperature for more consistent classification
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'request_classification',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'chain_of_thought' => [
                                    'type' => 'string',
                                    'description' => 'Step-by-step reasoning about the user request',
                                ],
                                'request_type' => [
                                    'type' => 'string',
                                    'enum' => RequestType::values(),
                                    'description' => 'Type of request: demonstration (show example code), implementation (create/modify files), explanation (explain concept), query (ask for information), conversation (general chat)',
                                ],
                                'requires_tools' => [
                                    'type' => 'boolean',
                                    'description' => 'Whether this request requires using tools like writing files or running commands',
                                ],
                                'is_internal_task_management' => [
                                    'type' => 'boolean',
                                    'description' => 'Whether this is about managing internal tasks (not file creation)',
                                ],
                                'confidence' => [
                                    'type' => 'number',
                                    'minimum' => 0,
                                    'maximum' => 1,
                                    'description' => 'Confidence level in the classification (0-1)',
                                ],
                                'reasoning' => [
                                    'type' => 'string',
                                    'description' => 'Brief explanation of why this classification was chosen',
                                ],
                            ],
                            'required' => ['chain_of_thought', 'request_type', 'requires_tools', 'is_internal_task_management', 'confidence', 'reasoning'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);

            $duration = microtime(true) - $startTime;

            // Log LLM usage
            if (isset($result->usage)) {
                $this->logger?->debug('LLM usage', [
                    'operation' => 'classify_request',
                    'model' => $this->model,
                    'prompt_tokens' => $result->usage->promptTokens,
                    'completion_tokens' => $result->usage->completionTokens,
                    'total_tokens' => $result->usage->totalTokens,
                    'duration_ms' => round($duration * 1000, 2),
                ]);
            }

            $classification = json_decode($result->choices[0]->message->content, true);

            if (! $classification || ! isset($classification['request_type'])) {
                throw new RuntimeException('Invalid classification response');
            }

            // Convert request_type string to enum
            $requestType = RequestType::fromString($classification['request_type']);
            if ($requestType === null) {
                throw new RuntimeException('Invalid request type: ' . $classification['request_type']);
            }
            $classification['request_type'] = $requestType;

            $this->logger?->info('Request classified', [
                'type' => $classification['request_type']->value,
                'requires_tools' => $classification['requires_tools'],
                'is_internal_task_management' => $classification['is_internal_task_management'] ?? false,
                'confidence' => $classification['confidence'],
                'reasoning' => $classification['reasoning'],
                'chain_of_thought' => $classification['chain_of_thought'] ?? '',
            ]);

            // Report classification complete with reasoning
            $this->reportProgress('classifying', [
                'message' => 'Classification complete',
                'phase' => 'classification_complete',
                'type' => $classification['request_type']->value,
                'confidence' => $classification['confidence'],
                'reasoning' => $classification['reasoning'] ?? 'No reasoning provided',
                'chain_of_thought' => $classification['chain_of_thought'] ?? '',
                'requires_tools' => $classification['requires_tools'],
                'elapsed_time' => round($duration, 2),
            ]);

            return $classification;
        } catch (Exception $e) {
            $this->logger?->error('Request classification failed', [
                'error' => $e->getMessage(),
                'input' => $input,
            ]);

            // Default to implementation if classification fails
            return [
                'request_type' => RequestType::Implementation,
                'requires_tools' => true,
                'confidence' => 0.5,
                'reasoning' => 'Classification failed, defaulting to implementation',
            ];
        }
    }

    protected function extractTasks(string $input): array
    {
        // Define a function for extracting tasks
        $extractTasksFunction = [
            'name' => 'extract_tasks',
            'description' => 'Extract coding tasks from natural language input',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'tasks' => [
                        'type' => 'array',
                        'description' => 'Array of extracted tasks',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'description' => [
                                    'type' => 'string',
                                    'description' => 'The task description',
                                ],
                            ],
                            'required' => ['description'],
                        ],
                    ],
                ],
                'required' => ['tasks'],
            ],
        ];

        $prompt = PromptTemplates::extractTasks($input);

        $this->logger?->debug('Extracting tasks from input', [
            'input_length' => mb_strlen($input),
            'prompt_length' => mb_strlen($prompt),
        ]);

        try {
            $startTime = microtime(true);

            $result = $this->llmClient->chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => PromptTemplates::defaultSystem()],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'functions' => [$extractTasksFunction],
                'function_call' => 'auto',
                'temperature' => $this->temperature,
            ]);

            $duration = microtime(true) - $startTime;

            // Log LLM usage
            if (isset($result->usage)) {
                $this->logger?->debug('LLM usage', [
                    'operation' => 'extract_tasks',
                    'model' => $this->model,
                    'prompt_tokens' => $result->usage->promptTokens,
                    'completion_tokens' => $result->usage->completionTokens,
                    'total_tokens' => $result->usage->totalTokens,
                    'duration_ms' => round($duration * 1000, 2),
                ]);
            }

            $message = $result->choices[0]->message;

            if (isset($message->functionCall) && $message->functionCall->name === 'extract_tasks') {
                $arguments = json_decode($message->functionCall->arguments, true);
                $tasks = $arguments['tasks'] ?? [];

                $this->logger?->info('Tasks extracted', [
                    'count' => count($tasks),
                    'tasks' => array_map(fn ($t) => $t['description'], $tasks),
                ]);

                return $tasks;
            }

            return [];
        } catch (Exception $e) {
            $this->logger?->error('Task extraction failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'input' => $input,
            ]);

            return [];
        }
    }

    protected function callOpenAI(string $prompt, ?string $systemPrompt = null): string
    {
        $startTime = microtime(true);

        // Build messages array with conversation history
        $messages = $this->buildMessagesWithHistory($prompt, $systemPrompt);

        // Log the request at debug level
        $this->logger?->debug('OpenAI request', [
            'prompt_preview' => mb_substr($prompt, 0, 500) . (mb_strlen($prompt) > 500 ? '...' : ''),
            'prompt_length' => mb_strlen($prompt),
            'model' => $this->model,
            'temperature' => $this->temperature,
            'message_count' => count($messages),
        ]);

        try {
            $this->reportProgress('calling_openai', ['message' => 'Calling OpenAI API...']);
            $result = $this->llmClient->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
            ]);

            $response = $result->choices[0]->message->content;

            // Store assistant response in history
            $this->addToHistory('assistant', $response);

            // Log the response
            $this->logger?->info('OpenAI response', [
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'prompt_tokens' => $result->usage->promptTokens ?? 0,
                'completion_tokens' => $result->usage->completionTokens ?? 0,
                'total_tokens' => $result->usage->totalTokens ?? 0,
                'response_preview' => mb_substr($response, 0, 500) . (mb_strlen($response) > 500 ? '...' : ''),
                'response_length' => mb_strlen($response),
                'finish_reason' => $result->choices[0]->finishReason ?? 'unknown',
            ]);

            // Log full request/response at debug level
            $this->logger?->debug('OpenAI full exchange', [
                'request' => $prompt,
                'response' => $response,
                'full_messages' => $messages,
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger?->error('OpenAI API call failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'model' => $this->model,
                'prompt_length' => mb_strlen($prompt),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception('OpenAI API error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function callOpenAIWithFunctions(string $prompt, array $functions): ?array
    {
        $startTime = microtime(true);

        // Build messages with history
        $systemPrompt = PromptTemplates::executionSystem($this->toolExecutor->getToolDescriptions());
        $messages = $this->buildMessagesWithHistory($prompt, $systemPrompt);

        $this->logger?->debug('OpenAI function call request', [
            'prompt_preview' => mb_substr($prompt, 0, 500) . (mb_strlen($prompt) > 500 ? '...' : ''),
            'prompt_length' => mb_strlen($prompt),
            'model' => $this->model,
            'temperature' => $this->temperature,
            'functions_count' => count($functions),
            'message_count' => count($messages),
        ]);

        try {
            $result = $this->llmClient->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'functions' => $functions,
                'function_call' => 'auto',
                'temperature' => $this->temperature,
            ]);

            $message = $result->choices[0]->message;

            // Check if the model made a function call
            if (isset($message->functionCall)) {
                $functionCall = $message->functionCall;

                // Store the assistant's function call in history
                $this->addToHistory('assistant', json_encode([
                    'function_call' => [
                        'name' => $functionCall->name,
                        'arguments' => $functionCall->arguments,
                    ],
                ]));

                $this->logger?->info('OpenAI function call response', [
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'function_name' => $functionCall->name,
                    'arguments' => $functionCall->arguments,
                ]);

                return [
                    'name' => $functionCall->name,
                    'arguments' => json_decode($functionCall->arguments, true),
                ];
            }

            // No function call, store regular response
            if ($message->content) {
                $this->addToHistory('assistant', $message->content);
            }

            // No function call, task might be complete
            $this->logger?->info('OpenAI response without function call', [
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'response' => $message->content,
            ]);

            return null;
        } catch (Exception $e) {
            $this->logger?->error('OpenAI function call failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'model' => $this->model,
                'functions_count' => count($functions),
                'prompt_length' => mb_strlen($prompt),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception('OpenAI API error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function planTask(Task $task): void
    {
        $this->logger?->info('Planning task', ['task_id' => $task->id, 'description' => $task->description]);

        // Report starting planning
        $this->reportProgress('planning_task', [
            'message' => 'Planning: ' . $task->description,
            'phase' => 'analyzing_requirements',
            'task_id' => $task->id,
            'task_description' => $task->description,
            'complexity_assessment' => 'Determining required tools and steps...',
        ]);

        $context = $this->buildContext();

        try {
            $systemPrompt = PromptTemplates::planningSystem();

            $messages = $this->buildMessagesWithHistory(
                PromptTemplates::planTask($task->description, $context),
                $systemPrompt
            );

            // Report calling AI for planning
            $this->reportProgress('planning_task', [
                'message' => 'Creating execution plan...',
                'phase' => 'calling_ai',
                'task_id' => $task->id,
            ]);

            $result = $this->llmClient->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.5, // Lower temperature for more focused planning
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'task_plan',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'plan_summary' => [
                                    'type' => 'string',
                                    'description' => 'High-level summary of the plan',
                                ],
                                'steps' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'description' => [
                                                'type' => 'string',
                                                'description' => 'What needs to be done in this step',
                                            ],
                                            'tool_needed' => [
                                                'type' => 'string',
                                                'description' => 'Which tool to use (read_file, write_file, grep, bash)',
                                            ],
                                            'expected_outcome' => [
                                                'type' => 'string',
                                                'description' => 'What we expect to achieve with this step',
                                            ],
                                        ],
                                        'required' => ['description', 'tool_needed'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                                'estimated_complexity' => [
                                    'type' => 'string',
                                    'enum' => ['simple', 'moderate', 'complex'],
                                    'description' => 'Overall complexity of the task',
                                ],
                                'potential_issues' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                    'description' => 'Potential issues or edge cases to watch for',
                                ],
                            ],
                            'required' => ['plan_summary', 'steps'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);

            $planData = json_decode($result->choices[0]->message->content, true);

            if (! $planData || ! isset($planData['plan_summary'], $planData['steps'])) {
                throw new RuntimeException('Invalid plan structure');
            }

            // Store assistant's plan in history
            $this->addToHistory('assistant', json_encode($planData));

            // Extract steps for task manager
            $steps = array_map(function ($step) {
                return $step['description'];
            }, $planData['steps']);

            $this->taskManager->planTask($task->id, $planData['plan_summary'], $steps);

            $this->logger?->debug('Task planned with structured output', [
                'task_id' => $task->id,
                'steps_count' => count($steps),
                'complexity' => $planData['estimated_complexity'] ?? 'unknown',
            ]);

            // Report planning complete
            $this->reportProgress('planning_task', [
                'message' => 'Plan created successfully',
                'phase' => 'plan_complete',
                'task_id' => $task->id,
                'plan_summary' => $planData['plan_summary'],
                'step_count' => count($steps),
                'estimated_complexity' => $planData['estimated_complexity'] ?? 'unknown',
                'tools_needed' => array_unique(array_map(fn ($s) => $s['tool_needed'] ?? 'unknown', $planData['steps'])),
            ]);
        } catch (Exception $e) {
            $this->logger?->error('Structured task planning failed, falling back', [
                'error' => $e->getMessage(),
                'task_id' => $task->id,
            ]);

            // Fallback to regular planning
            $prompt = PromptTemplates::planTaskFallback($task->description, $context);
            $planResponse = $this->callOpenAI($prompt);
            $this->taskManager->planTask($task->id, $planResponse, []);
        }
    }

    protected function buildContext(): string
    {
        // Build current project context
        $context = 'Current directory: ' . getcwd() . "\n";
        $context .= "Recent conversation:\n" . $this->getRecentHistory() . "\n";

        return $context;
    }

    protected function getRecentHistory(): string
    {
        $recent = array_slice($this->conversationHistory, -10);

        return implode("\n", array_map(function ($msg) {
            return "{$msg['role']}: {$msg['content']}";
        }, $recent));
    }

    protected function executeTask(Task $task): void
    {
        $maxIterations = 10;
        $iteration = 0;

        // Track step progress using task steps or iterations
        $totalSteps = ! empty($task->steps) ? count($task->steps) : $maxIterations;
        $currentStepIndex = 0;

        $this->logger?->info('Executing task', [
            'task_id' => $task->id,
            'description' => $task->description,
            'status' => $task->status->value,
            'steps' => $totalSteps,
        ]);

        while ($iteration < $maxIterations) {
            $this->logger?->debug('Task iteration', ['iteration' => $iteration + 1]);

            $context = $this->buildContext();
            $toolLog = $this->getRecentToolLog();

            $prompt = PromptTemplates::executeTask($task, $context, $toolLog);

            $startTime = microtime(true);
            $toolCall = $this->callOpenAIWithFunctions($prompt, $this->getToolFunctions());
            $duration = microtime(true) - $startTime;

            $this->logger?->debug('LLM tool selection', [
                'task_id' => $task->id,
                'duration_ms' => round($duration * 1000, 2),
                'tool_selected' => $toolCall ? $toolCall['name'] : 'none',
            ]);

            if (! $toolCall) {
                $this->logger?->info('Task completed - no more tools needed');
                break; // No tool call means task is complete
            }

            // Increment step tracking
            $currentStepIndex = min($currentStepIndex + 1, $totalSteps);

            // Get step description if available
            $stepDescription = '';
            if (! empty($task->steps) && isset($task->steps[$currentStepIndex - 1])) {
                $stepDescription = $task->steps[$currentStepIndex - 1];
            } else {
                // Generate a description based on the tool being used
                $stepDescription = $this->generateStepDescription($toolCall['name'], $toolCall['arguments']);
            }

            // Report task execution progress
            $this->reportProgress('executing_task', [
                'task_id' => $task->id,
                'task_description' => $task->description,
                'current_step' => $currentStepIndex,
                'total_steps' => $totalSteps,
                'step_description' => $stepDescription,
                'current_tool' => $toolCall['name'],
            ]);

            try {
                // Report tool execution starting
                $this->reportProgress('executing_tool', [
                    'message' => "Running {$toolCall['name']}...",
                    'phase' => 'preparing',
                    'task_id' => $task->id,
                    'tool_name' => $toolCall['name'],
                    'tool_index' => $iteration + 1,
                    'tool_total' => $maxIterations,
                    'params_preview' => $this->getParamsPreview($toolCall['arguments']),
                ]);

                // Execute the tool
                $this->logger?->debug('Executing tool', [
                    'tool' => $toolCall['name'],
                    'params' => $toolCall['arguments'],
                    'iteration' => $iteration + 1,
                ]);

                $toolStartTime = microtime(true);
                $result = $this->toolExecutor->dispatch($toolCall['name'], $toolCall['arguments']);
                $toolDuration = microtime(true) - $toolStartTime;

                $this->logger?->debug('Tool execution complete', [
                    'tool' => $toolCall['name'],
                    'success' => $result->isSuccess(),
                ]);

                // Report tool execution complete
                $this->reportProgress('executing_tool', [
                    'message' => "Tool {$toolCall['name']} completed",
                    'phase' => 'completed',
                    'task_id' => $task->id,
                    'tool_name' => $toolCall['name'],
                    'success' => $result->isSuccess(),
                    'execution_time' => round($toolDuration, 2),
                ]);

                $this->addToHistory('tool', "Tool: {$toolCall['name']}\nParams: " . json_encode($toolCall['arguments']) . "\nResult: " . json_encode($result->toArray()));
            } catch (Exception $e) {
                $this->logger?->error('Tool execution failed during task', [
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'tool' => $toolCall['name'],
                    'params' => $toolCall['arguments'],
                    'task' => $task->description,
                    'iteration' => $iteration,
                ]);

                $this->addToHistory('error', $e->getMessage());
                break;
            }

            $iteration++;
        }

        $this->logger?->info('Task execution completed', [
            'task' => $task->description,
            'iterations' => $iteration,
            'max_iterations' => $maxIterations,
        ]);
    }

    protected function getRecentToolLog(): string
    {
        $log = $this->toolExecutor->getExecutionLog();
        $recent = array_slice($log, -5); // Last 5 tool calls

        return json_encode($recent, JSON_PRETTY_PRINT);
    }

    protected function handleDemonstration(string $userInput): AgentResponse
    {
        $this->logger?->info('Handling demonstration request');

        $systemPrompt = PromptTemplates::demonstrationSystem();

        $response = $this->callOpenAI($userInput, $systemPrompt);

        return AgentResponse::success($response);
    }

    protected function handleExplanation(string $userInput): AgentResponse
    {
        $this->logger?->info('Handling explanation request');

        $systemPrompt = PromptTemplates::explanationSystem();

        $response = $this->callOpenAI($userInput, $systemPrompt);

        return AgentResponse::success($response);
    }

    protected function handleConversation(string $userInput): AgentResponse
    {
        $this->logger?->info('Handling conversation/query');

        $systemPrompt = PromptTemplates::conversationSystem($this->toolExecutor->getToolDescriptions());

        $response = $this->callOpenAI($userInput, $systemPrompt);

        return AgentResponse::success($response);
    }

    protected function generateTaskSummary(string $userInput, array $taskResults): string
    {
        // Get the recent history to understand what was done
        $recentHistory = $this->getRecentHistory();
        $toolLog = $this->getRecentToolLog();

        $prompt = PromptTemplates::generateSummary($userInput, $taskResults, $recentHistory, $toolLog);

        return $this->callOpenAI($prompt);
    }

    /**
     * Handle internal task management requests
     */
    protected function handleInternalTaskManagement(string $userInput): AgentResponse
    {
        $this->logger?->info('Handling internal task management request');

        // Get current tasks
        $tasks = $this->taskManager->getTasks();
        $taskHistory = $this->taskManager->getTaskHistory();

        // Analyze what the user wants to do with tasks
        $systemPrompt = 'You are helping the user manage their internal task list. ' .
            "Available operations: view tasks, add tasks, clear completed tasks, view history.\n\n" .
            'Current active tasks: ' . count($tasks) . "\n" .
            'Tasks in history: ' . count($taskHistory);

        $prompt = "The user said: \"{$userInput}\"\n\n" .
            "Current tasks:\n";

        if (empty($tasks)) {
            $prompt .= "(No active tasks)\n";
        } else {
            foreach ($tasks as $index => $task) {
                $prompt .= ($index + 1) . ". [{$task->status->value}] {$task->description}\n";
            }
        }

        $prompt .= "\nBased on their request, provide a helpful response about their task list. " .
            'If they want to add tasks, acknowledge it but explain that tasks are extracted from implementation requests. ' .
            'If they want to see completed tasks, mention the history contains ' . count($taskHistory) . ' completed tasks.';

        $response = $this->callOpenAI($prompt, $systemPrompt);

        // If user wants to clear completed tasks, do it
        if (mb_stripos($userInput, 'clear') !== false && mb_stripos($userInput, 'completed') !== false) {
            $cleared = $this->taskManager->clearCompletedTasks();
            if (count($cleared) > 0) {
                $response .= "\n\n✓ Cleared " . count($cleared) . ' completed tasks from the active list.';
            }
        }

        return AgentResponse::success($response);
    }

    /**
     * Generate a user-friendly step description based on tool usage
     */
    protected function generateStepDescription(string $toolName, array $arguments): string
    {
        return match ($toolName) {
            'read_file' => 'Reading ' . ($arguments['path'] ?? 'file'),
            'write_file' => 'Writing to ' . ($arguments['path'] ?? 'file'),
            'grep' => 'Searching for ' . ($arguments['pattern'] ?? 'pattern'),
            'find_files' => 'Finding files matching ' . ($arguments['pattern'] ?? 'pattern'),
            'bash' => 'Running command: ' . ($arguments['command'] ?? 'command'),
            default => 'Using ' . $toolName,
        };
    }
}
