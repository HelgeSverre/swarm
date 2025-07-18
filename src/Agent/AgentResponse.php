<?php

namespace HelgeSverre\Swarm\Agent;

class AgentResponse
{
    protected $message;

    protected $success;

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
}
