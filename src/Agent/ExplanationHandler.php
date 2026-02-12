<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use HelgeSverre\Swarm\Prompts\PromptTemplates;

/**
 * Explanation handler for educational content
 * 
 * Handles requests asking for explanations of concepts, code,
 * or technical topics without requiring implementation.
 */
class ExplanationHandler implements RequestHandler
{
    public function __construct(
        private readonly ConversationBuffer $conversationBuffer,
        private readonly \Closure $llmCallback
    ) {}

    public function handle(string $input, array $classification, array $analysis): AgentResponse
    {
        $context = $this->conversationBuffer->getOptimalContext($input);
        
        $prompt = PromptTemplates::explanationPrompt($input);
        $messages = array_merge($context, [
            ['role' => 'user', 'content' => $prompt]
        ]);

        $response = ($this->llmCallback)($messages);
        
        return new AgentResponse($response, true, ['type' => 'explanation']);
    }
}