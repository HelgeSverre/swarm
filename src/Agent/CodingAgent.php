<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

use Exception;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Enums\Agent\RequestType;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Task\Task;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI;
use Psr\Log\LoggerInterface;

/**
 * Enhanced Coding Agent with OpenAI GPT models and intelligent context management
 *
 * Key improvements:
 * - Intelligent conversation buffer with relevance-based context selection
 * - Self-consistent reasoning for better classification accuracy
 * - Multi-channel processing (analysis, planning, execution, reflection)
 * - Reflexive error recovery with pattern learning
 * - Clean architecture without massive processRequest() method
 */
class CodingAgent
{
    protected ConversationBuffer $conversationBuffer;

    protected RequestClassifier $requestClassifier;

    protected HandlerRegistry $handlerRegistry;

    protected LlmGateway $llmGateway;

    protected ErrorRecoveryPolicy $errorRecoveryPolicy;

    protected AgentProgressReporter $progressReporter;

    public function __construct(
        protected readonly ToolExecutor $toolExecutor,
        protected readonly TaskManager $taskManager,
        protected readonly OpenAI\Contracts\ClientContract $llmClient,
        protected readonly ?LoggerInterface $logger = null,
        protected readonly string $model = 'gpt-4o-mini',
        protected readonly string $reasoningEffort = 'medium',
        protected readonly string $verbosity = 'medium',
        ?EventBus $eventBus = null,
    ) {
        $this->conversationBuffer = new ConversationBuffer;
        $this->progressReporter = new AgentProgressReporter($eventBus ?? new EventBus);
        $this->errorRecoveryPolicy = new ErrorRecoveryPolicy(
            conversationBuffer: $this->conversationBuffer,
            logger: $this->logger,
        );
        $this->llmGateway = new LlmGateway(
            llmClient: $this->llmClient,
            conversationBuffer: $this->conversationBuffer,
            errorRecoveryPolicy: $this->errorRecoveryPolicy,
            progressReporter: $this->progressReporter,
            logger: $this->logger,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            verbosity: $this->verbosity,
        );
        $this->requestClassifier = new RequestClassifier(
            conversationBuffer: $this->conversationBuffer,
            llmCallback: fn (array $messages, array $options = []) => $this->llmGateway->call($messages, $options),
            logger: $this->logger,
        );
        $this->handlerRegistry = new HandlerRegistry(
            toolExecutor: $this->toolExecutor,
            taskManager: $this->taskManager,
            conversationBuffer: $this->conversationBuffer,
            logger: $this->logger,
            progressReporter: $this->progressReporter,
            llmCallback: fn (array $messages, array $options = []) => $this->llmGateway->call($messages, $options),
        );
    }

    /**
     * Main request processing with clean architecture
     */
    public function processRequest(string $userInput): AgentResponse
    {
        // Input validation
        if (empty(trim($userInput))) {
            return AgentResponse::error(
                error: 'Empty input provided',
                partialContent: 'Please provide a valid request.'
            );
        }

        if (mb_strlen($userInput) > 10000) {
            return AgentResponse::error(
                error: 'Input too long',
                partialContent: 'Please shorten your request to under 10,000 characters.'
            );
        }

        $this->logger?->info('Processing request', [
            'input_length' => mb_strlen($userInput),
            'model' => $this->model,
        ]);

        // Add to intelligent conversation buffer
        $this->conversationBuffer->addMessage('user', $userInput);

        $startTime = microtime(true);

        try {
            // Multi-channel processing: analyze, classify, route, execute
            $response = $this->processWithChannelsAndRecovery($userInput);

            $processingTime = microtime(true) - $startTime;
            // Create new response with processing time since properties are readonly
            $response = new AgentResponse(
                content: $response->content,
                success: $response->success,
                metadata: array_merge($response->metadata, ['processing_time' => $processingTime]),
                error: $response->error,
                processingTime: $processingTime,
                toolCalls: $response->toolCalls,
                classificationData: $response->classificationData
            );

            return $response;
        } catch (Exception $e) {
            $processingTime = microtime(true) - $startTime;

            $this->logger?->error('Request processing failed', [
                'error' => $e->getMessage(),
                'input' => mb_substr($userInput, 0, 200) . '...',
                'processing_time' => $processingTime,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorRecoveryPolicy->handleFatalProcessingError($e, $userInput, $processingTime);
        }
    }

    /**
     * Enhanced LLM call with retry logic
     */
    public function callLLMWithEnhancements(array $messages, array $options = []): string
    {
        return $this->llmGateway->call($messages, $options);
    }

    /**
     * Set progress callback for real-time feedback
     */
    public function setProgressCallback(?callable $callback): void
    {
        $this->progressReporter->setCallback($callback);
    }

    /**
     * Get conversation buffer statistics for monitoring
     */
    public function getConversationStats(): array
    {
        return $this->conversationBuffer->getStats();
    }

    /**
     * Enable/disable custom tools usage
     */
    public function setUseCustomTools(bool $use): void
    {
        $this->llmGateway->setUseCustomTools($use);
    }

    /**
     * Set conversation history (for backward compatibility with state restoration)
     */
    public function setConversationHistory(array $history): void
    {
        // Convert old history format to new ConversationBuffer
        foreach ($history as $entry) {
            if (isset($entry['role'], $entry['content'])) {
                $this->conversationBuffer->addMessage($entry['role'], $entry['content']);
            }
        }
    }

    /**
     * Get conversation history (for backward compatibility with state saving)
     */
    public function getConversationHistory(): array
    {
        // Return recent context for state saving
        return $this->conversationBuffer->getRecentContext(50);
    }

    /**
     * Get task manager (for backward compatibility)
     */
    public function getTaskManager(): TaskManager
    {
        return $this->taskManager;
    }

    /**
     * Get agent status (for backward compatibility with WorkerProcess)
     */
    public function getStatus(): array
    {
        $tasks = $this->taskManager->getTasks();
        $currentTask = $this->taskManager->currentTask;  // Use property directly

        return [
            'tasks' => array_map(fn (Task $task) => [
                'id' => $task->id,
                'description' => $task->description,
                'status' => $task->status->value,
            ], $tasks),
            'current_task' => $currentTask ? [
                'id' => $currentTask->id,
                'description' => $currentTask->description,
                'status' => $currentTask->status->value,
            ] : null,
            'conversation_stats' => $this->conversationBuffer->getStats(),
        ];
    }

    /**
     * Multi-channel processing: separate analysis, planning, execution, reflection
     */
    protected function processWithChannels(string $userInput): AgentResponse
    {
        // Quick Initial Assessment - Low effort for immediate feedback
        $this->progressReporter->report('quick_assessment', ['message' => 'Quick initial assessment...']);
        $quickClassification = $this->requestClassifier->quickClassify($userInput);

        // If we have high confidence and it's simple, provide immediate response
        if ($quickClassification['confidence'] > 0.85 && $quickClassification['complexity'] === 'simple') {
            $this->progressReporter->report('quick_response', [
                'message' => 'Providing quick response...',
                'type' => $quickClassification['request_type'],
                'confidence' => $quickClassification['confidence'],
            ]);

            $handler = $this->handlerRegistry->resolve($quickClassification);
            $response = $handler->handle($userInput, $quickClassification, []);

            // Log quick response
            $this->logger?->info('Quick response provided', [
                'type' => $quickClassification['request_type'],
                'confidence' => $quickClassification['confidence'],
            ]);

            return $response;
        }

        // For complex requests, use deep processing channels
        $this->progressReporter->report('deep_processing', ['message' => 'Processing complex request with multi-channel analysis...']);

        // Channel 1: Private analysis (enhanced understanding)
        $this->progressReporter->report('analyzing', ['message' => 'Analyzing request with enhanced intelligence...']);
        $analysis = $this->requestClassifier->analyze($userInput);

        // Channel 2: Public classification and routing (transparent to user)
        $this->progressReporter->report('classifying', ['message' => 'Classifying request type with self-consistent reasoning...']);
        $classification = $this->requestClassifier->classify($userInput, $analysis);

        // Channel 3: Route to appropriate handler
        $handler = $this->handlerRegistry->resolve($classification);

        // Channel 4: Execute with monitoring and reflection
        $response = $handler->handle($userInput, $classification, $analysis);

        // Channel 5: Post-execution reflection and learning
        $this->reflectOnExecution($userInput, $response, $classification);

        return $response;
    }

    /**
     * Post-execution reflection and learning
     */
    protected function reflectOnExecution(string $input, AgentResponse $response, array $classification): void
    {
        // Simple reflection for now - can be enhanced with pattern learning
        $this->logger?->info('Request completed', [
            'classification_type' => $classification['request_type'] ?? 'unknown',
            'consistency_score' => $classification['consistency_score'] ?? 0,
            'response_length' => mb_strlen($response->content),
            'success' => $response->success,
        ]);

        // Store successful patterns for future learning
        if ($response->success && ($classification['consistency_score'] ?? 0) > 0.8) {
            $this->recordSuccessPattern($input, $classification, $response);
        }
    }

    /**
     * Process with channels, falling back to simplified conversation on failure
     */
    protected function processWithChannelsAndRecovery(string $userInput): AgentResponse
    {
        return $this->errorRecoveryPolicy->runWithRecovery(
            userInput: $userInput,
            primaryProcessor: fn (string $input): AgentResponse => $this->processWithChannels($input),
            simplifiedProcessor: fn (string $input): array => $this->createSimplifiedFallback($input),
        );
    }

    /**
     * Create a simplified conversation fallback when primary processing fails.
     *
     * @return array{response: AgentResponse, classification: array}
     */
    protected function createSimplifiedFallback(string $userInput): array
    {
        $classification = $this->requestClassifier->quickClassify($userInput);
        $handler = $this->handlerRegistry->resolve([
            'request_type' => RequestType::Conversation->value,
        ]);

        return [
            'response' => $handler->handle($userInput, $classification, []),
            'classification' => $classification,
        ];
    }

    /**
     * Record successful patterns for future learning
     */
    protected function recordSuccessPattern(string $input, array $classification, AgentResponse $response): void
    {
        // This could be enhanced to store patterns in database or file for persistence
        $this->logger?->debug('Recording success pattern', [
            'input_pattern' => mb_substr($input, 0, 100),
            'classification' => $classification['request_type'],
            'confidence' => $classification['confidence'],
            'response_success' => $response->success,
        ]);
    }

}
