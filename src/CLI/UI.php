<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\CLI\Activity\ActivityEntry;
use HelgeSverre\Swarm\CLI\Activity\ConversationEntry;
use HelgeSverre\Swarm\CLI\Activity\NotificationEntry;
use HelgeSverre\Swarm\CLI\Activity\ToolCallEntry;
use HelgeSverre\Swarm\Core\ToolResponse;
use HelgeSverre\Swarm\Enums\CLI\NotificationType;
use function Laravel\Prompts\text;

class UI
{
    protected array $history = [];

    protected bool $isProcessing = false;

    protected ?string $lastOperation = null;

    protected array $activeTasks = [];

    protected array $contextData = [];

    protected bool $showPanels = false;

    protected ?TerminalUI $terminalUI = null;

    protected bool $richMode = false;

    public function __construct()
    {
        // Set up terminal for better unicode support
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        echo Termz::statusLine('💮 ', 'Swarm', 'Ready', '', Termz::GREEN, Termz::WHITE, Termz::DIM);
        echo Termz::divider(0, '─');
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

        // Update panels if visible
        if ($this->showPanels) {
            $this->refreshPanels();
        }
    }

    public function displayResponse(AgentResponse $response): void
    {
        $this->addToHistory(new ConversationEntry('assistant', $response->getMessage(), time()));

        // Display the response compactly
        $message = $response->getMessage();

        if ($response->isSuccess()) {
            echo Termz::activityLine('info', $message);
        } else {
            echo Termz::activityLine('error', $message);
        }
    }

    public function displayError(string $errorMessage): void
    {
        $this->addToHistory(new ConversationEntry('error', $errorMessage, time()));
        echo Termz::activityLine('error', $errorMessage);
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
                echo Termz::activityLine('tool', "Read from {$path}");
            } elseif ($tool === 'write_file') {
                $path = $params['path'] ?? 'file';
                echo Termz::activityLine('tool', "Wrote to {$path}");
            }
        } else {
            $errorMsg = $toolResponse ? $toolResponse->getError() : 'Unknown error';
            echo Termz::activityLine('error', "Tool {$tool} failed: {$errorMsg}");
        }
    }

    public function showNotification(string $message, string $type = 'info'): void
    {
        $notificationType = NotificationType::fromString($type);
        $this->addToHistory(new NotificationEntry($message, $notificationType, time()));

        // Compact notification display
        echo Termz::activityLine($type, $message);
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
        echo Termz::activityLine('system', '👋 Goodbye!');
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
     * Toggle panel display / rich mode
     */
    public function togglePanels(): void
    {
        $this->richMode = ! $this->richMode;

        if ($this->richMode && ! $this->terminalUI) {
            $this->terminalUI = TerminalUI::getInstance();
            $this->syncToTerminalUI();
        }

        if ($this->richMode) {
            $this->terminalUI->render();
        }
    }

    /**
     * Show keyboard shortcuts help
     */
    public function showHelp(): void
    {
        echo TermzLayout::helpOverlay();
    }

    protected function showAgentThinking(array $agentState): void
    {
        $operation = $agentState['operation'] ?? '';
        $phase = $agentState['phase'] ?? '';
        $details = $agentState['details'] ?? [];

        // Calculate content width (75% of terminal)
        $terminalWidth = $this->getTerminalWidth();
        $contentWidth = (int) ($terminalWidth * 0.75);

        // Only show if operation changed or specific phases complete
        if ($operation !== $this->lastOperation || $phase === 'completed' || $phase === 'classification_complete' || $phase === 'plan_complete' || $phase === 'extraction_complete') {
            $this->lastOperation = $operation;

            $icon = $this->getOperationIcon($operation);
            $message = $this->formatOperationMessage($operation, $phase, $details);

            // Compact thinking display
            echo Termz::activityLine('thinking', "{$message}...");

            // Show key details inline for certain operations
            if ($operation === 'classifying' && $phase === 'classification_complete' && isset($details['type'])) {
                $type = $details['type'];
                $confidence = $details['confidence'];
                echo Termz::indentedItem("Type: {$type} (confidence: {$confidence})", 1, Termz::ARROW, Termz::DIM);
            }

            // Compact task display when tasks are extracted
            if ($operation === 'extracting_tasks' && $phase === 'extraction_complete') {
                if (isset($details['tasks']) && is_array($details['tasks'])) {
                    echo Termz::activityLine('task', "Found {$details['task_count']} tasks");

                    foreach ($details['tasks'] as $index => $taskData) {
                        $number = $index + 1;
                        $description = $taskData['description'] ?? '';
                        $status = $taskData['status'] ?? 'pending';

                        // Compact task display
                        echo '  ' . Termz::taskLine($number, $description, $status);

                        // Only show step count if available, no extra whitespace
                        if (! empty($taskData['steps']) && is_array($taskData['steps'])) {
                            $stepCount = count($taskData['steps']);
                            $stepText = $stepCount === 1 ? '1 step' : "{$stepCount} steps";
                            echo '    ' . Termz::colorize("→ {$stepText}", 'dim') . "\n";
                        }
                    }
                } elseif (isset($details['task_count'])) {
                    echo Termz::activityLine('task', "Found {$details['task_count']} tasks");
                }
            }

            if ($operation === 'planning_task' && $phase === 'plan_complete' && isset($details['step_count'])) {
                $stepCount = $details['step_count'];
                echo Termz::indentedItem("Plan ready with {$stepCount} steps", 1, Termz::ARROW, Termz::DIM);
            }

            if ($operation === 'executing_tool' && isset($details['tool_name'])) {
                $toolName = $details['tool_name'];
                if ($phase === 'completed') {
                    $status = $details['success'] ? 'success' : 'error';
                    echo Termz::activityLine('tool', "Tool {$toolName}", null, $details['success'] ? 'completed' : 'failed');
                } else {
                    echo Termz::activityLine('tool', "Running {$toolName}...");
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
     * Word wrap text to a percentage of terminal width
     */
    protected function wordWrap(string $text, int $width): string
    {
        return Termz::wordWrap($text, $width);
    }

    /**
     * Get terminal width for responsive layouts
     */
    protected function getTerminalWidth(): int
    {
        return Termz::getTerminalWidth();
    }

    /**
     * Colorize text with ANSI escape codes
     */
    protected function colorize(string $text, string $style): string
    {
        return Termz::colorize($text, $style);
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
            $truncatedDesc = Termz::truncate($taskDesc, 40);
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
            echo TermzLayout::expandableSection(
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
     * Sync state to TerminalUI
     */
    protected function syncToTerminalUI(): void
    {
        if (! $this->terminalUI) {
            return;
        }

        // Sync tasks
        $tasks = [];
        foreach ($this->activeTasks as $task) {
            $tasks[] = [
                'description' => $task['description'] ?? 'Task',
                'status' => $task['status'] ?? 'pending',
            ];
        }
        $this->terminalUI->setTasks($tasks);

        // Sync context
        $this->terminalUI->setContext($this->contextData);

        // Sync history
        foreach ($this->history as $entry) {
            if ($entry instanceof ConversationEntry) {
                $type = $entry->role === 'user' ? 'command' : 'info';
                $this->terminalUI->addHistory($type, $entry->message, $entry->timestamp);
            } elseif ($entry instanceof ToolCallEntry) {
                $this->terminalUI->addHistory('tool', "Tool: {$entry->toolName}", $entry->timestamp);
            }
        }
    }

    /**
     * Refresh side panels
     */
    protected function refreshPanels(): void
    {
        // This would typically clear and redraw panels
        // For now, just show a divider to indicate panels are active
        echo Termz::divider(0, '═');
        echo Termz::DIM . 'Panels: Press ⌥T to toggle task panel' . Termz::RESET . "\n";
    }

    /**
     * Format detailed task progress
     */
    protected function formatTaskProgress(array $task): string
    {
        $output = '';

        // Progress bar
        if (isset($task['progress'])) {
            $output .= Termz::progressBar(
                $task['progress']['current'],
                $task['progress']['total'],
                30,
                true
            ) . "\n";
        }

        // Current step
        if (isset($task['current_step'])) {
            $output .= Termz::BOLD . 'Current: ' . Termz::RESET . $task['current_step'] . "\n";
        }

        // Tool being used
        if (isset($task['tool'])) {
            $output .= Termz::DIM . 'Using: ' . $task['tool'] . Termz::RESET . "\n";
        }

        // Timing (if available)
        if (isset($task['timing'])) {
            $elapsed = $task['timing']['elapsed'] ?? 'calculating...';
            $output .= Termz::DIM . 'Time: ' . $elapsed . Termz::RESET . "\n";
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
