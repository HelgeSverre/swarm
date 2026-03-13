<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI;

use HelgeSverre\Swarm\CLI\Command\CommandAction;

final class CommandResult
{
    public function __construct(
        public readonly bool $handled,
        public readonly ?CommandAction $action,
        public readonly array $data
    ) {}

    public static function ignored(): self
    {
        return new self(false, null, []);
    }

    public static function success(CommandAction $action, array $data = []): self
    {
        return new self(true, $action, $data);
    }

    public static function error(string $message): self
    {
        return new self(true, CommandAction::Error, ['error' => $message]);
    }

    public function isExit(): bool
    {
        return $this->action === CommandAction::Exit;
    }

    public function hasError(): bool
    {
        return $this->action === CommandAction::Error;
    }

    public function getError(): ?string
    {
        return $this->data['error'] ?? null;
    }

    public function getMessage(): ?string
    {
        return $this->data['message'] ?? null;
    }
}
