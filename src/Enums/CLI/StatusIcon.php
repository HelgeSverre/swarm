<?php

namespace HelgeSverre\Swarm\Enums\CLI;

use HelgeSverre\Swarm\Task\TaskStatus;

/**
 * Icons used for various statuses and UI elements
 */
enum StatusIcon: string
{
    /**
     * Get the icon character/emoji
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => '⏸',
            self::Planned => '📋',
            self::Executing, self::Running => '⏳',
            self::Completed => '✓',
            self::Failed => '✗',
            self::Robot => '🤖',
            self::Tool => '🔧',
            self::Task => '🎯',
            self::Error => '❌',
            self::Success => '✅',
            self::Info => 'ℹ',
            self::Warning => '⚠',
        };
    }

    /**
     * Get the appropriate icon for a task status
     */
    public static function forTaskStatus(TaskStatus|string $status): self
    {
        if (is_string($status)) {
            $status = TaskStatus::tryFrom($status);
        }

        return match ($status) {
            TaskStatus::Pending => self::Pending,
            TaskStatus::Planned => self::Planned,
            TaskStatus::Executing => self::Executing,
            TaskStatus::Completed => self::Completed,
            default => self::Pending,
        };
    }
    // Task status icons
    case Pending = 'pending';
    case Planned = 'planned';
    case Executing = 'executing';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    // UI icons
    case Robot = 'robot';
    case Tool = 'tool';
    case Task = 'task';
    case Error = 'error';
    case Success = 'success';
    case Info = 'info';
    case Warning = 'warning';
}
