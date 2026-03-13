<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\StateUpdateEvent;
use HelgeSverre\Swarm\Traits\Loggable;

/**
 * Owns the in-memory synced state and bridges it to persistence (StateManager)
 * and the event system (StateUpdateEvent).
 */
class StateSyncAdapter
{
    use Loggable;

    protected array $state;

    public function __construct(
        protected StateManager $stateManager,
        protected EventBus $eventBus,
    ) {
        $this->state = $stateManager->reset();
    }

    /**
     * Load persisted state into memory
     */
    public function load(): void
    {
        $this->state = $this->stateManager->load();
    }

    /**
     * Persist current in-memory state
     */
    public function save(): void
    {
        $this->stateManager->save($this->state);
    }

    /**
     * Clear persisted state and reset in-memory state
     */
    public function clear(): bool
    {
        $cleared = $this->stateManager->clear();
        if ($cleared) {
            $this->state = $this->stateManager->reset();
        }

        return $cleared;
    }

    /**
     * Check whether there is meaningful state worth saving
     */
    public function hasContent(): bool
    {
        return ! empty($this->state['conversation_history'])
            || ! empty($this->state['tasks'])
            || ! empty($this->state['tool_log']);
    }

    /**
     * Emit a StateUpdateEvent with current state
     */
    public function emitUpdate(): void
    {
        $this->eventBus->emit(new StateUpdateEvent(
            tasks: $this->state['tasks'] ?? [],
            currentTask: $this->state['current_task'] ?? null,
            conversationHistory: $this->state['conversation_history'] ?? [],
            toolLog: $this->state['tool_log'] ?? [],
            context: [],
            status: $this->state['operation'] ?? 'ready',
        ));
    }

    // ── Accessors ────────────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    public function all(): array
    {
        return $this->state;
    }

    /**
     * Merge an associative array into state (shallow)
     */
    public function merge(array $data): void
    {
        $this->state = array_merge($this->state, $data);
    }

    // ── Conversation helpers ─────────────────────────────────────

    public function addConversationMessage(string $role, string $content): void
    {
        $this->state['conversation_history'][] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time(),
        ];
    }

    public function getConversationHistory(): array
    {
        return $this->state['conversation_history'] ?? [];
    }

    public function getTaskHistory(): array
    {
        return $this->state['task_history'] ?? [];
    }

    public function setTaskHistory(array $history): void
    {
        $this->state['task_history'] = $history;
    }
}
