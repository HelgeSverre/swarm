<?php

namespace HelgeSverre\Swarm\Router;

class ToolResponse
{
    protected $success;

    protected $data;

    protected $error;

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

    public function getData(): array
    {
        return $this->data ?? [];
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
