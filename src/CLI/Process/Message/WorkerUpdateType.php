<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Process\Message;

enum WorkerUpdateType: string
{
    case Progress = 'progress';
    case StateSync = 'state_sync';
    case TaskStatus = 'task_status';
    case Status = 'status';
    case Error = 'error';
    case Heartbeat = 'heartbeat';
    case ToolStarted = 'tool_started';
    case ToolCompleted = 'tool_completed';
    case Unknown = 'unknown';

    public static function fromValue(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Unknown;
    }
}
