<?php

// tmp/loop.php

require 'vendor/autoload.php';

use Dotenv\Dotenv;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Initialize OpenAI client
$openai = OpenAI::client($_ENV['OPENAI_API_KEY'] ?? throw new Exception('OpenAI API key not found'));
$model = $_ENV['OPENAI_MODEL'] ?? 'gpt-4-turbo-preview';

// Mock internal state
$internalTasks = [];
$conversationHistory = [];

// Mock tools
$tools = [
    'add_task' => [
        'description' => 'Add a task to the internal task list',
        'params' => ['description' => 'string'],
        'execute' => function (array $params) use (&$internalTasks): array {
            $internalTasks[] = $params['description'];

            return ['success' => true, 'message' => "Added task: {$params['description']}", 'task_count' => count($internalTasks)];
        },
    ],
    'list_tasks' => [
        'description' => 'List all tasks in the internal task list',
        'params' => [],
        'execute' => function () use (&$internalTasks): array {
            return ['success' => true, 'tasks' => $internalTasks, 'count' => count($internalTasks)];
        },
    ],
    'create_file' => [
        'description' => 'Create a file with content',
        'params' => ['filename' => 'string', 'content' => 'string'],
        'execute' => function (array $params): array {
            // Mock file creation
            return ['success' => true, 'message' => "Created file: {$params['filename']}"];
        },
    ],
    'search' => [
        'description' => 'Search for information',
        'params' => ['query' => 'string'],
        'execute' => function (array $params): array {
            return ['success' => true, 'results' => ["Mock result 1 for '{$params['query']}'", 'Mock result 2']];
        },
    ],
    'calculate' => [
        'description' => 'Perform mathematical calculations',
        'params' => ['expression' => 'string'],
        'execute' => function (array $params): array {
            // Safe eval for simple math
            $expr = preg_replace('/[^0-9+\-*\/().\s]/', '', $params['expression']);
            $result = @eval("return {$expr};");

            return ['success' => true, 'result' => $result, 'expression' => $params['expression']];
        },
    ],
];

// Chain of Thought reasoning
function performReasoning(string $input, array $context, array $tools, OpenAI\Client $openai, string $model): array
{
    info("\n🤔 THOUGHT (Chain of Thought):");

    $systemPrompt = <<<'PROMPT'
You are an AI assistant that uses Chain of Thought reasoning. Think step by step about the user's request.

Available tools:
PROMPT;

    foreach ($tools as $name => $tool) {
        $systemPrompt .= "\n- {$name}: {$tool['description']}";
    }

    $systemPrompt .= "\n\nIMPORTANT: When users mention 'task list' or 'tasks', they usually mean the internal task management system, NOT file creation.";

    $reasoningPrompt = <<<PROMPT
User request: "{$input}"

Let's think step by step:
1. What is the user literally asking for?
2. What is their underlying intent?
3. Do they need a tool, or can I answer directly?
4. If they mention "tasks" or "task list", are they referring to internal task management or file creation?

Provide your reasoning in a structured way.
PROMPT;

    $result = $openai->chat()->create([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $reasoningPrompt],
        ],
        'temperature' => 0.3,
    ]);

    $reasoning = $result->choices[0]->message->content;

    // Display reasoning with formatting
    $lines = explode("\n", $reasoning);
    foreach ($lines as $line) {
        if (mb_trim($line)) {
            warning('  ' . $line);
            usleep(100000); // Small delay for effect
        }
    }

    return ['reasoning' => $reasoning];
}

// Decide action based on reasoning
function decideAction(string $reasoning, string $input, array $tools, OpenAI\Client $openai, string $model): ?array
{
    info('🎯 ACTION DECISION:');

    $toolList = implode(', ', array_keys($tools));

    $decisionPrompt = <<<PROMPT
Based on this reasoning:
{$reasoning}

User request: "{$input}"

Available tools: {$toolList}

Decide the next action. You must respond with a valid JSON object (no markdown, just JSON):
{
    "action": "tool_name" or "respond",
    "tool": "tool_name if using a tool, null otherwise",
    "params": {"param": "value"} or null,
    "response": "direct response if action is respond, null otherwise"
}

Only use tools when necessary. Many requests can be answered directly.
PROMPT;

    $result = $openai->chat()->create([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You are an AI that decides which action to take based on reasoning. Always respond with valid JSON only.'],
            ['role' => 'user', 'content' => $decisionPrompt],
        ],
        'temperature' => 0.2,
    ]);

    $decision = $result->choices[0]->message->content;

    // Parse JSON from response
    $actionData = json_decode(mb_trim($decision), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error('  Failed to parse action decision: ' . json_last_error_msg());

        return null;
    }

    if ($actionData['action'] === 'respond') {
        warning('  Decision: Respond directly (no tool needed)');
    } else {
        warning("  Decision: Use tool '{$actionData['tool']}'");
    }

    return $actionData;
}

// Execute tool
function executeTool(string $toolName, array $params, array $tools): array
{
    info("\n🔧 EXECUTING TOOL: {$toolName}");

    if (! isset($tools[$toolName])) {
        return ['success' => false, 'error' => "Tool '{$toolName}' not found"];
    }

    warning('  Parameters: ' . json_encode($params));

    try {
        $result = $tools[$toolName]['execute']($params);
        note('  Result: ' . json_encode($result));

        return $result;
    } catch (Exception $e) {
        error('  Error: ' . $e->getMessage());

        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ReAct loop
function reactLoop(string $input, array $tools, array &$conversationHistory, OpenAI\Client $openai, string $model): string
{
    $maxSteps = 10;
    $observations = [];

    note('=== Starting ReAct Loop ===');

    for ($step = 1; $step <= $maxSteps; $step++) {
        info("--- Step {$step} ---");
        // THOUGHT (Chain of Thought reasoning)
        $thought = performReasoning($input, [
            'step' => $step,
            'observations' => $observations,
            'history' => array_slice($conversationHistory, -5),
        ], $tools, $openai, $model);

        // ACTION
        $action = decideAction($thought['reasoning'], $input, $tools, $openai, $model);

        if (! $action) {
            error('  Failed to decide action');
            break;
        }

        // Handle direct response
        if ($action['action'] === 'respond') {
            return $action['response'];
        }

        // OBSERVATION (Tool execution)
        info('📊 OBSERVATION:');
        $observation = executeTool($action['tool'], $action['params'] ?? [], $tools);
        $observations[] = $observation;

        // Check if task is complete
        if ($step < $maxSteps) {
            $completionCheck = $openai->chat()->create([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You determine if a task is complete. Answer with "yes" or "no" followed by a brief explanation.'],
                    ['role' => 'user', 'content' => "User request: '{$input}'\nLatest result: " . json_encode($observation) . "\n\nIs the task complete?"],
                ],
                'temperature' => 0.2,
            ])->choices[0]->message->content;

            warning(' Completion check: ' . mb_trim($completionCheck));

            if (mb_stripos($completionCheck, 'yes') === 0) {
                // Generate final response
                $finalResponse = $openai->chat()->create([
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You provide helpful, concise responses about completed tasks.'],
                        ['role' => 'user', 'content' => "Generate a response for:\nRequest: {$input}\nResult: " . json_encode($observation)],
                    ],
                    'temperature' => 0.7,
                ])->choices[0]->message->content;

                return $finalResponse;
            }
        }
    }

    return "I've completed the maximum number of steps. Here's what I accomplished: " .
        json_encode($observations[count($observations) - 1] ?? ['error' => 'No results']);
}

// Main interface
info('🤖 CoT/ReAct Agent Demo with OpenAI');
info("Model: {$model}");
info("Commands: 'exit' to quit, 'tasks' to see task list, 'clear' to clear screen\n");

while (true) {
    $input = text('You: ');

    if ($input === 'exit') {
        break;
    }

    if ($input === 'clear') {
        system('clear');

        continue;
    }

    if ($input === 'tasks') {
        info("\n📋 Current internal task list:");
        if (empty($internalTasks)) {
            warning('  (empty)');
        } else {
            foreach ($internalTasks as $i => $task) {
                warning('  ' . ($i + 1) . ". {$task}");
            }
        }
        echo "\n";

        continue;
    }

    // Add to conversation history
    $conversationHistory[] = ['role' => 'user', 'content' => $input];

    try {
        $response = reactLoop($input, $tools, $conversationHistory, $openai, $model);

        info('🤖 Agent: ' . $response);

        $conversationHistory[] = ['role' => 'assistant', 'content' => $response];
    } catch (Exception $e) {
        error('❌ Error: ' . $e->getMessage());
    }
}

info("\n👋 Goodbye!");
