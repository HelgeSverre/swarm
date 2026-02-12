<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

/**
 * Request handler interface for clean architecture
 *
 * Defines the contract for handling different types of user requests
 * in the multi-channel processing architecture.
 */
interface RequestHandler
{
    /**
     * Handle a classified request with analysis context
     *
     * @param string $input The user's original input
     * @param array $classification The classification result from self-consistent reasoning
     * @param array $analysis The private analysis context
     *
     * @return AgentResponse The response to send back to the user
     */
    public function handle(string $input, array $classification, array $analysis): AgentResponse;
}
