<?php

use HelgeSverre\Swarm\Agent\AgentProgressReporter;
use HelgeSverre\Swarm\Agent\ConversationBuffer;
use HelgeSverre\Swarm\Agent\ConversationHandler;
use HelgeSverre\Swarm\Agent\DemonstrationHandler;
use HelgeSverre\Swarm\Agent\ExplanationHandler;
use HelgeSverre\Swarm\Agent\HandlerRegistry;
use HelgeSverre\Swarm\Agent\ImplementationHandler;
use HelgeSverre\Swarm\Agent\QueryHandler;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Enums\Agent\RequestType;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Task\TaskManager;
use Psr\Log\NullLogger;

test('handler registry resolves handlers for known request types', function (string $requestType, string $expectedClass) {
    $registry = new HandlerRegistry(
        toolExecutor: ToolExecutor::createWithDefaultTools(),
        taskManager: new TaskManager(new NullLogger),
        conversationBuffer: new ConversationBuffer,
        logger: new NullLogger,
        progressReporter: new AgentProgressReporter(new EventBus),
        llmCallback: fn (array $messages, array $options = []): string => 'ok',
    );

    $handler = $registry->resolve(['request_type' => $requestType]);

    expect($handler)->toBeInstanceOf($expectedClass);
})->with([
    [RequestType::Implementation->value, ImplementationHandler::class],
    [RequestType::Demonstration->value, DemonstrationHandler::class],
    [RequestType::Explanation->value, ExplanationHandler::class],
    [RequestType::Query->value, QueryHandler::class],
    [RequestType::Conversation->value, ConversationHandler::class],
]);

test('handler registry falls back to conversation handler for unknown request types', function () {
    $registry = new HandlerRegistry(
        toolExecutor: ToolExecutor::createWithDefaultTools(),
        taskManager: new TaskManager(new NullLogger),
        conversationBuffer: new ConversationBuffer,
        logger: new NullLogger,
        progressReporter: new AgentProgressReporter(new EventBus),
        llmCallback: fn (array $messages, array $options = []): string => 'ok',
    );

    $handler = $registry->resolve(['request_type' => 'unknown-type']);

    expect($handler)->toBeInstanceOf(ConversationHandler::class);
});
