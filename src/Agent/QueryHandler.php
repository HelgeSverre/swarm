<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use HelgeSverre\Swarm\Prompts\PromptTemplates;

/**
 * Query handler for information requests
 * 
 * Handles requests asking for information, facts, or answers
 * to specific questions without requiring implementation.
 */
class QueryHandler implements RequestHandler
{
    public function __construct(
        private readonly ConversationBuffer $conversationBuffer,
        private readonly \Closure $llmCallback
    ) {}

    public function handle(string $input, array $classification, array $analysis): AgentResponse
    {
        $context = $this->conversationBuffer->getOptimalContext($input);
        
        $prompt = PromptTemplates::queryPrompt($input);
        $messages = array_merge($context, [
            ['role' => 'user', 'content' => $prompt]
        ]);

        $response = ($this->llmCallback)($messages);
        
        return new AgentResponse($response, true, ['type' => 'query']);
    }
}