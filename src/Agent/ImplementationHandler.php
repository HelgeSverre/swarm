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
        // Extract and execute tasks using existing logic
        $tasks = $this->extractTasks($input);

        if (empty($tasks)) {
            return new AgentResponse(
                "I understand you want me to implement something, but I couldn't identify specific tasks. Could you provide more details?",
                false
            );
        }

        $this->taskManager->addTasks($tasks);

        // Execute tasks with enhanced monitoring
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
        ($this->progressCallback)('extracting_tasks', [
            'phase' => 'analyzing_request',
            'input_length' => mb_strlen($input),
        ]);

        try {
            $context = $this->conversationBuffer->getOptimalContext(
                currentTask: $input,
                tokenBudget: 2000
            );

            $prompt = PromptTemplates::extractTasks($input);
            $messages = array_merge($context, [
                ['role' => 'system', 'content' => PromptTemplates::planningSystem()],
                ['role' => 'user', 'content' => $prompt],
            ]);

            ($this->progressCallback)('extracting_tasks', [
                'phase' => 'calling_ai',
                'context_size' => count($context),
            ]);

            $response = ($this->llmCallback)($messages, [
                'reasoning_effort' => 'medium',
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $this->getTaskExtractionSchema(),
                ],
            ]);

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
            ($this->progressCallback)('extracting_tasks', [
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

    /**
     * Get JSON schema for task extraction
     */
    protected function getTaskExtractionSchema(): array
    {
        return [
            'name' => 'task_extraction',
            'schema' => [
                'type' => 'object',
                'properties' => [
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
                ],
                'required' => ['tasks', 'reasoning'],
            ],
        ];
    }

    protected function executeTaskWithMonitoring(Task $task): void
    {
        ($this->progressCallback)('executing_task', [
            'task_id' => $task->id,
            'task_description' => $task->description,
            'status' => $task->status->value,
        ]);

        try {
            // First, plan the task if not already planned
            if ($task->status === TaskStatus::Pending) {
                $this->planAndExecuteTask($task);
            } else {
                $this->executeExistingTask($task);
            }
        } catch (Exception $e) {
            $this->logger?->error('Task execution failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Task remains in current status - don't advance on failure
            throw $e;
        }
    }

    /**
     * Plan and execute a pending task
     */
    protected function planAndExecuteTask(Task $task): void
    {
        // Plan the task first
        ($this->progressCallback)('planning_task', [
            'task_id' => $task->id,
            'task_description' => $task->description,
            'phase' => 'analyzing_requirements',
        ]);

        $context = $this->conversationBuffer->getOptimalContext(
            currentTask: $task->description,
            tokenBudget: 1500
        );

        $contextStr = ! empty($context) ?
            json_encode(array_slice($context, -3), JSON_PRETTY_PRINT) : 'No prior context';

        $prompt = PromptTemplates::planTask(
            description: $task->description,
            context: $contextStr
        );

        $messages = array_merge($context, [
            ['role' => 'system', 'content' => PromptTemplates::planningSystem()],
            ['role' => 'user', 'content' => $prompt],
        ]);

        ($this->progressCallback)('planning_task', [
            'task_id' => $task->id,
            'phase' => 'calling_ai',
        ]);

        $planResponse = ($this->llmCallback)($messages, [
            'reasoning_effort' => 'medium',
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => $this->getTaskPlanSchema(),
            ],
        ]);

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

        // Update task with plan
        $plannedTask = $task->withPlan(
            plan: $planData['plan_summary'],
            steps: $planData['steps'] ?? []
        );

        $this->taskManager->planTask(
            taskId: $task->id,
            plan: $planData['plan_summary'],
            steps: $planData['steps'] ?? []
        );

        ($this->progressCallback)('planning_task', [
            'task_id' => $task->id,
            'phase' => 'plan_complete',
            'step_count' => count($planData['steps'] ?? []),
        ]);

        // Now execute the planned task
        $this->executeTaskSteps($plannedTask);
    }

    /**
     * Execute an already planned task
     */
    protected function executeExistingTask(Task $task): void
    {
        $this->executeTaskSteps($task);
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

        $contextStr = ! empty($context) ?
            json_encode(array_slice($context, -3), JSON_PRETTY_PRINT) : 'No prior context';

        $toolLog = implode("\n", array_slice(
            $this->toolExecutor->getExecutionLog(), -5
        ));

        $prompt = PromptTemplates::executeTask(
            task: $task,
            context: $contextStr,
            toolLog: $toolLog
        );

        // Get available tools for this request
        $availableTools = $this->toolExecutor->getToolSchemas();
        $toolNames = array_column(array_column($availableTools, 'function'), 'name');

        $messages = array_merge($context, [
            ['role' => 'system', 'content' => PromptTemplates::executionSystem(
                toolDescriptions: implode(', ', $toolNames)
            )],
            ['role' => 'user', 'content' => $prompt],
        ]);

        // Execute with tools available
        $response = ($this->llmCallback)($messages, [
            'tools' => $availableTools,
            'reasoning_effort' => 'medium',
        ]);

        // Task completion is handled by the task manager when tools complete successfully
        $this->logger?->info('Task execution completed', [
            'task_id' => $task->id,
            'response_length' => mb_strlen($response),
        ]);
    }

    /**
     * Get JSON schema for task planning
     */
    protected function getTaskPlanSchema(): array
    {
        return [
            'name' => 'task_plan',
            'schema' => [
                'type' => 'object',
                'properties' => [
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
                ],
                'required' => ['plan_summary', 'steps'],
            ],
        ];
    }
}
