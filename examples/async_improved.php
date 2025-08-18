<?php

/** @noinspection PhpUnhandledExceptionInspection */

/**
 * Improved Async TUI with Fibers - Smooth rendering demonstration
 *
 * Key improvements:
 * - Uses alternate screen buffer to prevent terminal pollution
 * - Implements proper cursor positioning instead of screen clearing
 * - Buffers output for single write operations
 * - Only renders when data changes
 * - Proper terminal size detection
 * - Simplified Fiber coordination
 */
class AsyncTUI
{
    private array $subprocesses = [];

    private bool $running = true;

    private array $output = [];

    private int $currentLine = 0;

    private int $lastOutputCount = 0;

    private array $terminalSize = [24, 80];

    private string $lastFrame = '';

    private bool $needsRender = true;

    public function __construct()
    {
        // Get terminal size
        $this->updateTerminalSize();

        // Enable raw terminal mode
        system('stty -echo -icanon');

        // Switch to alternate screen buffer and hide cursor
        echo "\033[?1049h\033[?25l";

        // Clear the alternate screen
        echo "\033[2J\033[H";
    }

    public function __destruct()
    {
        // Show cursor and switch back to main screen buffer
        echo "\033[?25h\033[?1049l";

        // Restore terminal
        system('stty echo icanon');
    }

    public function addSubprocess(string $command, string $label): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if ($process) {
            // Make stdout and stderr non-blocking
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $this->subprocesses[] = [
                'process' => $process,
                'pipes' => $pipes,
                'label' => $label,
                'output' => [],
                'status' => 'running',
                'start_time' => microtime(true),
            ];
        }
    }

    public function run(): void
    {
        // Create fibers for different responsibilities
        $fibers = [
            'ui' => new Fiber([$this, 'uiLoop']),
            'process' => new Fiber([$this, 'processLoop']),
            'input' => new Fiber([$this, 'inputLoop']),
        ];

        // Start all fibers
        foreach ($fibers as $fiber) {
            $fiber->start();
        }

        // Main event loop - simplified coordination
        while ($this->running) {
            foreach ($fibers as $name => $fiber) {
                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }
            }

            // 100 FPS potential for instant responsiveness
            usleep(10000);
        }
    }

    public function uiLoop(): void
    {
        while ($this->running) {
            // Only render when there's new data or user interaction
            if ($this->hasChanges()) {
                $this->render();
            }
            Fiber::suspend();
        }
    }

    public function processLoop(): void
    {
        while ($this->running) {
            $this->checkSubprocesses();
            Fiber::suspend();
        }
    }

    public function inputLoop(): void
    {
        // Make stdin non-blocking
        $stdin = fopen('php://stdin', 'r');
        stream_set_blocking($stdin, false);

        $escapeBuffer = '';

        while ($this->running) {
            $char = fgetc($stdin);
            if ($char !== false) {
                // Handle escape sequences for arrow keys
                if ($char === "\033") {
                    $escapeBuffer = $char;
                } elseif ($escapeBuffer) {
                    $escapeBuffer .= $char;
                    if (mb_strlen($escapeBuffer) === 3) {
                        $this->handleEscapeSequence($escapeBuffer);
                        $escapeBuffer = '';
                    }
                } else {
                    $this->handleInput($char);
                }
            }
            Fiber::suspend();
        }

        fclose($stdin);
    }

    private function updateTerminalSize(): void
    {
        $size = shell_exec('stty size 2>/dev/null');
        if ($size) {
            $parts = explode(' ', trim($size));
            if (count($parts) === 2) {
                $this->terminalSize = [(int) $parts[0], (int) $parts[1]];
            }
        }
    }

    private function hasChanges(): bool
    {
        // Check if we need to render due to user input or new output
        if ($this->needsRender) {
            $this->needsRender = false;

            return true;
        }

        $hasNewOutput = count($this->output) !== $this->lastOutputCount;
        $this->lastOutputCount = count($this->output);

        return $hasNewOutput;
    }

    private function checkSubprocesses(): void
    {
        foreach ($this->subprocesses as $key => &$subprocess) {
            if ($subprocess['status'] !== 'running') {
                continue;
            }

            $pipes = $subprocess['pipes'];

            // Check for output on stdout
            $stdout = stream_get_contents($pipes[1]);
            if ($stdout !== false && $stdout !== '') {
                $lines = explode("\n", rtrim($stdout));
                foreach ($lines as $line) {
                    if ($line !== '') {
                        $timestamp = date('H:i:s');
                        $this->output[] = "\033[32m[{$timestamp}]\033[0m \033[36m[{$subprocess['label']}]\033[0m {$line}";
                    }
                }
            }

            // Check for output on stderr
            $stderr = stream_get_contents($pipes[2]);
            if ($stderr !== false && $stderr !== '') {
                $lines = explode("\n", rtrim($stderr));
                foreach ($lines as $line) {
                    if ($line !== '') {
                        $timestamp = date('H:i:s');
                        $this->output[] = "\033[32m[{$timestamp}]\033[0m \033[31m[{$subprocess['label']}]\033[0m ERROR: {$line}";
                    }
                }
            }

            // Check if process is still running
            $status = proc_get_status($subprocess['process']);
            if (! $status['running']) {
                $subprocess['status'] = 'finished';
                $runtime = round(microtime(true) - $subprocess['start_time'], 2);
                $timestamp = date('H:i:s');
                $this->output[] = "\033[32m[{$timestamp}]\033[0m \033[33m[{$subprocess['label']}]\033[0m Process finished (exit: {$status['exitcode']}, runtime: {$runtime}s)";

                // Close pipes
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($subprocess['process']);
            }
        }
    }

    private function render(): void
    {
        // Build the entire frame in a buffer
        $buffer = '';

        // Move cursor to home position (no clear)
        $buffer .= "\033[H";

        // Header with line clearing
        $buffer .= "\033[1;37m╔══════════════════════════════════════════════════╗\033[K\n";
        $buffer .= "║        \033[1;36mAsync TUI Process Monitor (Fibers)\033[1;37m       ║\033[K\n";
        $buffer .= "╠══════════════════════════════════════════════════╣\033[K\n";
        $buffer .= "║ \033[0;37mControls: [q]uit [c]lear [↑↓/jk]scroll\033[1;37m         ║\033[K\n";

        // Process status
        $activeCount = count(array_filter($this->subprocesses, fn ($p) => $p['status'] === 'running'));
        $totalCount = count($this->subprocesses);
        $statusText = sprintf('Active: %d/%d', $activeCount, $totalCount);
        $padding = 35 - mb_strlen($statusText);
        $buffer .= "║ \033[0;32m{$statusText}" . str_repeat(' ', $padding) . "\033[1;37m║\033[K\n";
        $buffer .= "╚══════════════════════════════════════════════════╝\033[0m\033[K\n";

        // Calculate output window dimensions
        $headerLines = 6;
        $footerLines = 2;
        $outputHeight = $this->terminalSize[0] - $headerLines - $footerLines;
        $outputHeight = max(5, $outputHeight); // Minimum height

        // Output window with scrolling
        $totalLines = count($this->output);
        $maxScroll = max(0, $totalLines - $outputHeight);
        $this->currentLine = min($this->currentLine, $maxScroll);

        $startLine = $this->currentLine;
        $endLine = min($totalLines, $startLine + $outputHeight);

        // Render output lines
        for ($i = $startLine; $i < $endLine; $i++) {
            $buffer .= $this->output[$i] . "\033[K\n";
        }

        // Fill remaining lines with blanks
        for ($i = $endLine - $startLine; $i < $outputHeight; $i++) {
            $buffer .= "\033[K\n";
        }

        // Footer with scroll indicator
        if ($totalLines > $outputHeight) {
            $scrollPercent = $maxScroll > 0 ? round(($this->currentLine / $maxScroll) * 100) : 0;
            $scrollBar = $this->generateScrollBar($scrollPercent, 30);
            $buffer .= "\033[90m─── Scroll: {$scrollBar} {$scrollPercent}% ───\033[0m\033[K\n";
        } else {
            $buffer .= "\033[90m─── All output visible ───\033[0m\033[K\n";
        }

        // Write the entire frame at once
        echo $buffer;

        $this->lastFrame = $buffer;
    }

    private function generateScrollBar(int $percent, int $width): string
    {
        $filled = (int) ($width * $percent / 100);
        $empty = $width - $filled;

        return '[' . str_repeat('█', $filled) . str_repeat('░', $empty) . ']';
    }

    private function handleInput(string $char): void
    {
        switch ($char) {
            case 'q':
            case 'Q':
                $this->running = false;
                break;
            case 'c':
            case 'C':
                $this->output = [];
                $this->currentLine = 0;
                $this->needsRender = true;
                break;
            case 'j': // Vim-style down
                $this->scrollDown();
                $this->needsRender = true;
                break;
            case 'k': // Vim-style up
                $this->scrollUp();
                $this->needsRender = true;
                break;
        }
    }

    private function handleEscapeSequence(string $sequence): void
    {
        // Arrow key sequences
        if ($sequence === "\033[A") { // Up arrow
            $this->scrollUp();
            $this->needsRender = true;
        } elseif ($sequence === "\033[B") { // Down arrow
            $this->scrollDown();
            $this->needsRender = true;
        }
    }

    private function scrollDown(): void
    {
        $outputHeight = max(5, $this->terminalSize[0] - 8);
        $maxScroll = max(0, count($this->output) - $outputHeight);
        $this->currentLine = min($maxScroll, $this->currentLine + 1);
    }

    private function scrollUp(): void
    {
        $this->currentLine = max(0, $this->currentLine - 1);
    }
}

// Demo usage
echo "Starting Async TUI Demo with Fibers...\n";
echo "This demonstrates smooth rendering with PHP Fibers\n";
sleep(2);

// Create TUI instance
$tui = new AsyncTUI;

// Add some demo processes with different runtimes
$tui->addSubprocess('ping -c 10 google.com', 'ping');
$tui->addSubprocess('for i in {1..20}; do echo "Counter: $i"; sleep 0.5; done', 'counter');
$tui->addSubprocess('find /usr -name "*.conf" 2>/dev/null | head -30', 'find');
$tui->addSubprocess('ps aux | head -20', 'processes');

// You can add more processes to see scrolling in action
// $tui->addSubprocess('ls -la /usr/bin | head -50', 'ls-bin');
// $tui->addSubprocess('df -h', 'disk-usage');

// Start the TUI event loop
$tui->run();

echo "\nAsync TUI Demo finished!\n";
