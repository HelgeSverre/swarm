<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Contracts;

use HelgeSverre\Swarm\Agent\AgentResponse;

/**
 * Core UI interface that all UI implementations must follow
 * This enables swappable UI implementations (line-based vs full terminal)
 */
interface UIInterface
{
    /**
     * Prompt the user for input
     *
     * @param string $label The prompt label to display
     *
     * @return string The user's input
     */
    public function prompt(string $label = '>'): string;

    /**
     * Refresh the UI with updated status information
     *
     * @param array $status Current system status including tasks, context, etc.
     */
    public function refresh(array $status): void;

    /**
     * Display an agent response to the user
     *
     * @param AgentResponse $response The response from the agent
     */
    public function displayResponse(AgentResponse $response): void;

    /**
     * Display an error message to the user
     *
     * @param string $errorMessage The error message to display
     */
    public function displayError(string $errorMessage): void;

    /**
     * Show a notification to the user
     *
     * @param string $message The notification message
     * @param string $type The notification type (info, success, warning, error)
     */
    public function showNotification(string $message, string $type = 'info'): void;

    /**
     * Start processing animation/indicator
     */
    public function startProcessing(): void;

    /**
     * Stop processing animation/indicator
     */
    public function stopProcessing(): void;

    /**
     * Update the processing animation (called periodically during processing)
     */
    public function showProcessing(): void;

    /**
     * Update the processing message with specific details
     *
     * @param string $message The processing status message
     */
    public function updateProcessingMessage(string $message): void;

    /**
     * Cleanup UI resources (called on shutdown)
     */
    public function cleanup(): void;
}
