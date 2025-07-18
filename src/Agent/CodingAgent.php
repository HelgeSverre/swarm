<?php

namespace HelgeSverre\Swarm\Agent;

use Exception;
use HelgeSverre\Swarm\Router\ToolRouter;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI;
use Psr\Log\LoggerInterface;

class CodingAgent
{
    protected ToolRouter $toolRouter;

    protected TaskManager $taskManager;

    protected array $conversationHistory = [];

    protected OpenAI\Client $llmClient;

    protected ?LoggerInterface $logger;

    public function __construct(
        ToolRouter $toolRouter,
        TaskManager $taskManager,
        OpenAI\Client $llmClient,
        ?LoggerInterface $logger = null
    ) {
        $this->toolRouter = $toolRouter;
        $this->taskManager = $taskManager;
        $this->llmClient = $llmClient;
        $this->logger = $logger;
    }

    public function processRequest(string $userInput): AgentResponse
    {
        $this->logger?->info('Processing user request', ['input_length' => mb_strlen($userInput)]);
        $this->addToHistory('user', $userInput);

        // First, try to extract tasks from the input
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
            while ($currentTask = $this->taskManager->getNextTask()) {
                $this->executeTask($currentTask);
                $this->taskManager->completeCurrentTask();
            }

            return AgentResponse::success('All tasks completed successfully!');
        }

        // Handle as a regular conversation
        return $this->handleConversation($userInput);
    }

    public function getStatus(): array
    {
        return [
            'tasks' => $this->taskManager->getTasks(),
            'current_task' => $this->taskManager->currentTask ?? null,
        ];
    }

    protected function addToHistory(string $role, string $content): void
    {
        $this->conversationHistory[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time(),
        ];
    }

    protected function extractTasks(string $input): array
    {
        // Use AI to extract structured tasks from natural language
        $prompt = "Extract coding tasks from this input. Return as JSON array with 'description' field for each task:\n\n{$input}";

        $this->logger?->debug('Extracting tasks from input', ['prompt_length' => mb_strlen($prompt)]);
        $response = $this->callOpenAI($prompt);

        $decoded = json_decode($response, true);
        $taskCount = is_array($decoded) ? count($decoded) : 0;
        $this->logger?->debug('Tasks extracted', ['task_count' => $taskCount]);

        return is_array($decoded) ? $decoded : [];
    }

    protected function callOpenAI(string $prompt): string
    {
        $startTime = microtime(true);

        // Log the request at debug level
        $this->logger?->debug('OpenAI request', [
            'prompt_preview' => mb_substr($prompt, 0, 200) . (mb_strlen($prompt) > 200 ? '...' : ''),
            'prompt_length' => mb_strlen($prompt),
            'model' => 'gpt-4',
            'temperature' => 0.7,
        ]);

        try {
            $result = $this->llmClient->chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful coding assistant. Always return valid JSON when asked for JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);

            $response = $result->choices[0]->message->content;

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
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger?->error('OpenAI error: ' . $e->getMessage(), [
                'duration' => round((microtime(true) - $startTime) * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception('OpenAI API error: ' . $e->getMessage());
        }
    }

    protected function planTask(array $task): void
    {
        $this->logger?->info('Planning task', ['task_id' => $task['id'], 'description' => $task['description']]);

        $context = $this->buildContext();
        $prompt = "Plan how to execute this coding task:\n\n{$task['description']}\n\nContext:\n{$context}\n\nReturn a plan and list of steps.";

        $planResponse = $this->callOpenAI($prompt);

        // Extract plan and steps (simplified - would need better parsing)
        $this->taskManager->planTask($task['id'], $planResponse, []);

        $this->logger?->debug('Task planned', ['task_id' => $task['id'], 'plan_length' => mb_strlen($planResponse)]);
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

            $prompt = "Execute this task step by step:\n\n{$task['description']}\n\nPlan:\n{$task['plan']}\n\nContext:\n{$context}\n\nRecent tool results:\n{$toolLog}\n\nWhat tool should I use next? Return JSON with 'tool', 'params', and 'reasoning'.";

            $response = $this->callOpenAI($prompt);

            try {
                $action = json_decode($response, true);

                if (! $action || $action['tool'] === 'done') {
                    break; // Task complete
                }

                // Execute the tool
                $this->logger?->debug('Executing tool', [
                    'tool' => $action['tool'],
                    'params' => $action['params'],
                    'reasoning' => $action['reasoning'] ?? null,
                ]);

                $result = $this->toolRouter->dispatch($action['tool'], $action['params']);
                $this->addToHistory('tool', json_encode($action) . "\nResult: " . json_encode($result->toArray()));
            } catch (Exception $e) {
                $this->logger?->error("Tool error: {$e->getMessage()}");
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

    protected function handleConversation(string $userInput): AgentResponse
    {
        $prompt = "User: {$userInput}\n\nProvide a helpful response as a coding assistant.";
        $response = $this->callOpenAI($prompt);

        return AgentResponse::success($response);
    }
}
