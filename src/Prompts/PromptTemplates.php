<?php

namespace HelgeSverre\Swarm\Prompts;

/**
 * Centralized prompt templates for the coding agent.
 *
 * Provides type-safe static methods for generating prompts used throughout the system.
 * Inspired by patterns from Aider and Claude Code.
 */
class PromptTemplates
{
    /**
     * Default system prompt for general interactions
     */
    public static function defaultSystem(array $availableTools = []): string
    {
        $toolList = ! empty($availableTools) ? implode(', ', $availableTools) : 'various coding tools';

        return 'You are a helpful coding assistant. Always return valid JSON when asked for JSON. ' .
            "Available tools are: {$toolList}. " .
            'Use "bash" for terminal/command line operations.';
    }

    /**
     * System prompt for request classification
     */
    public static function classificationSystem(): string
    {
        return 'You are an expert at understanding user intent in coding requests. ' .
            'Classify whether the user wants you to show an example, actually implement something, ' .
            'explain a concept, or just have a conversation.';
    }

    /**
     * System prompt for task planning
     */
    public static function planningSystem(): string
    {
        return 'You are an expert at planning coding tasks. Create a detailed plan with specific steps.';
    }

    /**
     * System prompt for task execution with functions
     */
    public static function executionSystem(string $toolDescriptions = ''): string
    {
        $toolInfo = $toolDescriptions ? "Available tools: {$toolDescriptions}. " : '';

        return 'You are a helpful coding assistant. ' .
            $toolInfo .
            'Use the provided functions to complete tasks. ' .
            'Remember the context from our previous conversation.';
    }

    /**
     * System prompt for code demonstrations
     */
    public static function demonstrationSystem(): string
    {
        return 'You are a helpful coding assistant. The user is asking for a code example or demonstration. ' .
            'Provide the requested code example in markdown format with proper syntax highlighting. ' .
            'Do NOT suggest creating files unless explicitly asked. Focus on showing the code example.';
    }

    /**
     * System prompt for explanations
     */
    public static function explanationSystem(): string
    {
        return 'You are a helpful coding assistant. The user is asking for an explanation of a concept. ' .
            'Provide a clear, educational explanation. Use code examples in markdown if helpful, ' .
            'but focus on explaining the concept rather than implementing anything.';
    }

    /**
     * System prompt for conversations
     */
    public static function conversationSystem(string $toolDescriptions = ''): string
    {
        $toolInfo = $toolDescriptions ? "You have access to these tools: {$toolDescriptions}. " : 'You have access to various tools. ';

        return 'You are a helpful coding assistant engaged in conversation with the user. ' .
            $toolInfo .
            'Provide helpful, informative responses. Remember the context from our previous conversation. ' .
            'If the user asks for code examples, provide them in markdown format.';
    }

    /**
     * Prompt for classifying user requests
     */
    public static function classifyRequest(string $input): string
    {
        return "Classify this request: \"{$input}\"";
    }

    /**
     * Prompt for extracting tasks from user input
     */
    public static function extractTasks(string $input): string
    {
        return "Extract tasks given in this input:\n\n" .
            "{$input}" .
            "\n\nIf there are specific tasks to do, extract them. Otherwise, treat the input as a general question or conversation.";
    }

    /**
     * Prompt for planning a task
     */
    public static function planTask(string $description, string $context): string
    {
        return "Plan how to execute this coding task:\n\n{$description}\n\nContext:\n{$context}";
    }

    /**
     * Fallback prompt for planning a task
     */
    public static function planTaskFallback(string $description, string $context): string
    {
        return "Plan how to execute this coding task:\n\n{$description}\n\nContext:\n{$context}\n\nReturn a plan and list of steps.";
    }

    /**
     * Prompt for executing a task step by step
     */
    public static function executeTask(array $task, string $context, string $toolLog): string
    {
        return "Execute this task step by step:\n\n{$task['description']}\n\n" .
            "Plan:\n{$task['plan']}\n\n" .
            "Context:\n{$context}\n\n" .
            "Recent tool results:\n{$toolLog}\n\n" .
            'Decide what to do next to complete the task.';
    }

    /**
     * Prompt for generating a task completion summary
     */
    public static function generateSummary(string $userInput, array $taskResults, string $recentHistory, string $toolLog): string
    {
        $prompt = "The user asked: {$userInput}\n\n";
        $prompt .= "The following tasks were completed:\n";
        foreach ($taskResults as $task) {
            $prompt .= "- {$task}\n";
        }
        $prompt .= "\nRecent actions taken:\n{$toolLog}\n\n";
        $prompt .= "Recent conversation:\n{$recentHistory}\n\n";
        $prompt .= 'Provide a helpful response to the user summarizing what was done, focusing on the actual results and outcomes. Be specific about what files were created, modified, or what actions were taken.';

        return $prompt;
    }

    /**
     * Code assistance prompt: Explain code
     * Inspired by Claude Code patterns
     */
    public static function explainCode(string $code): string
    {
        return "Please explain what this code does:\n\n{$code}\n\n" .
            "Break down the explanation into:\n" .
            "1. Overall purpose\n" .
            "2. Key components and their roles\n" .
            "3. How the pieces work together\n" .
            '4. Any important patterns or techniques used';
    }

    /**
     * Code assistance prompt: Refactor code
     * Inspired by Claude Code patterns
     */
    public static function refactorCode(string $code, string $focus = 'readability', string $context = ''): string
    {
        $prompt = "Please refactor this code to improve its {$focus}:\n\n{$code}";

        if (! empty($context)) {
            $prompt .= "\n\nAdditional context: {$context}";
        }

        $prompt .= "\n\nProvide:\n" .
            "1. The refactored code\n" .
            "2. A brief explanation of the improvements made\n" .
            '3. Any trade-offs or considerations';

        return $prompt;
    }

    /**
     * Code assistance prompt: Debug code
     * Inspired by Claude Code patterns
     */
    public static function debugCode(string $code, string $issue, string $errorMessages = ''): string
    {
        $prompt = "Please help me debug the following code:\n\n{$code}\n\n" .
            "The issue I'm seeing is: {$issue}";

        if (! empty($errorMessages)) {
            $prompt .= "\n\nError messages:\n{$errorMessages}";
        }

        $prompt .= "\n\nPlease:\n" .
            "1. Identify the likely cause of the issue\n" .
            "2. Explain why this issue is occurring\n" .
            "3. Provide a corrected version of the code\n" .
            '4. Suggest how to prevent similar issues in the future';

        return $prompt;
    }

    /**
     * Code assistance prompt: Review code
     * Inspired by Claude Code patterns
     */
    public static function reviewCode(string $code): string
    {
        return "Please review this code and provide feedback:\n\n{$code}\n\n" .
            "Focus on:\n" .
            "1. Code quality and readability\n" .
            "2. Potential bugs or issues\n" .
            "3. Performance considerations\n" .
            "4. Security concerns\n" .
            "5. Best practices and improvements\n" .
            '6. Overall architecture and design';
    }

    /**
     * Code assistance prompt: Generate code
     * Inspired by Claude Code patterns
     */
    public static function generateCode(string $task, string $language = 'PHP', string $requirements = ''): string
    {
        $prompt = "Please write code to {$task}.\n\n" .
            "Language/Framework: {$language}";

        if (! empty($requirements)) {
            $prompt .= "\n\nRequirements:\n{$requirements}";
        }

        $prompt .= "\n\nPlease ensure the code:\n" .
            "1. Is well-structured and follows best practices\n" .
            "2. Includes appropriate error handling\n" .
            "3. Is documented with clear comments\n" .
            '4. Is efficient and maintainable';

        return $prompt;
    }

    /**
     * Code assistance prompt: Document code
     * Inspired by Claude Code patterns
     */
    public static function documentCode(string $code, string $style = 'PHPDoc'): string
    {
        return "Please add documentation to this code:\n\n{$code}\n\n" .
            "Documentation style: {$style}\n\n" .
            "Include:\n" .
            "1. Clear descriptions of what each component does\n" .
            "2. Parameter and return type documentation\n" .
            "3. Usage examples where appropriate\n" .
            '4. Any important notes or warnings';
    }

    /**
     * Code assistance prompt: Generate tests
     * Inspired by Claude Code patterns
     */
    public static function testCode(string $code, string $framework = 'PHPUnit'): string
    {
        return "Please write tests for this code:\n\n{$code}\n\n" .
            "Testing framework: {$framework}\n\n" .
            "Create tests that:\n" .
            "1. Cover the main functionality\n" .
            "2. Test edge cases and error conditions\n" .
            "3. Are clear and well-documented\n" .
            "4. Follow testing best practices\n" .
            '5. Aim for comprehensive coverage';
    }
}
