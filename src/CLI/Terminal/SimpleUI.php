<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\CLI\Activity\ActivityEntry;
use HelgeSverre\Swarm\CLI\Activity\ConversationEntry;
use HelgeSverre\Swarm\CLI\Activity\NotificationEntry;
use HelgeSverre\Swarm\CLI\Activity\ToolCallEntry;
use HelgeSverre\Swarm\Core\ToolResponse;
use HelgeSverre\Swarm\Enums\CLI\NotificationType;

use function Laravel\Prompts\text;

class SimpleUI
{
    protected array $history = [];

    protected bool $isProcessing = false;

    protected ?string $lastOperation = null;

    protected array $activeTasks = [];

    protected array $contextData = [];

    protected bool $showPanels = false;

    protected bool $richMode = false;

    public function __construct()
    {
        // Set up terminal for better unicode support
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        echo Ansi::statusLine('💮 ', 'Swarm', 'Ready', '', Ansi::GREEN, Ansi::WHITE, Ansi::DIM);
        echo Ansi::divider(0, '─');
    }

    public function prompt(string $label = '>'): string
    {
        return text(
            label: '',
            placeholder: 'Type your message...',
            required: true
        );
    }

    public function refresh(array $status): void
    {
        // Update internal state
        if (! empty($status['tasks'])) {
            $this->activeTasks = $status['tasks'];
        }

        if (! empty($status['context'])) {
            $this->contextData = $status['context'];
        }

        // Show agent state updates if there's a change
        if (! empty($status['agent_state'])) {
            $this->showAgentThinking($status['agent_state']);
        }
    }

    public function displayResponse(AgentResponse $response): void
    {
        $this->addToHistory(new ConversationEntry('assistant', $response->getMessage(), time()));

        // Display the response compactly
        $message = $response->getMessage();

        if ($response->isSuccess()) {
            echo Ansi::activityLine('info', $message);
        } else {
            echo Ansi::activityLine('error', $message);
        }
    }

    public function displayError(string $errorMessage): void
    {
        $this->addToHistory(new ConversationEntry('error', $errorMessage, time()));
        echo Ansi::activityLine('error', $errorMessage);
    }

    public function displayToolCall(string $tool, array $params, $result): void
    {
        // Convert result to ToolResponse if needed
        $toolResponse = null;
        if ($result instanceof ToolResponse) {
            $toolResponse = $result;
        } elseif (is_array($result) && isset($result['success'])) {
            $toolResponse = $result['success']
                ? ToolResponse::success($result['data'] ?? [])
                : ToolResponse::error($result['error'] ?? 'Unknown error');
        }

        $entry = new ToolCallEntry($tool, $params, $toolResponse, time());
        $this->addToHistory($entry);

        // Show tool execution using Termz activity lines
        if ($toolResponse && $toolResponse->isSuccess()) {
            // Don't show successful tool calls unless they have important output
            if ($tool === 'read_file' || $tool === 'grep') {
                $path = $params['path'] ?? 'file';
                echo Ansi::activityLine('tool', "Read from {$path}");
            } elseif ($tool === 'write_file') {
                $path = $params['path'] ?? 'file';
                echo Ansi::activityLine('tool', "Wrote to {$path}");
            }
        } else {
            $errorMsg = $toolResponse ? $toolResponse->getError() : 'Unknown error';
            echo Ansi::activityLine('error', "Tool {$tool} failed: {$errorMsg}");
        }
    }

    public function showNotification(string $message, string $type = 'info'): void
    {
        $notificationType = NotificationType::fromString($type);
        $this->addToHistory(new NotificationEntry($message, $notificationType, time()));

        // Compact notification display
        echo Ansi::activityLine($type, $message);
    }

    public function startProcessing(): void
    {
        $this->isProcessing = true;
        $this->lastOperation = null;
    }

    public function stopProcessing(): void
    {
        $this->isProcessing = false;
        $this->lastOperation = null;
    }

    public function showProcessing(): void
    {
        // No-op in chat mode
    }

    public function updateProcessingMessage(string $message): void
    {
        // Messages are shown via showAgentThinking
    }

    public function cleanup(): void
    {
        $this->stopProcessing();
        echo Ansi::activityLine('system', '👋 Goodbye!');
    }

    /**
     * Run a long operation with a spinner (for compatibility)
     */
    public function runWithSpinner(string $message, callable $callback): mixed
    {
        // Use Laravel Prompts spin function
        return \Laravel\Prompts\spin(
            fn () => $callback(),
            $message
        );
    }

    /**
     * Show keyboard shortcuts help
     */
    public function showHelp(): void
    {
        echo Renderer::helpOverlay();
    }

    protected function showAgentThinking(array $agentState): void
    {
        $operation = $agentState['operation'] ?? '';
        $phase = $agentState['phase'] ?? '';
        $details = $agentState['details'] ?? [];

        // Calculate content width (75% of terminal)
        $terminalWidth = Ansi::getTerminalWidth();
        $contentWidth = (int) ($terminalWidth * 0.75);

        // Only show if operation changed or specific phases complete
        if ($operation !== $this->lastOperation || $phase === 'completed' || $phase === 'classification_complete' || $phase === 'plan_complete' || $phase === 'extraction_complete') {
            $this->lastOperation = $operation;

            $icon = $this->getOperationIcon($operation);
            $message = $this->formatOperationMessage($operation, $phase, $details);

            // Compact thinking display
            echo Ansi::activityLine('thinking', "{$message}...");

            // Show key details inline for certain operations
            if ($operation === 'classifying' && $phase === 'classification_complete' && isset($details['type'])) {
                $type = $details['type'];
                $confidence = $details['confidence'];
                echo Ansi::indentedItem("Type: {$type} (confidence: {$confidence})", 1, Ansi::ARROW, Ansi::DIM);
            }

            // Compact task display when tasks are extracted
            if ($operation === 'extracting_tasks' && $phase === 'extraction_complete') {
                if (isset($details['tasks']) && is_array($details['tasks'])) {
                    echo Ansi::activityLine('task', "Found {$details['task_count']} tasks");

                    foreach ($details['tasks'] as $index => $taskData) {
                        $number = $index + 1;
                        $description = $taskData['description'] ?? '';
                        $status = $taskData['status'] ?? 'pending';

                        // Compact task display
                        echo '  ' . Ansi::taskLine($number, $description, $status);

                        // Only show step count if available, no extra whitespace
                        if (! empty($taskData['steps']) && is_array($taskData['steps'])) {
                            $stepCount = count($taskData['steps']);
                            $stepText = $stepCount === 1 ? '1 step' : "{$stepCount} steps";
                            echo '    ' . Ansi::colorize("→ {$stepText}", 'dim') . "\n";
                        }
                    }
                } elseif (isset($details['task_count'])) {
                    echo Ansi::activityLine('task', "Found {$details['task_count']} tasks");
                }
            }

            if ($operation === 'planning_task' && $phase === 'plan_complete' && isset($details['step_count'])) {
                $stepCount = $details['step_count'];
                echo Ansi::indentedItem("Plan ready with {$stepCount} steps", 1, Ansi::ARROW, Ansi::DIM);
            }

            if ($operation === 'executing_tool' && isset($details['tool_name'])) {
                $toolName = $details['tool_name'];
                if ($phase === 'completed') {
                    $status = $details['success'] ? 'success' : 'error';
                    echo Ansi::activityLine('tool', "Tool {$toolName}", null, $details['success'] ? 'completed' : 'failed');
                } else {
                    echo Ansi::activityLine('tool', "Running {$toolName}...");
                }
            }
        }

        // Handle task execution progress (always show, not just on operation change)
        if ($operation === 'executing_task') {
            $this->displayTaskProgress($details, $contentWidth);
        }
    }

    protected function addToHistory(ActivityEntry $entry): void
    {
        $this->history[] = $entry;

        // Keep only last 100 entries
        if (count($this->history) > 100) {
            array_shift($this->history);
        }
    }

    protected function getOperationIcon(string $operation): string
    {
        return match ($operation) {
            'classifying' => '🔍',
            'extracting_tasks' => '📋',
            'planning_task' => '📐',
            'executing_task' => '⚡',
            'executing_tool' => '🔧',
            'calling_openai' => '🤔',
            'generating_summary' => '📝',
            default => '⚙️'
        };
    }

    protected function formatOperationMessage(string $operation, string $phase, array $details): string
    {
        return match ($operation) {
            'classifying' => 'Analyzing request',
            'extracting_tasks' => 'Breaking down into tasks',
            'planning_task' => 'Planning execution',
            'executing_task' => 'Working on task',
            'executing_tool' => 'Using tool',
            'calling_openai' => 'Thinking',
            'generating_summary' => 'Summarizing',
            default => 'Processing'
        };
    }

    /**
     * Display task execution progress with progress bar
     */
    protected function displayTaskProgress(array $details, int $contentWidth): void
    {
        static $lastTaskId = null;
        static $lastStep = 0;

        $taskDesc = $details['task_description'] ?? 'Task';
        $currentStep = $details['current_step'] ?? 0;
        $totalSteps = $details['total_steps'] ?? 0;
        $taskId = $details['task_id'] ?? null;

        // Only display if we have valid step information and it's a new step
        if ($currentStep > 0 && $totalSteps > 0 && ($taskId !== $lastTaskId || $currentStep !== $lastStep)) {
            $lastTaskId = $taskId;
            $lastStep = $currentStep;

            // Enhanced progress display with more details
            $truncatedDesc = Ansi::truncate($taskDesc, 40);
            $taskInfo = [
                'description' => $taskDesc,
                'progress' => [
                    'current' => $currentStep,
                    'total' => $totalSteps,
                ],
                'status' => 'running',
                'id' => $taskId,
            ];

            // Add step details if available
            if (isset($details['step_description'])) {
                $taskInfo['current_step'] = $details['step_description'];
            }

            if (isset($details['current_tool'])) {
                $taskInfo['tool'] = $details['current_tool'];
            }

            // Add timing if available
            if (isset($details['start_time'])) {
                $elapsed = time() - $details['start_time'];
                $taskInfo['timing'] = ['elapsed' => $this->formatDuration($elapsed)];
            }

            // Use expandable section for detailed progress
            $progressDetail = $this->formatTaskProgress($taskInfo);
            echo Renderer::expandableSection(
                "task_{$taskId}",
                $truncatedDesc,
                $progressDetail,
                true, // Default expanded for running tasks
                2
            );

            // Update active tasks for panel display
            $this->activeTasks[$taskId] = $taskInfo;
        }
    }

    /**
     * Format detailed task progress
     */
    protected function formatTaskProgress(array $task): string
    {
        $output = '';

        // Progress bar
        if (isset($task['progress'])) {
            $output .= Ansi::progressBar(
                $task['progress']['current'],
                $task['progress']['total'],
                30,
                true
            ) . "\n";
        }

        // Current step
        if (isset($task['current_step'])) {
            $output .= Ansi::BOLD . 'Current: ' . Ansi::RESET . $task['current_step'] . "\n";
        }

        // Tool being used
        if (isset($task['tool'])) {
            $output .= Ansi::DIM . 'Using: ' . $task['tool'] . Ansi::RESET . "\n";
        }

        // Timing (if available)
        if (isset($task['timing'])) {
            $elapsed = $task['timing']['elapsed'] ?? 'calculating...';
            $output .= Ansi::DIM . 'Time: ' . $elapsed . Ansi::RESET . "\n";
        }

        return $output;
    }

    /**
     * Format duration in human-readable format
     */
    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;

            return "{$minutes}m {$secs}s";
        }
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "{$hours}h {$minutes}m";
    }
}
