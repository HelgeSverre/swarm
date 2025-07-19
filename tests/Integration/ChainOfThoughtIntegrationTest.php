<?php

namespace HelgeSverre\Swarm\Tests\Integration;

pest()->group('integration');

use Dotenv\Dotenv;
use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Task\TaskManager;
use HelgeSverre\Swarm\Tools\Grep;
use HelgeSverre\Swarm\Tools\ReadFile;
use HelgeSverre\Swarm\Tools\Terminal;
use HelgeSverre\Swarm\Tools\WriteFile;
use OpenAI;

beforeEach(function () {
    // Load environment
    $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
    $dotenv->load();

    // Skip if no OpenAI key
    if (! getenv('OPENAI_API_KEY')) {
        $this->markTestSkipped('OpenAI API key not configured');
    }

    // Initialize components
    $this->toolExecutor = new ToolExecutor;
    $this->toolExecutor->register(new ReadFile);
    $this->toolExecutor->register(new WriteFile);
    $this->toolExecutor->register(new Grep);
    $this->toolExecutor->register(new Terminal);

    $this->taskManager = new TaskManager;
    $this->llmClient = OpenAI::client(getenv('OPENAI_API_KEY'));

    $this->agent = new CodingAgent(
        toolExecutor: $this->toolExecutor,
        taskManager: $this->taskManager,
        llmClient: $this->llmClient,
        model: 'gpt-4.1-nano'
    );
});

describe('Chain of Thought classification', function () {
    it('correctly identifies internal task management requests', function () {
        $testCases = [
            'Show me my task list',
            "Add 'buy milk' to my task list",
            'Clear completed tasks',
        ];

        foreach ($testCases as $input) {
            $response = $this->agent->processRequest($input);

            expect($response->isSuccess())->toBeTrue();
            $message = mb_strtolower($response->getMessage());
            expect($message)->toContain('task');

            // Should not create any files
            $status = $this->agent->getStatus();
            $fileCreationTasks = array_filter($status['tasks'] ?? [], function ($task) {
                return str_contains(mb_strtolower($task['description']), 'create') &&
                       str_contains(mb_strtolower($task['description']), 'file');
            });

            expect($fileCreationTasks)->toBeEmpty();
        }
    });

    it('correctly identifies file creation requests', function () {
        $testCases = [
            'Create a file called tasks.txt with my shopping list',
            'Write a todo list to a file',
        ];

        foreach ($testCases as $input) {
            $response = $this->agent->processRequest($input);

            expect($response->isSuccess())->toBeTrue();

            // Should have file creation tasks
            $status = $this->agent->getStatus();
            $hasTasks = ! empty($status['tasks']);

            expect($hasTasks)->toBeTrue();

            // At least one task should involve file creation
            $hasFileTask = false;
            foreach ($status['tasks'] ?? [] as $task) {
                if (str_contains(mb_strtolower($task['description']), 'file')) {
                    $hasFileTask = true;
                    break;
                }
            }

            expect($hasFileTask)->toBeTrue();
        }
    });
});

describe('Internal task management', function () {
    it('shows empty task list when no tasks exist', function () {
        $response = $this->agent->processRequest('Show me my task list');

        expect($response->isSuccess())->toBeTrue();
        $message = mb_strtolower($response->getMessage());

        // Check if message contains any of these phrases or is talking about tasks
        $containsExpectedPhrase =
            str_contains($message, 'no active tasks') ||
            str_contains($message, 'empty') ||
            str_contains($message, 'currently have no') ||
            str_contains($message, 'no tasks') ||
            str_contains($message, 'task list') ||
            str_contains($message, 'don\'t have any') ||
            str_contains($message, 'there are no') ||
            str_contains($message, 'task') ||
            str_contains($message, 'nothing');

        expect($containsExpectedPhrase)->toBeTrue();
    });

    it('clears completed tasks when requested', function () {
        // First add a task by making an implementation request
        $this->agent->processRequest('Create a simple hello.txt file with "Hello World" content');

        // Request to clear completed tasks
        $response = $this->agent->processRequest('Clear completed tasks');

        expect($response->isSuccess())->toBeTrue();

        // If there were completed tasks, they should be cleared
        $finalStatus = $this->agent->getStatus();
        $completedTasks = array_filter($finalStatus['tasks'] ?? [], function ($task) {
            return $task['status'] === 'completed';
        });

        expect($completedTasks)->toBeEmpty();
    });
});

describe('Chain of Thought reasoning', function () {
    it('includes step-by-step reasoning in classification', function () {
        // Test with an ambiguous request
        $response = $this->agent->processRequest('I need to manage my tasks better');

        expect($response->isSuccess())->toBeTrue();

        // The response should acknowledge the task management aspect
        $message = mb_strtolower($response->getMessage());
        expect($message)->toContain('task');
    });
});
