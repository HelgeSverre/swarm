<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Process\Message;

final class WorkerUpdate
{
    public function __construct(
        public readonly WorkerUpdateType $type,
        public readonly array $payload,
        public readonly ?string $processId = null,
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            type: WorkerUpdateType::fromValue($payload['type'] ?? null),
            payload: $payload,
            processId: isset($payload['processId']) ? (string) $payload['processId'] : null,
        );
    }

    public function withProcessId(string $processId): self
    {
        $payload = $this->payload;
        $payload['processId'] = $processId;

        return new self($this->type, $payload, $processId);
    }

    public function toArray(): array
    {
        $payload = $this->payload;
        $payload['type'] = $this->type->value;

        if ($this->processId !== null) {
            $payload['processId'] = $this->processId;
        }

        return $payload;
    }

    public function status(): ?string
    {
        return isset($this->payload['status']) ? (string) $this->payload['status'] : null;
    }

    public function response(): ?array
    {
        return isset($this->payload['response']) && is_array($this->payload['response'])
            ? $this->payload['response']
            : null;
    }

    public function isCompletedStatus(): bool
    {
        return $this->type === WorkerUpdateType::Status && $this->status() === 'completed';
    }
}
