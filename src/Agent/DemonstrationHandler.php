<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use HelgeSverre\Swarm\Prompts\PromptTemplates;

/**
 * Demonstration handler for code examples
 * 
 * Handles requests asking for code examples, snippets, or demonstrations
 * of how to implement specific functionality.
 */
class DemonstrationHandler implements RequestHandler
{
    public function __construct(
        private readonly ConversationBuffer $conversationBuffer,
        private readonly \Closure $llmCallback
    ) {}

    public function handle(string $input, array $classification, array $analysis): AgentResponse
    {
        $context = $this->conversationBuffer->getOptimalContext($input);
        
        $prompt = PromptTemplates::demonstrationPrompt($input);
        $messages = array_merge($context, [
            ['role' => 'user', 'content' => $prompt]
        ]);

        $response = ($this->llmCallback)($messages);
        
        return new AgentResponse($response, true, ['type' => 'demonstration']);
    }
}