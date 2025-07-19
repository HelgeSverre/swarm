<?php

use HelgeSverre\Swarm\Prompts\PromptTemplates;

describe('PromptTemplates', function () {
    describe('System Prompts', function () {
        test('defaultSystem includes available tools', function () {
            $tools = ['read_file', 'write_file', 'bash'];
            $prompt = PromptTemplates::defaultSystem($tools);

            expect($prompt)
                ->toContain('AI coding assistant')
                ->toContain('You have access to these tools: read_file, write_file, bash')
                ->toContain('terminal/command line operations');
        });

        test('defaultSystem handles empty tools array', function () {
            $prompt = PromptTemplates::defaultSystem([]);

            expect($prompt)
                ->toContain('AI coding assistant')
                ->toContain('various coding tools');
        });

        test('classificationSystem prompt', function () {
            $prompt = PromptTemplates::classificationSystem();

            expect($prompt)
                ->toContain('understanding user intent')
                ->toContain('show an example')
                ->toContain('implement something')
                ->toContain('explain a concept')
                ->toContain('conversation');
        });

        test('planningSystem prompt', function () {
            $prompt = PromptTemplates::planningSystem();

            expect($prompt)
                ->toContain('expert at planning coding tasks')
                ->toContain('detailed plan');
        });

        test('executionSystem prompt', function () {
            $prompt = PromptTemplates::executionSystem();

            expect($prompt)
                ->toContain('Use the provided functions')
                ->toContain('Remember the context');
        });

        test('demonstrationSystem prompt', function () {
            $prompt = PromptTemplates::demonstrationSystem();

            expect($prompt)
                ->toContain('code example or demonstration')
                ->toContain('markdown format')
                ->toContain('Do NOT suggest creating files');
        });

        test('explanationSystem prompt', function () {
            $prompt = PromptTemplates::explanationSystem();

            expect($prompt)
                ->toContain('explanation of a concept')
                ->toContain('clear, educational');
        });

        test('conversationSystem prompt', function () {
            $prompt = PromptTemplates::conversationSystem();

            expect($prompt)
                ->toContain('engaged in conversation')
                ->toContain('Remember the context');
        });
    });

    describe('Task Prompts', function () {
        test('classifyRequest includes input', function () {
            $input = 'Create a function to calculate fibonacci';
            $prompt = PromptTemplates::classifyRequest($input);

            expect($prompt)
                ->toContain('Classify this request')
                ->toContain($input);
        });

        test('extractTasks includes input', function () {
            $input = 'Create three functions for math operations';
            $prompt = PromptTemplates::extractTasks($input);

            expect($prompt)
                ->toContain('Extract tasks')
                ->toContain($input)
                ->toContain('specific tasks to do');
        });

        test('planTask includes description and context', function () {
            $description = 'Create a REST API endpoint';
            $context = 'Working in Laravel project';
            $prompt = PromptTemplates::planTask($description, $context);

            expect($prompt)
                ->toContain('Plan how to execute')
                ->toContain($description)
                ->toContain($context);
        });

        test('planTaskFallback includes additional instructions', function () {
            $description = 'Build a user authentication system';
            $context = 'PHP 8.2 environment';
            $prompt = PromptTemplates::planTaskFallback($description, $context);

            expect($prompt)
                ->toContain($description)
                ->toContain($context)
                ->toContain('Return a plan and list of steps');
        });

        test('executeTask includes all task details', function () {
            $task = HelgeSverre\Swarm\Task\Task::fromArray([
                'id' => 'test-id',
                'description' => 'Implement login functionality',
                'plan' => 'Create controller, add routes, implement validation',
                'status' => 'executing',
            ]);
            $context = 'Laravel application';
            $toolLog = '{"tool": "write_file", "result": "success"}';

            $prompt = PromptTemplates::executeTask($task, $context, $toolLog);

            expect($prompt)
                ->toContain('Execute this task step by step')
                ->toContain($task->description)
                ->toContain($task->plan)
                ->toContain($context)
                ->toContain($toolLog)
                ->toContain('Decide what to do next');
        });

        test('generateSummary includes all components', function () {
            $userInput = 'Create a todo app';
            $taskResults = ['Created Todo model', 'Added CRUD routes', 'Built UI'];
            $recentHistory = 'user: Create a todo app\nassistant: I\'ll help you create that';
            $toolLog = '{"tools_used": ["write_file", "bash"]}';

            $prompt = PromptTemplates::generateSummary($userInput, $taskResults, $recentHistory, $toolLog);

            expect($prompt)
                ->toContain($userInput)
                ->toContain('Created Todo model')
                ->toContain('Added CRUD routes')
                ->toContain('Built UI')
                ->toContain($recentHistory)
                ->toContain($toolLog)
                ->toContain('summarizing what was done');
        });
    });

    describe('Code Assistance Prompts', function () {
        test('explainCode includes code and structure', function () {
            $code = 'function fibonacci($n) { return $n <= 1 ? $n : fibonacci($n-1) + fibonacci($n-2); }';
            $prompt = PromptTemplates::explainCode($code);

            expect($prompt)
                ->toContain('explain what this code does')
                ->toContain($code)
                ->toContain('Overall purpose')
                ->toContain('Key components')
                ->toContain('How the pieces work together');
        });

        test('refactorCode with all parameters', function () {
            $code = 'if($x == true) { return true; } else { return false; }';
            $focus = 'simplicity';
            $context = 'This is part of a validation function';

            $prompt = PromptTemplates::refactorCode($code, $focus, $context);

            expect($prompt)
                ->toContain('refactor this code')
                ->toContain($focus)
                ->toContain($code)
                ->toContain($context)
                ->toContain('improvements made');
        });

        test('refactorCode with default focus', function () {
            $code = 'function test() { /* complex code */ }';
            $prompt = PromptTemplates::refactorCode($code);

            expect($prompt)
                ->toContain('improve its readability')
                ->toContain($code)
                ->not->toContain('Additional context');
        });

        test('debugCode with error messages', function () {
            $code = 'array_push($items, $item);';
            $issue = 'Getting "undefined variable" error';
            $errorMessages = 'Notice: Undefined variable: items in test.php on line 5';

            $prompt = PromptTemplates::debugCode($code, $issue, $errorMessages);

            expect($prompt)
                ->toContain('debug the following code')
                ->toContain($code)
                ->toContain($issue)
                ->toContain($errorMessages)
                ->toContain('Identify the likely cause');
        });

        test('debugCode without error messages', function () {
            $code = 'return $result;';
            $issue = 'Function returns null sometimes';

            $prompt = PromptTemplates::debugCode($code, $issue);

            expect($prompt)
                ->toContain($code)
                ->toContain($issue)
                ->not->toContain('Error messages:');
        });

        test('reviewCode includes all review aspects', function () {
            $code = 'class UserController { /* controller code */ }';
            $prompt = PromptTemplates::reviewCode($code);

            expect($prompt)
                ->toContain('review this code')
                ->toContain($code)
                ->toContain('Code quality')
                ->toContain('Potential bugs')
                ->toContain('Performance')
                ->toContain('Security')
                ->toContain('Best practices');
        });

        test('generateCode with all parameters', function () {
            $task = 'create a user registration form';
            $language = 'PHP/Laravel';
            $requirements = '- Email validation\n- Password strength check\n- CSRF protection';

            $prompt = PromptTemplates::generateCode($task, $language, $requirements);

            expect($prompt)
                ->toContain($task)
                ->toContain($language)
                ->toContain($requirements)
                ->toContain('well-structured')
                ->toContain('error handling');
        });

        test('generateCode with defaults', function () {
            $task = 'calculate prime numbers';
            $prompt = PromptTemplates::generateCode($task);

            expect($prompt)
                ->toContain($task)
                ->toContain('Language/Framework: PHP')
                ->not->toContain('Requirements:');
        });

        test('documentCode with custom style', function () {
            $code = 'function processData($input) { /* processing */ }';
            $style = 'Markdown documentation';

            $prompt = PromptTemplates::documentCode($code, $style);

            expect($prompt)
                ->toContain('add documentation')
                ->toContain($code)
                ->toContain($style)
                ->toContain('Clear descriptions')
                ->toContain('Parameter and return type');
        });

        test('documentCode with default style', function () {
            $code = 'class Example {}';
            $prompt = PromptTemplates::documentCode($code);

            expect($prompt)
                ->toContain($code)
                ->toContain('Documentation style: PHPDoc');
        });

        test('testCode with custom framework', function () {
            $code = 'function add($a, $b) { return $a + $b; }';
            $framework = 'Pest PHP';

            $prompt = PromptTemplates::testCode($code, $framework);

            expect($prompt)
                ->toContain('write tests')
                ->toContain($code)
                ->toContain($framework)
                ->toContain('main functionality')
                ->toContain('edge cases')
                ->toContain('comprehensive coverage');
        });

        test('testCode with default framework', function () {
            $code = 'class Calculator {}';
            $prompt = PromptTemplates::testCode($code);

            expect($prompt)
                ->toContain($code)
                ->toContain('Testing framework: PHPUnit');
        });
    });
});
