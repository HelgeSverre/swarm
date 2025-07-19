<?php

namespace HelgeSverre\Swarm\Agent;

class AgentResponse
{
    protected ?string $message = null;

    protected bool $success = false;

    public static function success(string $message): self
    {
        $instance = new self;
        $instance->message = $message;
        $instance->success = true;

        return $instance;
    }

    public function getMessage(): string
    {
        return $this->message ?? '';
    }

    public function isSuccess(): bool
    {
        return $this->success ?? false;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'success' => $this->success,
        ];
    }

    public static function fromArray(array $data): self
    {
        $instance = new self;
        $instance->message = $data['message'] ?? '';
        $instance->success = $data['success'] ?? false;

        return $instance;
    }
}
