<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\CLI\Activity\ToolCallEntry;
use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ProcessCompleteEvent;
use HelgeSverre\Swarm\Events\ProcessingEvent;
use HelgeSverre\Swarm\Events\ProcessProgressEvent;
use HelgeSverre\Swarm\Events\StateUpdateEvent;
use HelgeSverre\Swarm\Events\TaskUpdateEvent;
use HelgeSverre\Swarm\Events\ToolCompletedEvent;
use HelgeSverre\Swarm\Events\ToolStartedEvent;

class TuiViewModel
{
    const FOCUS_MAIN = 'main';

    const FOCUS_TASKS = 'tasks';

    const FOCUS_CONTEXT = 'context';

    protected bool $stateChanged = false;

    protected array $history = [];

    protected array $expandedThoughts = [];

    protected array $tasks = [];

    protected array $context = [
        'directory' => '',
        'files' => [],
        'tools' => [],
        'notes' => [],
    ];

    protected array $pendingToolCalls = [];

    protected array $activityFeed = [];

    protected string $currentTask = '';

    protected string $status = 'ready';

    protected int $currentStep = 0;

    protected int $totalSteps = 0;

    protected bool $showTaskOverlay = false;

    protected bool $showHelp = false;

    protected int $selectedTaskIndex = 0;

    protected int $selectedContextLine = 0;

    protected int $taskScrollOffset = 0;

    protected ?string $currentReasoning = null;

    protected bool $showReasoning = false;

    protected float $startTime;

    protected array $currentProgress = [];

    // Input state
    protected string $input = '';

    protected string $contextInput = '';

    protected string $currentFocus = 'main';

    public function __construct(
        protected EventBus $eventBus,
    ) {
        $this->subscribeToEvents();
        $this->startTime = microtime(true);
    }

    // ──────────────────────────────────────────────────────────────
    // State mutation methods
    // ──────────────────────────────────────────────────────────────

    public function addHistory(string $type, string $content, string $params = '', string $result = ''): void
    {
        $entry = [
            'time' => time(),
            'type' => $type,
            'content' => $content,
        ];

        if ($type === 'tool') {
            $entry['tool'] = $content;
            $entry['params'] = $params;
            $entry['result'] = $result;
        }

        $this->history[] = $entry;

        if (count($this->history) > 100) {
            array_shift($this->history);
        }
    }

    public function adjustTaskScroll(int $terminalHeight): void
    {
        $visibleHeight = $terminalHeight - 10;

        if ($this->selectedTaskIndex < $this->taskScrollOffset) {
            $this->taskScrollOffset = $this->selectedTaskIndex;
        } elseif ($this->selectedTaskIndex >= $this->taskScrollOffset + $visibleHeight) {
            $this->taskScrollOffset = $this->selectedTaskIndex - $visibleHeight + 1;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // UIInterface delegation methods
    // ──────────────────────────────────────────────────────────────

    public function displayResponse(AgentResponse $response): void
    {
        $this->addHistory('assistant', $response->getMessage());
        $this->stateChanged = true;
    }

    public function displayError(string $errorMessage): void
    {
        $this->addHistory('error', $errorMessage);
        $this->stateChanged = true;
    }

    public function showNotification(string $message, string $type = 'info'): void
    {
        $this->addHistory($type, $message);
        $this->stateChanged = true;
    }

    public function startProcessing(): void
    {
        $this->status = 'Processing...';
        $this->stateChanged = true;
    }

    public function stopProcessing(): void
    {
        $this->status = 'Ready';
        $this->stateChanged = true;
    }

    public function showProcessing(): void
    {
        // Empty body
    }

    public function refresh(array $state = []): void
    {
        if (! empty($state)) {
            foreach ($state as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }
        }

        $this->stateChanged = true;
    }

    public function updateProcessingMessage(string $message): void
    {
        $this->status = $message;
        $this->stateChanged = true;
    }

    // ──────────────────────────────────────────────────────────────
    // Toggle / consume helpers
    // ──────────────────────────────────────────────────────────────

    public function toggleNearestThought(int $mainAreaWidth): bool
    {
        $recentHistory = array_slice($this->history, -20);
        foreach (array_reverse($recentHistory) as $entry) {
            if ($entry['type'] === 'assistant' && isset($entry['thought'])) {
                $thoughtId = md5($entry['time'] . $entry['thought']);
                $thoughtLines = explode(' ', $entry['thought']);
                // Rough estimate of wrapped lines
                $wrappedCount = (int) ceil(mb_strlen($entry['thought']) / max(1, $mainAreaWidth - 15));

                if ($wrappedCount > 4) {
                    if (in_array($thoughtId, $this->expandedThoughts)) {
                        $this->expandedThoughts = array_diff($this->expandedThoughts, [$thoughtId]);
                    } else {
                        $this->expandedThoughts[] = $thoughtId;
                    }

                    return true;
                }
            }
        }

        return false;
    }

    public function consumeStateChanged(): bool
    {
        $changed = $this->stateChanged;
        $this->stateChanged = false;

        return $changed;
    }

    public function markStateChanged(): void
    {
        $this->stateChanged = true;
    }

    // ──────────────────────────────────────────────────────────────
    // Public getters
    // ──────────────────────────────────────────────────────────────

    public function getHistory(): array
    {
        return $this->history;
    }

    public function getTasks(): array
    {
        return $this->tasks;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getActivityFeed(): array
    {
        return $this->activityFeed;
    }

    public function getPendingToolCalls(): array
    {
        return $this->pendingToolCalls;
    }

    public function getExpandedThoughts(): array
    {
        return $this->expandedThoughts;
    }

    public function getCurrentProgress(): array
    {
        return $this->currentProgress;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCurrentTask(): string
    {
        return $this->currentTask;
    }

    public function getCurrentStep(): int
    {
        return $this->currentStep;
    }

    public function getTotalSteps(): int
    {
        return $this->totalSteps;
    }

    public function isShowTaskOverlay(): bool
    {
        return $this->showTaskOverlay;
    }

    public function isShowHelp(): bool
    {
        return $this->showHelp;
    }

    public function getSelectedTaskIndex(): int
    {
        return $this->selectedTaskIndex;
    }

    public function getSelectedContextLine(): int
    {
        return $this->selectedContextLine;
    }

    public function getTaskScrollOffset(): int
    {
        return $this->taskScrollOffset;
    }

    public function getCurrentReasoning(): ?string
    {
        return $this->currentReasoning;
    }

    public function isShowReasoning(): bool
    {
        return $this->showReasoning;
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function getContextInput(): string
    {
        return $this->contextInput;
    }

    public function getCurrentFocus(): string
    {
        return $this->currentFocus;
    }

    // ──────────────────────────────────────────────────────────────
    // Public setters
    // ──────────────────────────────────────────────────────────────

    public function setInput(string $input): void
    {
        $this->input = $input;
    }

    public function setContextInput(string $input): void
    {
        $this->contextInput = $input;
    }

    public function setCurrentFocus(string $focus): void
    {
        $this->currentFocus = $focus;
    }

    public function setShowTaskOverlay(bool $show): void
    {
        $this->showTaskOverlay = $show;
    }

    public function setShowHelp(bool $show): void
    {
        $this->showHelp = $show;
    }

    public function setSelectedTaskIndex(int $index): void
    {
        $this->selectedTaskIndex = $index;
    }

    public function setSelectedContextLine(int $line): void
    {
        $this->selectedContextLine = $line;
    }

    public function setTaskScrollOffset(int $offset): void
    {
        $this->taskScrollOffset = $offset;
    }

    public function setShowReasoning(bool $show): void
    {
        $this->showReasoning = $show;
    }

    public function clearHistory(): void
    {
        $this->history = [];
    }

    public function addContextNote(string $note): void
    {
        $this->context['notes'][] = $note;
    }

    public function removeContextNote(int $index): void
    {
        array_splice($this->context['notes'], $index, 1);
    }

    protected function subscribeToEvents(): void
    {
        $this->eventBus->subscribe(ProcessingEvent::class, fn (ProcessingEvent $event) => $this->onProcessingEvent($event));
        $this->eventBus->subscribe(StateUpdateEvent::class, fn (StateUpdateEvent $event) => $this->onStateUpdate($event));
        $this->eventBus->subscribe(TaskUpdateEvent::class, fn (TaskUpdateEvent $event) => $this->onTaskUpdate($event));
        $this->eventBus->subscribe(ToolStartedEvent::class, fn (ToolStartedEvent $event) => $this->onToolStarted($event));
        $this->eventBus->subscribe(ToolCompletedEvent::class, fn (ToolCompletedEvent $event) => $this->onToolCompleted($event));
        $this->eventBus->subscribe(ProcessProgressEvent::class, fn (ProcessProgressEvent $event) => $this->onProcessProgress($event));
        $this->eventBus->subscribe(ProcessCompleteEvent::class, fn (ProcessCompleteEvent $event) => $this->onProcessComplete($event));
    }

    // ──────────────────────────────────────────────────────────────
    // Event handlers
    // ──────────────────────────────────────────────────────────────

    protected function onProcessingEvent(ProcessingEvent $event): void
    {
        error_log('[UI] ProcessingEvent received: ' . json_encode([
            'operation' => $event->operation,
            'phase' => $event->phase,
            'message' => $event->getMessage(),
        ]));

        $message = $event->getMessage();
        $this->addHistory('status', $message);
        $this->status = $message;
        $this->stateChanged = true;
    }

    protected function onStateUpdate(StateUpdateEvent $event): void
    {
        error_log('[UI] StateUpdateEvent received: ' . json_encode([
            'tasks_count' => count($event->tasks),
            'current_task' => $event->currentTask,
            'status' => $event->status,
        ]));

        $this->tasks = $event->tasks;
        $this->currentTask = $event->currentTask ?? '';
        $this->context = array_merge($this->context, $event->context);
        $this->status = $event->status;
        $this->stateChanged = true;
    }

    protected function onTaskUpdate(TaskUpdateEvent $event): void
    {
        foreach ($this->tasks as &$task) {
            if (($task['id'] ?? '') === $event->task->id) {
                $task['status'] = $event->newStatus;
                break;
            }
        }
        $this->stateChanged = true;
    }

    protected function onToolStarted(ToolStartedEvent $event): void
    {
        error_log('[UI] ToolStartedEvent received: ' . json_encode([
            'tool' => $event->tool,
            'params' => $event->params,
        ]));

        $this->addHistory('tool', $event->tool, implode(' ', $event->params), 'Running...');

        $this->pendingToolCalls[$event->tool] = [
            'tool' => $event->tool,
            'params' => $event->params,
            'startTime' => time(),
        ];

        $this->stateChanged = true;
    }

    protected function onToolCompleted(ToolCompletedEvent $event): void
    {
        error_log('[UI] ToolCompletedEvent received: ' . json_encode([
            'tool' => $event->tool,
            'success' => $event->result->isSuccess(),
        ]));

        $result = $event->result->isSuccess() ? 'Success' : 'Failed';
        $this->addHistory('tool', $event->tool, implode(' ', $event->params), $result);

        $toolCallEntry = new ToolCallEntry(
            $event->tool,
            $event->params,
            $event->result,
            $this->pendingToolCalls[$event->tool]['startTime'] ?? time()
        );

        $this->addActivity($toolCallEntry);

        unset($this->pendingToolCalls[$event->tool]);

        $this->stateChanged = true;
    }

    protected function onProcessProgress(ProcessProgressEvent $event): void
    {
        error_log('[UI] ProcessProgressEvent received: ' . json_encode([
            'processId' => $event->processId,
            'type' => $event->type,
            'message' => $event->data['message'] ?? null,
            'operation' => $event->data['operation'] ?? null,
        ]));

        $this->currentProgress = $event->data;
        $this->status = $event->data['message'] ?? 'Processing...';

        if (isset($event->data['operation']) && $event->data['operation'] === 'reasoning_received') {
            $this->currentReasoning = $event->data['details']['reasoning_content'] ?? null;
            $this->showReasoning = ! empty($this->currentReasoning);

            if ($this->showReasoning) {
                $this->status = 'Thinking... (Press R to toggle reasoning)';
            }
        }

        if (isset($event->data['operation']) && ! in_array($event->data['operation'], ['reasoning_received', 'calling_openai'])) {
            if ($event->data['operation'] === 'quick_response' || $event->data['operation'] === 'deep_processing') {
                $this->currentReasoning = null;
                $this->showReasoning = false;
            }
        }

        $this->stateChanged = true;
    }

    protected function onProcessComplete(ProcessCompleteEvent $event): void
    {
        error_log('[UI] ProcessCompleteEvent received: ' . json_encode([
            'processId' => $event->processId,
            'message_length' => mb_strlen($event->response->getMessage()),
        ]));

        $this->displayResponse($event->response);
        $this->currentProgress = [];
        $this->status = 'Ready';
        $this->stateChanged = true;
    }

    protected function addActivity($activityEntry): void
    {
        $this->activityFeed[] = $activityEntry;

        if (count($this->activityFeed) > 50) {
            array_shift($this->activityFeed);
        }

        if (method_exists($activityEntry, 'getMessage')) {
            $this->history[] = [
                'time' => time(),
                'type' => 'activity',
                'content' => $activityEntry->getMessage(),
            ];
        }
    }
}
