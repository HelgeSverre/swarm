<?php

namespace HelgeSverre\Swarm\Agent;

use Exception;
use HelgeSverre\Swarm\Core\ToolRouter;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CodingAgent
{
    protected array $conversationHistory = [];

    public function __construct(
        protected readonly ToolRouter $toolRouter,
        protected readonly TaskManager $taskManager,
        protected readonly OpenAI\Contracts\ClientContract $llmClient,
        protected readonly ?LoggerInterface $logger = null,
        protected readonly string $model = 'gpt-4',
        protected readonly float $temperature = 0.7
    ) {}

    public function processRequest(string $userInput): AgentResponse
    {
        $this->logger?->info('Processing user request', ['input_length' => mb_strlen($userInput)]);
        $this->addToHistory('user', $userInput);

        // First, classify the request
        $classification = $this->classifyRequest($userInput);

        // Handle demonstration requests without tools
        if ($classification['request_type'] === 'demonstration' && ! $classification['requires_tools']) {
            return $this->handleDemonstration($userInput);
        }

        // Handle explanation requests without tools
        if ($classification['request_type'] === 'explanation') {
            return $this->handleExplanation($userInput);
        }

        // Handle general queries or conversations
        if ($classification['request_type'] === 'query' || $classification['request_type'] === 'conversation') {
            return $this->handleConversation($userInput);
        }

        // Only extract tasks for implementation or tool-requiring requests
        if ($classification['requires_tools']) {
            $tasks = $this->extractTasks($userInput);

            if (! empty($tasks)) {
                $this->taskManager->addTasks($tasks);

                // Plan each task
                foreach ($this->taskManager->getTasks() as $task) {
                    if ($task['status'] === 'pending') {
                        $this->planTask($task);
                    }
                }

                // Execute tasks one by one
                $taskResults = [];
                while ($currentTask = $this->taskManager->getNextTask()) {
                    $this->executeTask($currentTask);
                    $this->taskManager->completeCurrentTask();
                    $taskResults[] = $currentTask['description'];
                }

                // Generate a summary of what was done
                $summary = $this->generateTaskSummary($userInput, $taskResults);

                return AgentResponse::success($summary);
            }
        }

        // Default to conversation if no tasks were found
        return $this->handleConversation($userInput);
    }

    public function getStatus(): array
    {
        return [
            'tasks' => $this->taskManager->getTasks(),
            'current_task' => $this->taskManager->currentTask ?? null,
        ];
    }

    protected function getToolFunctions(): array
    {
        // Get schemas dynamically from registered tools
        return $this->toolRouter->getToolSchemas();
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
            $systemPrompt = 'You are a helpful coding assistant. Always return valid JSON when asked for JSON. ' .
                'Available tools are: read_file, write_file, find_files, search_content, bash. ' .
                'Use "bash" for terminal/command line operations.';
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
        $this->logger?->debug('Classifying request', ['input' => $input]);

        try {
            $systemPrompt = 'You are an expert at understanding user intent in coding requests. ' .
                'Classify whether the user wants you to show an example, actually implement something, ' .
                'explain a concept, or just have a conversation.';

            $messages = $this->buildMessagesWithHistory(
                "Classify this request: \"{$input}\"",
                $systemPrompt
            );

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
                                'request_type' => [
                                    'type' => 'string',
                                    'enum' => ['demonstration', 'implementation', 'explanation', 'query', 'conversation'],
                                    'description' => 'Type of request: demonstration (show example code), implementation (create/modify files), explanation (explain concept), query (ask for information), conversation (general chat)',
                                ],
                                'requires_tools' => [
                                    'type' => 'boolean',
                                    'description' => 'Whether this request requires using tools like writing files or running commands',
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
                            'required' => ['request_type', 'requires_tools', 'confidence', 'reasoning'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);

            $classification = json_decode($result->choices[0]->message->content, true);

            if (! $classification || ! isset($classification['request_type'])) {
                throw new RuntimeException('Invalid classification response');
            }

            $this->logger?->info('Request classified', [
                'type' => $classification['request_type'],
                'requires_tools' => $classification['requires_tools'],
                'confidence' => $classification['confidence'],
            ]);

            return $classification;
        } catch (Exception $e) {
            $this->logger?->error('Request classification failed', [
                'error' => $e->getMessage(),
                'input' => $input,
            ]);

            // Default to implementation if classification fails
            return [
                'request_type' => 'implementation',
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

        $prompt = "Extract tasks given in this input:\n\n" .
            "{$input}" .
            "\n\nIf there are specific tasks to do, extract them. Otherwise, treat the input as a general question or conversation.";

        $this->logger?->debug('Extracting tasks from input', ['prompt_length' => mb_strlen($prompt)]);

        try {
            $result = $this->llmClient->chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful coding assistant. Extract coding tasks from user input when they describe specific actions to take.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'functions' => [$extractTasksFunction],
                'function_call' => 'auto',
                'temperature' => $this->temperature,
            ]);

            $message = $result->choices[0]->message;

            if (isset($message->functionCall) && $message->functionCall->name === 'extract_tasks') {
                $arguments = json_decode($message->functionCall->arguments, true);
                $tasks = $arguments['tasks'] ?? [];
                $this->logger?->debug('Tasks extracted', ['task_count' => count($tasks)]);

                return $tasks;
            }
        } catch (Exception $e) {
            $this->logger?->error('Task extraction failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'input' => $input,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return [];
    }

    protected function callOpenAI(string $prompt, ?string $systemPrompt = null): string
    {
        $startTime = microtime(true);

        // Build messages array with conversation history
        $messages = $this->buildMessagesWithHistory($prompt, $systemPrompt);

        // Log the request at debug level
        $this->logger?->debug('OpenAI request', [
            'prompt_preview' => mb_substr($prompt, 0, 200) . (mb_strlen($prompt) > 200 ? '...' : ''),
            'prompt_length' => mb_strlen($prompt),
            'model' => $this->model,
            'temperature' => $this->temperature,
            'message_count' => count($messages),
        ]);

        try {
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
                'response_preview' => mb_substr($response, 0, 200) . (mb_strlen($response) > 200 ? '...' : ''),
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
        $systemPrompt = 'You are a helpful coding assistant. Use the provided functions to complete tasks. ' .
            'Remember the context from our previous conversation.';
        $messages = $this->buildMessagesWithHistory($prompt, $systemPrompt);

        $this->logger?->debug('OpenAI function call request', [
            'prompt_preview' => mb_substr($prompt, 0, 200) . (mb_strlen($prompt) > 200 ? '...' : ''),
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

    protected function planTask(array $task): void
    {
        $this->logger?->info('Planning task', ['task_id' => $task['id'], 'description' => $task['description']]);

        $context = $this->buildContext();

        try {
            $systemPrompt = 'You are an expert at planning coding tasks. Create a detailed plan with specific steps.';

            $messages = $this->buildMessagesWithHistory(
                "Plan how to execute this coding task:\n\n{$task['description']}\n\nContext:\n{$context}",
                $systemPrompt
            );

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
                                                'description' => 'Which tool to use (read_file, write_file, find_files, search_content, bash)',
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

            $this->taskManager->planTask($task['id'], $planData['plan_summary'], $steps);

            $this->logger?->debug('Task planned with structured output', [
                'task_id' => $task['id'],
                'steps_count' => count($steps),
                'complexity' => $planData['estimated_complexity'] ?? 'unknown',
            ]);
        } catch (Exception $e) {
            $this->logger?->error('Structured task planning failed, falling back', [
                'error' => $e->getMessage(),
                'task_id' => $task['id'],
            ]);

            // Fallback to regular planning
            $prompt = "Plan how to execute this coding task:\n\n{$task['description']}\n\nContext:\n{$context}\n\nReturn a plan and list of steps.";
            $planResponse = $this->callOpenAI($prompt);
            $this->taskManager->planTask($task['id'], $planResponse, []);
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

    protected function executeTask(array $task): void
    {
        $maxIterations = 10;
        $iteration = 0;

        $this->logger?->info("Executing task: {$task['description']}");

        while ($iteration < $maxIterations) {
            $context = $this->buildContext();
            $toolLog = $this->getRecentToolLog();

            $prompt = "Execute this task step by step:\n\n{$task['description']}\n\nPlan:\n{$task['plan']}\n\nContext:\n{$context}\n\nRecent tool results:\n{$toolLog}\n\nDecide what to do next to complete the task.";

            $toolCall = $this->callOpenAIWithFunctions($prompt, $this->getToolFunctions());

            if (! $toolCall) {
                break; // No tool call means task is complete
            }

            try {
                // Execute the tool
                $this->logger?->debug('Executing tool', [
                    'tool' => $toolCall['name'],
                    'params' => $toolCall['arguments'],
                ]);

                $result = $this->toolRouter->dispatch($toolCall['name'], $toolCall['arguments']);
                $this->addToHistory('tool', "Tool: {$toolCall['name']}\nParams: " . json_encode($toolCall['arguments']) . "\nResult: " . json_encode($result->toArray()));
            } catch (Exception $e) {
                $this->logger?->error('Tool execution failed during task', [
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'tool' => $toolCall['name'],
                    'params' => $toolCall['arguments'],
                    'task' => $task['description'],
                    'iteration' => $iteration,
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->addToHistory('error', $e->getMessage());
                break;
            }

            $iteration++;
        }

        $this->logger?->info('Task execution completed', [
            'task' => $task['description'],
            'iterations' => $iteration,
            'max_iterations' => $maxIterations,
        ]);
    }

    protected function getRecentToolLog(): string
    {
        $log = $this->toolRouter->getExecutionLog();
        $recent = array_slice($log, -5); // Last 5 tool calls

        return json_encode($recent, JSON_PRETTY_PRINT);
    }

    protected function handleDemonstration(string $userInput): AgentResponse
    {
        $this->logger?->info('Handling demonstration request');

        $systemPrompt = 'You are a helpful coding assistant. The user is asking for a code example or demonstration. ' .
            'Provide the requested code example in markdown format with proper syntax highlighting. ' .
            'Do NOT suggest creating files unless explicitly asked. Focus on showing the code example.';

        $response = $this->callOpenAI($userInput, $systemPrompt);

        return AgentResponse::success($response);
    }

    protected function handleExplanation(string $userInput): AgentResponse
    {
        $this->logger?->info('Handling explanation request');

        $systemPrompt = 'You are a helpful coding assistant. The user is asking for an explanation of a concept. ' .
            'Provide a clear, educational explanation. Use code examples in markdown if helpful, ' .
            'but focus on explaining the concept rather than implementing anything.';

        $response = $this->callOpenAI($userInput, $systemPrompt);

        return AgentResponse::success($response);
    }

    protected function handleConversation(string $userInput): AgentResponse
    {
        $this->logger?->info('Handling conversation/query');

        $systemPrompt = 'You are a helpful coding assistant engaged in conversation with the user. ' .
            'Provide helpful, informative responses. Remember the context from our previous conversation. ' .
            'If the user asks for code examples, provide them in markdown format.';

        $response = $this->callOpenAI($userInput, $systemPrompt);

        return AgentResponse::success($response);
    }

    protected function generateTaskSummary(string $userInput, array $taskResults): string
    {
        // Get the recent history to understand what was done
        $recentHistory = $this->getRecentHistory();
        $toolLog = $this->getRecentToolLog();

        $prompt = "The user asked: {$userInput}\n\n";
        $prompt .= "The following tasks were completed:\n";
        foreach ($taskResults as $task) {
            $prompt .= "- {$task}\n";
        }
        $prompt .= "\nRecent actions taken:\n{$toolLog}\n\n";
        $prompt .= "Recent conversation:\n{$recentHistory}\n\n";
        $prompt .= 'Provide a helpful response to the user summarizing what was done, focusing on the actual results and outcomes. Be specific about what files were created, modified, or what actions were taken.';

        return $this->callOpenAI($prompt);
    }
}
