<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use HelgeSverre\Swarm\Prompts\PromptTemplates;

/**
 * Conversation handler for general chat
 * 
 * Handles conversational requests that don't require specific tools or implementations.
 * Uses the ConversationBuffer for intelligent context management.
 */
class ConversationHandler implements RequestHandler
{
    public function __construct(
        private readonly ConversationBuffer $conversationBuffer,
        private readonly \Closure $llmCallback
    ) {}

    public function handle(string $input, array $classification, array $analysis): AgentResponse
    {
        $context = $this->conversationBuffer->getOptimalContext($input);
        
        $prompt = PromptTemplates::conversationPrompt($input);
        $messages = array_merge($context, [
            ['role' => 'user', 'content' => $prompt]
        ]);

        $response = ($this->llmCallback)($messages);
        
        return new AgentResponse($response, true, ['type' => 'conversation']);
    }
}