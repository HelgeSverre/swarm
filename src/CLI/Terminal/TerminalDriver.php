<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

class TerminalDriver
{
    protected bool $initialized = false;

    protected string $originalTermState = '';

    protected bool $isMacOS;

    protected string $modKey;

    protected string $modSymbol;

    protected int $terminalHeight;

    protected int $terminalWidth;

    public function __construct()
    {
        $this->detectOS();
        $this->updateTerminalSize();
    }

    public function initializeTerminal(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->originalTermState = (string) exec('stty -g');

        echo "\033[?1049h";
        echo "\033[2J\033[3J\033[H";

        exec('stty -echo -icanon min 1 time 0');
        stream_set_blocking(STDIN, false);

        echo "\033[?25l";

        $this->initialized = true;
    }

    public function cleanup(): void
    {
        if (! $this->initialized) {
            return;
        }

        echo "\033[?25h";
        echo "\033[0m";
        echo "\033[?1049l";

        if ($this->originalTermState !== '') {
            @exec('stty ' . escapeshellarg($this->originalTermState));
        } else {
            @exec('stty sane');
        }

        $this->initialized = false;
    }

    protected function detectOS(): void
    {
        $this->isMacOS = str_contains(PHP_OS, 'Darwin');
        $this->modKey = $this->isMacOS ? 'Option' : 'Alt';
        $this->modSymbol = $this->isMacOS ? '⌥' : 'Alt+';
    }

    public function updateTerminalSize(): void
    {
        $this->terminalHeight = (int) exec('tput lines') ?: 24;
        $this->terminalWidth = (int) exec('tput cols') ?: 80;
    }

    public function clearScreen(): void
    {
        echo "\033[2J\033[3J\033[H";
    }

    public function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    public function write(string $output): void
    {
        echo $output;
    }

    public function readKey(): ?string
    {
        $read = [STDIN];
        $write = null;
        $except = null;
        $result = stream_select($read, $write, $except, 0, 0);

        if ($result === 0 || $result === false) {
            return null;
        }

        $key = fgetc(STDIN);

        if ($key === false || $key === '') {
            return null;
        }

        if ($key === "\033") {
            $seq = $key;

            $read2 = [STDIN];
            $result2 = stream_select($read2, $write, $except, 0, 10000);

            if ($result2 > 0) {
                $next = fgetc(STDIN);
                if ($next !== false && $next !== '') {
                    $seq .= $next;

                    if ($next !== '[' && $next !== "\033") {
                        return 'ALT+' . mb_strtoupper($next);
                    }
                } else {
                    return 'ESC';
                }
            } else {
                return 'ESC';
            }

            if (isset($seq[1]) && $seq[1] === '[') {
                $read3 = [STDIN];
                $result3 = stream_select($read3, $write, $except, 0, 10000);

                if ($result3 > 0) {
                    $third = fgetc(STDIN);
                    if ($third !== false && $third !== '') {
                        $seq .= $third;
                    }
                }
            } elseif (isset($seq[1]) && $seq[1] === "\033") {
                $seq .= fgetc(STDIN);
                if (isset($seq[2]) && $seq[2] === '[') {
                    while (true) {
                        $char = fgetc(STDIN);
                        if ($char === false || ctype_alpha($char)) {
                            break;
                        }
                    }
                }

                return null;
            }

            if (preg_match('/^\033\[1;9[A-D]$/', $seq)) {
                return null;
            }

            if ($seq[2] === ';' || ctype_digit($seq[2])) {
                while (true) {
                    $char = fgetc(STDIN);
                    $seq .= $char;
                    if ($char === false || ctype_alpha($char)) {
                        break;
                    }
                }

                return null;
            }

            if ($seq === "\033[A") {
                return 'UP';
            }
            if ($seq === "\033[B") {
                return 'DOWN';
            }
            if ($seq === "\033[C") {
                return 'RIGHT';
            }
            if ($seq === "\033[D") {
                return 'LEFT';
            }

            if ($seq === "\033\000\000" || mb_strlen($seq) === 1) {
                return 'ESC';
            }

            return null;
        }

        if ($key === "\t") {
            return 'TAB';
        }

        return $key;
    }

    public function getWidth(): int
    {
        return $this->terminalWidth;
    }

    public function getHeight(): int
    {
        return $this->terminalHeight;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function isMacOS(): bool
    {
        return $this->isMacOS;
    }

    public function getModKey(): string
    {
        return $this->modKey;
    }

    public function getModSymbol(): string
    {
        return $this->modSymbol;
    }
}
