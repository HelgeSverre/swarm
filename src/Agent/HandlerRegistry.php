<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use Closure;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Enums\Agent\RequestType;
use HelgeSverre\Swarm\Task\TaskManager;
use Psr\Log\LoggerInterface;

final class HandlerRegistry
{
    public function __construct(
        private readonly ToolExecutor $toolExecutor,
        private readonly TaskManager $taskManager,
        private readonly ConversationBuffer $conversationBuffer,
        private readonly ?LoggerInterface $logger,
        private readonly AgentProgressReporter $progressReporter,
        private readonly Closure $llmCallback,
    ) {}

    public function resolve(array $classification): RequestHandler
    {
        $type = RequestType::tryFrom($classification['request_type'] ?? RequestType::Conversation->value)
            ?? RequestType::Conversation;

        return match ($type) {
            RequestType::Implementation => new ImplementationHandler(
                toolExecutor: $this->toolExecutor,
                taskManager: $this->taskManager,
                conversationBuffer: $this->conversationBuffer,
                logger: $this->logger,
                progressReporter: $this->progressReporter,
                llmCallback: $this->llmCallback,
            ),
            RequestType::Demonstration => new DemonstrationHandler(
                conversationBuffer: $this->conversationBuffer,
                llmCallback: $this->llmCallback,
            ),
            RequestType::Explanation => new ExplanationHandler(
                conversationBuffer: $this->conversationBuffer,
                llmCallback: $this->llmCallback,
            ),
            RequestType::Query => new QueryHandler(
                conversationBuffer: $this->conversationBuffer,
                llmCallback: $this->llmCallback,
            ),
            RequestType::Conversation => new ConversationHandler(
                conversationBuffer: $this->conversationBuffer,
                llmCallback: $this->llmCallback,
            ),
        };
    }
}
