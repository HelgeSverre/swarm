<?php

namespace HelgeSverre\Swarm\Core;

class ToolResponse
{
    protected bool $success = false;

    protected ?array $data = null;

    protected mixed $error = null;

    public static function success(array $data): self
    {
        $instance = new self;
        $instance->success = true;
        $instance->data = $data;

        return $instance;
    }

    public static function error(string $error): self
    {
        $instance = new self;
        $instance->success = false;
        $instance->error = $error;

        return $instance;
    }

    public function isError(): bool
    {
        return ! $this->success;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getData(): array
    {
        return $this->data ?? [];
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}
