<?php

namespace HelgeSverre\Swarm\CLI\Process;

use Exception;
use HelgeSverre\Swarm\Agent\CodingAgent;
use HelgeSverre\Swarm\Core\ToolExecutor;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Task\TaskManager;
use OpenAI;

/**
 * Worker process that runs in a child process and streams progress updates
 * via stdout to the parent process
 */
class WorkerProcess
{
    /**
     * Process a request asynchronously with streaming updates
     */
    public static function processRequest(string $input, int $timeout = 300): void
    {
        // Set execution time limit based on timeout
        set_time_limit($timeout);

        // Register signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () {
                self::sendUpdate([
                    'type' => 'status',
                    'status' => 'error',
                    'error' => 'Process terminated by timeout signal',
                ]);
                exit(1);
            });

            pcntl_signal(SIGALRM, function () {
                self::sendUpdate([
                    'type' => 'status',
                    'status' => 'error',
                    'error' => 'Process exceeded time limit',
                ]);
                exit(1);
            });

            // Enable async signal handling
            pcntl_async_signals(true);
        }

        try {
            // Send initial status
            self::sendUpdate([
                'type' => 'status',
                'status' => 'initializing',
                'message' => 'Starting request processing...',
            ]);

            // Create Application instance for bootstrapping
            $app = new \HelgeSverre\Swarm\Core\Application(dirname(__DIR__, 2));

            // Get logger from Application
            $logger = $app->logger();

            // Initialize services
            self::sendUpdate([
                'type' => 'status',
                'status' => 'initializing',
                'message' => 'Setting up tools and services...',
            ]);

            // Create EventBus for the tools and agent
            $eventBus = new EventBus;

            $toolExecutor = ToolExecutor::createWithDefaultTools($logger);
            $taskManager = new TaskManager($logger);

            // Setup OpenAI client using Application config
            $apiKey = $app->config('openai.api_key');
            if (! $apiKey) {
                throw new Exception('OpenAI API key not found in environment');
            }

            $openAI = OpenAI::client($apiKey);

            // Create agent with progress callback
            $agent = new CodingAgent(
                toolExecutor: $toolExecutor,
                taskManager: $taskManager,
                llmClient: $openAI,
                logger: $logger,
                model: $app->config('openai.model', 'gpt-4o-mini'),
                temperature: $app->config('openai.temperature', 0.7)
            );

            // Load conversation history from state file if it exists
            $stateFile = getcwd() . '/.swarm.json';
            if (file_exists($stateFile)) {
                $stateContent = file_get_contents($stateFile);
                if (! empty(mb_trim($stateContent))) {
                    $state = json_decode($stateContent, true);
                    if ($state && isset($state['conversation_history']) && is_array($state['conversation_history'])) {
                        $agent->setConversationHistory($state['conversation_history']);
                        $logger?->debug('Restored conversation history', [
                            'count' => count($state['conversation_history']),
                        ]);
                    }
                }
            }

            // Subscribe to EventBus events for progress tracking
            // Track last heartbeat time and last state sync
            $lastHeartbeat = time();
            $heartbeatInterval = (int) ($_ENV['SWARM_HEARTBEAT_INTERVAL'] ?? 30);
            $lastStateSync = 0;
            $stateSyncThrottle = 0.1; // 100ms throttle for state updates

            // Track operation start times
            $operationStartTimes = [];
            $lastClassification = null;
            $activePlan = null;

            // Subscribe to ProcessingEvent
            $eventBus->on(\HelgeSverre\Swarm\Events\ProcessingEvent::class,
                function (\HelgeSverre\Swarm\Events\ProcessingEvent $event) use (
                    &$lastHeartbeat,
                    &$lastStateSync,
                    $heartbeatInterval,
                    $stateSyncThrottle,
                    $agent,
                    $toolExecutor,
                    &$operationStartTimes,
                    &$lastClassification,
                    &$activePlan
                ) {
                    $operation = $event->operation;
                    $details = $event->details;

                    // Track operation start time
                    if (! isset($operationStartTimes[$operation])) {
                        $operationStartTimes[$operation] = microtime(true);
                    }

                    // Store classification results
                    if ($operation === 'classifying' && ($details['phase'] ?? '') === 'classification_complete') {
                        $lastClassification = $details;
                    }

                    // Store active plan
                    if ($operation === 'planning_task' && ($details['phase'] ?? '') === 'plan_complete') {
                        $activePlan = $details;
                    }

                    // Create detailed message based on operation and phase
                    $message = self::getDetailedMessage($operation, $details);

                    // Enhanced progress details
                    $enrichedDetails = array_merge($details, [
                        'timestamp' => microtime(true),
                        'memory_usage' => memory_get_usage(true),
                        'operation_id' => uniqid($operation . '_'),
                    ]);

                    self::sendUpdate([
                        'type' => 'progress',
                        'operation' => $operation,
                        'message' => $message,
                        'details' => $enrichedDetails,
                        'context' => [
                            'conversation_length' => count($agent->getConversationHistory()),
                            'task_queue_size' => count($agent->getTaskManager()->getTasks()),
                            'tools_available' => count($toolExecutor->getRegisteredTools()),
                        ],
                    ]);

                    // Send comprehensive state sync with throttling
                    $now = microtime(true);
                    if ($now - $lastStateSync > $stateSyncThrottle) {
                        $status = $agent->getStatus();
                        self::sendUpdate([
                            'type' => 'state_sync',
                            'data' => [
                                'agent_state' => [
                                    'operation' => $operation,
                                    'phase' => $details['phase'] ?? 'processing',
                                    'details' => $details,
                                    'start_time' => $operationStartTimes[$operation] ?? microtime(true),
                                ],
                                'tasks' => $status['tasks'],
                                'current_task' => $status['current_task'],
                                'conversation_history' => $agent->getConversationHistory(),
                                'tool_log' => array_slice($toolExecutor->getExecutionLog(), -10), // Last 10 tool executions
                                'operation' => $operation,
                                'operation_details' => $details,
                                'decision_context' => [
                                    'last_classification' => $lastClassification,
                                    'active_plan' => $activePlan,
                                    'pending_operations' => array_keys($operationStartTimes),
                                ],
                            ],
                        ]);
                        $lastStateSync = $now;
                    }

                    // Send heartbeat if enough time has passed
                    $now = time();
                    if ($now - $lastHeartbeat >= $heartbeatInterval) {
                        self::sendUpdate([
                            'type' => 'heartbeat',
                            'message' => 'Process is still running...',
                            'elapsed' => $now - $lastHeartbeat,
                        ]);
                        $lastHeartbeat = $now;
                    }
                }
            );

            // Subscribe to ToolStartedEvent
            $eventBus->on(\HelgeSverre\Swarm\Events\ToolStartedEvent::class,
                function (\HelgeSverre\Swarm\Events\ToolStartedEvent $event) {
                    self::sendUpdate([
                        'type' => 'tool_started',
                        'tool' => $event->tool,
                        'params' => $event->params,
                        'message' => "Starting tool: {$event->tool}",
                    ]);
                }
            );

            // Subscribe to ToolCompletedEvent
            $eventBus->on(\HelgeSverre\Swarm\Events\ToolCompletedEvent::class,
                function (\HelgeSverre\Swarm\Events\ToolCompletedEvent $event) {
                    self::sendUpdate([
                        'type' => 'tool_completed',
                        'tool' => $event->tool,
                        'params' => $event->params,
                        'success' => $event->result->isSuccess(),
                        'duration' => $event->duration,
                        'message' => "Tool {$event->tool} completed" . ($event->result->isSuccess() ? ' successfully' : ' with errors'),
                    ]);
                }
            );

            $logger?->info('Processing user request (streaming)', ['input' => $input]);

            // Send processing status
            self::sendUpdate([
                'type' => 'status',
                'status' => 'processing',
                'message' => 'Processing your request...',
            ]);

            // Process the request
            $response = $agent->processRequest($input);

            // Send completion status with response
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
        // Ensure we have a type
        $data['type'] = $data['type'] ?? 'status';
        $data['timestamp'] = microtime(true);

        // Send as JSON line
        echo json_encode($data) . "\n";

        // Flush output immediately
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
