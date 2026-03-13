<?php

namespace HelgeSverre\Swarm\CLI\Process;

use Exception;
use HelgeSverre\Swarm\Application\Runtime\RuntimeKernel;
use HelgeSverre\Swarm\Core\Application;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ProcessingEvent;
use HelgeSverre\Swarm\Events\ToolCompletedEvent;
use HelgeSverre\Swarm\Events\ToolStartedEvent;

/**
 * Worker process that runs in a child process and streams progress updates
 * via stdout to the parent process
 */
class WorkerProcess
{
    protected \HelgeSverre\Swarm\Agent\CodingAgent $agent;

    protected ToolExecutor $toolExecutor;

    protected int $lastHeartbeat;

    protected float $lastStateSync = 0;

    protected int $heartbeatInterval;

    protected float $stateSyncThrottle = 0.1;

    /** @var array<string, float> */
    protected array $operationStartTimes = [];

    protected ?array $lastClassification = null;

    protected ?array $activePlan = null;

    /**
     * Process a request asynchronously with streaming updates
     */
    public static function processRequest(Application $app, string $input, int $timeout = 300): void
    {
        $worker = new self;
        $worker->run($app, $input, $timeout);
    }

    protected function run(Application $app, string $input, int $timeout): void
    {
        set_time_limit($timeout);
        $this->registerSignalHandlers();
        $this->lastHeartbeat = time();
        $this->heartbeatInterval = (int) ($_ENV['SWARM_HEARTBEAT_INTERVAL'] ?? 30);

        try {
            self::sendUpdate(['type' => 'status', 'status' => 'initializing', 'message' => 'Starting request processing...']);

            $logger = $app->logger();

            self::sendUpdate(['type' => 'status', 'status' => 'initializing', 'message' => 'Setting up tools and services...']);

            $runtime = RuntimeKernel::bootWorker($app);
            $eventBus = $runtime->eventBus;
            $this->toolExecutor = $runtime->toolExecutor;
            $taskManager = $runtime->taskManager;
            $this->agent = $runtime->codingAgent;

            $this->restoreConversationHistory($logger);
            $this->restoreTaskHistory($taskManager, $logger);
            $this->subscribeToEvents($eventBus);

            $logger?->info('Processing user request (streaming)', ['input' => $input]);

            self::sendUpdate(['type' => 'status', 'status' => 'processing', 'message' => 'Processing your request...']);

            $response = $this->agent->processRequest($input);

            self::sendUpdate([
                'type' => 'status',
                'status' => 'completed',
                'message' => 'Request completed successfully',
                'response' => [
                    'message' => $response->getMessage(),
                    'success' => $response->isSuccess(),
                ],
            ]);
        } catch (Exception $e) {
            $logger?->error('Unexpected error in streaming process', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            self::sendUpdate([
                'type' => 'status',
                'status' => 'error',
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ]);
        }
    }

    protected function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGTERM, function () {
            self::sendUpdate(['type' => 'status', 'status' => 'error', 'error' => 'Process terminated by timeout signal']);
            exit(1);
        });

        pcntl_signal(SIGALRM, function () {
            self::sendUpdate(['type' => 'status', 'status' => 'error', 'error' => 'Process exceeded time limit']);
            exit(1);
        });

        pcntl_async_signals(true);
    }

    protected function restoreConversationHistory(mixed $logger): void
    {
        $state = $this->loadState();
        if (! isset($state['conversation_history']) || ! is_array($state['conversation_history'])) {
            return;
        }

        $this->agent->setConversationHistory($state['conversation_history']);
        $logger?->debug('Restored conversation history', [
            'count' => count($state['conversation_history']),
        ]);
    }

    protected function restoreTaskHistory(\HelgeSverre\Swarm\Task\TaskManager $taskManager, mixed $logger): void
    {
        $state = $this->loadState();
        if (! isset($state['task_history']) || ! is_array($state['task_history'])) {
            return;
        }

        $taskManager->setTaskHistory($state['task_history']);
        $logger?->debug('Restored task history', [
            'count' => count($state['task_history']),
        ]);
    }

    protected function loadState(): array
    {
        $stateFile = getcwd() . '/.swarm.json';

        if (! file_exists($stateFile)) {
            return [];
        }

        $stateContent = file_get_contents($stateFile);
        if (empty(mb_trim($stateContent))) {
            return [];
        }

        $state = json_decode($stateContent, true);

        return is_array($state) ? $state : [];
    }

    protected function subscribeToEvents(EventBus $eventBus): void
    {
        $eventBus->on(ProcessingEvent::class, $this->handleProcessingEvent(...));
        $eventBus->on(ToolStartedEvent::class, $this->handleToolStarted(...));
        $eventBus->on(ToolCompletedEvent::class, $this->handleToolCompleted(...));
    }

    protected function handleProcessingEvent(ProcessingEvent $event): void
    {
        $operation = $event->operation;
        $details = $event->details;

        $this->operationStartTimes[$operation] ??= microtime(true);

        if ($operation === 'classifying' && ($details['phase'] ?? '') === 'classification_complete') {
            $this->lastClassification = $details;
        }

        if ($operation === 'planning_task' && ($details['phase'] ?? '') === 'plan_complete') {
            $this->activePlan = $details;
        }

        $enrichedDetails = array_merge($details, [
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true),
            'operation_id' => uniqid($operation . '_'),
        ]);

        self::sendUpdate([
            'type' => 'progress',
            'operation' => $operation,
            'message' => self::getDetailedMessage($operation, $details),
            'details' => $enrichedDetails,
            'context' => [
                'conversation_length' => count($this->agent->getConversationHistory()),
                'task_queue_size' => count($this->agent->getTaskManager()->getTasks()),
                'tools_available' => count($this->toolExecutor->getRegisteredTools()),
            ],
        ]);

        $this->maybeSendStateSync($operation, $details);
        $this->maybeSendHeartbeat();
    }

    protected function maybeSendStateSync(string $operation, array $details): void
    {
        $now = microtime(true);
        if ($now - $this->lastStateSync <= $this->stateSyncThrottle) {
            return;
        }

        $status = $this->agent->getStatus();

        self::sendUpdate([
            'type' => 'state_sync',
            'data' => [
                'agent_state' => [
                    'operation' => $operation,
                    'phase' => $details['phase'] ?? 'processing',
                    'details' => $details,
                    'start_time' => $this->operationStartTimes[$operation] ?? microtime(true),
                ],
                'tasks' => $status['tasks'],
                'current_task' => $status['current_task'],
                'conversation_history' => $this->agent->getConversationHistory(),
                'tool_log' => array_slice($this->toolExecutor->getExecutionLog(), -10),
                'operation' => $operation,
                'operation_details' => $details,
                'decision_context' => [
                    'last_classification' => $this->lastClassification,
                    'active_plan' => $this->activePlan,
                    'pending_operations' => array_keys($this->operationStartTimes),
                ],
            ],
        ]);

        $this->lastStateSync = $now;
    }

    protected function maybeSendHeartbeat(): void
    {
        $now = time();
        if ($now - $this->lastHeartbeat < $this->heartbeatInterval) {
            return;
        }

        self::sendUpdate([
            'type' => 'heartbeat',
            'message' => 'Process is still running...',
            'elapsed' => $now - $this->lastHeartbeat,
        ]);

        $this->lastHeartbeat = $now;
    }

    protected function handleToolStarted(ToolStartedEvent $event): void
    {
        self::sendUpdate([
            'type' => 'tool_started',
            'tool' => $event->tool,
            'params' => $event->params,
            'message' => "Starting tool: {$event->tool}",
        ]);
    }

    protected function handleToolCompleted(ToolCompletedEvent $event): void
    {
        $outcome = $event->result->isSuccess() ? ' successfully' : ' with errors';

        self::sendUpdate([
            'type' => 'tool_completed',
            'tool' => $event->tool,
            'params' => $event->params,
            'success' => $event->result->isSuccess(),
            'duration' => $event->duration,
            'message' => "Tool {$event->tool} completed" . $outcome,
        ]);
    }

    /**
     * Get detailed message for operation
     */
    protected static function getDetailedMessage(string $operation, array $details): string
    {
        $phase = $details['phase'] ?? '';

        return match ($operation) {
            'classifying' => match ($phase) {
                'understanding_intent' => 'Analyzing your request...',
                'calling_ai' => 'Consulting AI to understand intent...',
                'classification_complete' => "Classified as {$details['type']} (confidence: {$details['confidence']})",
                default => 'Analyzing request type...'
            },
            'extracting_tasks' => match ($phase) {
                'analyzing_request' => 'Breaking down your request into tasks...',
                'calling_ai' => 'Asking AI to identify specific tasks...',
                'extraction_complete' => "Found {$details['task_count']} tasks to complete",
                default => 'Identifying tasks to complete...'
            },
            'planning_task' => match ($phase) {
                'analyzing_requirements' => "Analyzing requirements for: {$details['task_description']}",
                'calling_ai' => 'Creating execution plan...',
                'plan_complete' => "Plan ready with {$details['step_count']} steps",
                default => 'Planning: ' . ($details['task_description'] ?? '')
            },
            'executing_task' => 'Executing: ' . ($details['task_description'] ?? ''),
            'executing_tool' => match ($phase) {
                'preparing' => "Preparing to run {$details['tool_name']}...",
                'completed' => "Tool {$details['tool_name']} completed" . ($details['success'] ? ' successfully' : ' with errors'),
                default => "Running {$details['tool_name']}..."
            },
            'calling_openai' => match ($phase) {
                'preparing_request' => 'Preparing AI request...',
                'sending_request' => "Sending to {$details['model']} model...",
                'processing_response' => 'Processing AI response...',
                default => 'Thinking...'
            },
            'generating_summary' => 'Generating summary of completed work...',
            default => ucfirst(str_replace('_', ' ', $operation)) . '...'
        };
    }

    /**
     * Send a status update via stdout
     */
    protected static function sendUpdate(array $data): void
    {
        $data['type'] ??= 'status';
        $data['timestamp'] = microtime(true);

        echo json_encode($data) . "\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
