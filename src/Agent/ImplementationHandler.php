<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use Closure;
use Exception;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Prompts\PromptTemplates;
use HelgeSverre\Swarm\Task\Task;
use HelgeSverre\Swarm\Task\TaskManager;
use HelgeSverre\Swarm\Task\TaskStatus;
use Psr\Log\LoggerInterface;

/**
 * Implementation handler for tool-based requests
 *
 * Handles requests that require actual implementation work using tools.
 * Extracts tasks, creates execution plans, and coordinates tool usage.
 */
class ImplementationHandler implements RequestHandler
{
    public function __construct(
        private readonly ToolExecutor $toolExecutor,
        private readonly TaskManager $taskManager,
        private readonly ConversationBuffer $conversationBuffer,
        private readonly ?LoggerInterface $logger,
        private readonly Closure $progressCallback,
        private readonly Closure $llmCallback
    ) {}

    public function handle(string $input, array $classification, array $analysis): AgentResponse
    {
        $tasks = $this->extractTasks($input);

        if (empty($tasks)) {
            return new AgentResponse(
                "I understand you want me to implement something, but I couldn't identify specific tasks. Could you provide more details?",
                false
            );
        }

        $this->taskManager->addTasks($tasks);

        foreach ($this->taskManager->getTasks() as $task) {
            if ($task->status === TaskStatus::Pending) {
                $this->executeTaskWithMonitoring($task);
            }
        }

        return new AgentResponse('Tasks completed successfully', true, [
            'tasks_executed' => count($tasks),
            'classification' => $classification,
        ]);
    }

    protected function extractTasks(string $input): array
    {
        $this->reportProgress('extracting_tasks', [
            'phase' => 'analyzing_request',
            'input_length' => mb_strlen($input),
        ]);

        try {
            $context = $this->conversationBuffer->getOptimalContext(
                currentTask: $input,
                tokenBudget: 2000
            );

            $this->reportProgress('extracting_tasks', [
                'phase' => 'calling_ai',
                'context_size' => count($context),
            ]);

            $response = $this->callLLM(
                context: $context,
                systemPrompt: PromptTemplates::planningSystem(),
                userPrompt: PromptTemplates::extractTasks($input),
                options: [
                    'reasoning_effort' => 'medium',
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => $this->buildJsonSchema('task_extraction', [
                            'tasks' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'description' => ['type' => 'string'],
                                        'priority' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                                        'complexity' => ['type' => 'string', 'enum' => ['simple', 'moderate', 'complex']],
                                        'estimated_time' => ['type' => 'string'],
                                    ],
                                    'required' => ['description'],
                                ],
                            ],
                            'reasoning' => ['type' => 'string'],
                        ], required: ['tasks', 'reasoning']),
                    ],
                ],
            );

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger?->warning('JSON decode error in task extraction', [
                    'error' => json_last_error_msg(),
                    'response' => mb_substr($response, 0, 500),
                ]);

                return [];
            }

            if (! $data || ! isset($data['tasks']) || ! is_array($data['tasks'])) {
                $this->logger?->warning('Invalid task extraction response structure', [
                    'response' => mb_substr($response, 0, 500),
                ]);

                return [];
            }

            $tasks = $data['tasks'];
            $this->reportProgress('extracting_tasks', [
                'phase' => 'extraction_complete',
                'task_count' => count($tasks),
            ]);

            return $tasks;
        } catch (Exception $e) {
            $this->logger?->error('Task extraction failed', [
                'error' => $e->getMessage(),
                'input' => mb_substr($input, 0, 200),
            ]);

            return [];
        }
    }

    protected function executeTaskWithMonitoring(Task $task): void
    {
        $this->reportProgress('executing_task', [
            'task_id' => $task->id,
            'task_description' => $task->description,
            'status' => $task->status->value,
        ]);

        try {
            if ($task->status === TaskStatus::Pending) {
                $task = $this->planTask($task);
            }

            $this->executeTaskSteps($task);
        } catch (Exception $e) {
            $this->logger?->error('Task execution failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Plan a pending task and return the planned task
     */
    protected function planTask(Task $task): Task
    {
        $this->reportProgress('planning_task', [
            'task_id' => $task->id,
            'task_description' => $task->description,
            'phase' => 'analyzing_requirements',
        ]);

        $context = $this->conversationBuffer->getOptimalContext(
            currentTask: $task->description,
            tokenBudget: 1500
        );

        $contextStr = $this->formatContextString($context);

        $this->reportProgress('planning_task', [
            'task_id' => $task->id,
            'phase' => 'calling_ai',
        ]);

        $planResponse = $this->callLLM(
            context: $context,
            systemPrompt: PromptTemplates::planningSystem(),
            userPrompt: PromptTemplates::planTask(
                description: $task->description,
                context: $contextStr
            ),
            options: [
                'reasoning_effort' => 'medium',
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $this->buildJsonSchema('task_plan', [
                        'plan_summary' => ['type' => 'string'],
                        'steps' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'description' => ['type' => 'string'],
                                    'tool_needed' => ['type' => 'string'],
                                    'expected_outcome' => ['type' => 'string'],
                                ],
                                'required' => ['description'],
                            ],
                        ],
                        'estimated_complexity' => ['type' => 'string', 'enum' => ['simple', 'moderate', 'complex']],
                        'potential_issues' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ], required: ['plan_summary', 'steps']),
                ],
            ],
        );

        $planData = json_decode($planResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error in task planning: ' . json_last_error_msg());
        }

        if (! $planData || ! isset($planData['plan_summary'])) {
            $this->logger?->warning('Invalid task plan response structure', [
                'response' => mb_substr($planResponse, 0, 500),
            ]);
            throw new Exception('Invalid task plan response: missing plan_summary');
        }

        $steps = $planData['steps'] ?? [];

        $this->taskManager->planTask(
            taskId: $task->id,
            plan: $planData['plan_summary'],
            steps: $steps
        );

        $this->reportProgress('planning_task', [
            'task_id' => $task->id,
            'phase' => 'plan_complete',
            'step_count' => count($steps),
        ]);

        return $task->withPlan(
            plan: $planData['plan_summary'],
            steps: $steps
        );
    }

    /**
     * Execute task steps using tools
     */
    protected function executeTaskSteps(Task $task): void
    {
        $context = $this->conversationBuffer->getOptimalContext(
            currentTask: $task->description,
            tokenBudget: 1500
        );

        $toolLog = implode("\n", array_slice(
            $this->toolExecutor->getExecutionLog(), -5
        ));

        $availableTools = $this->toolExecutor->getToolSchemas();
        $toolNames = array_column(array_column($availableTools, 'function'), 'name');

        $response = $this->callLLM(
            context: $context,
            systemPrompt: PromptTemplates::executionSystem(
                toolDescriptions: implode(', ', $toolNames)
            ),
            userPrompt: PromptTemplates::executeTask(
                task: $task,
                context: $this->formatContextString($context),
                toolLog: $toolLog
            ),
            options: [
                'tools' => $availableTools,
                'reasoning_effort' => 'medium',
            ],
        );

        $this->logger?->info('Task execution completed', [
            'task_id' => $task->id,
            'response_length' => mb_strlen($response),
        ]);
    }

    /**
     * Call the LLM with context messages, system prompt, and user prompt
     */
    protected function callLLM(array $context, string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $messages = array_merge($context, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ]);

        return ($this->llmCallback)($messages, $options);
    }

    /**
     * Report progress through the callback
     */
    protected function reportProgress(string $operation, array $details): void
    {
        ($this->progressCallback)($operation, $details);
    }

    /**
     * Format conversation context as a string for prompt injection
     */
    protected function formatContextString(array $context): string
    {
        if (empty($context)) {
            return 'No prior context';
        }

        return json_encode(array_slice($context, -3), JSON_PRETTY_PRINT);
    }

    /**
     * Build a JSON schema wrapper for OpenAI structured output
     */
    protected function buildJsonSchema(string $name, array $properties, array $required = []): array
    {
        return [
            'name' => $name,
            'schema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
            ],
        ];
    }
}
