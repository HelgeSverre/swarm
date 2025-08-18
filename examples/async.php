<?php

/** @noinspection PhpUnhandledExceptionInspection */
class AsyncTUI
{
    private $subprocesses = [];

    private $running = true;

    private $output = [];

    private $currentLine = 0;

    public function __construct()
    {
        // Enable raw terminal mode
        system('stty -echo -icanon');

        // Hide cursor and clear screen
        echo "\033[?25l\033[2J\033[H";
    }

    public function __destruct()
    {
        // Restore terminal
        system('stty echo icanon');
        echo "\033[?25h\033[2J\033[H";
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
            ];
        }
    }

    public function run(): void
    {
        // Create fibers for different responsibilities
        $uiFiber = new Fiber([$this, 'uiLoop']);
        $processFiber = new Fiber([$this, 'processLoop']);
        $inputFiber = new Fiber([$this, 'inputLoop']);

        // Start all fibers
        $uiFiber->start();
        $processFiber->start();
        $inputFiber->start();

        // Main event loop
        while ($this->running) {
            // Resume UI fiber for rendering
            if ($uiFiber->isSuspended()) {
                $uiFiber->resume();
            }

            // Resume process monitoring
            if ($processFiber->isSuspended()) {
                $processFiber->resume();
            }

            // Resume input handling
            if ($inputFiber->isSuspended()) {
                $inputFiber->resume();
            }

            // Small delay to prevent busy waiting
            usleep(16667); // ~60 FPS
        }
    }

    public function uiLoop(): void
    {
        while ($this->running) {
            $this->render();
            Fiber::suspend(); // Yield control after each render
        }
    }

    public function processLoop(): void
    {
        while ($this->running) {
            $this->checkSubprocesses();
            Fiber::suspend(); // Yield control after checking processes
        }
    }

    public function inputLoop(): void
    {
        // Make stdin non-blocking
        $stdin = fopen('php://stdin', 'r');
        stream_set_blocking($stdin, false);

        while ($this->running) {
            $char = fgetc($stdin);
            if ($char !== false) {
                $this->handleInput($char);
            }
            Fiber::suspend(); // Yield control after input check
        }

        fclose($stdin);
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
                        $subprocess['output'][] = "[{$subprocess['label']}] {$line}";
                        $this->output[] = "[{$subprocess['label']}] {$line}";
                    }
                }
            }

            // Check for output on stderr
            $stderr = stream_get_contents($pipes[2]);
            if ($stderr !== false && $stderr !== '') {
                $lines = explode("\n", rtrim($stderr));
                foreach ($lines as $line) {
                    if ($line !== '') {
                        $subprocess['output'][] = "[{$subprocess['label']}] ERROR: {$line}";
                        $this->output[] = "[{$subprocess['label']}] ERROR: {$line}";
                    }
                }
            }

            // Check if process is still running
            $status = proc_get_status($subprocess['process']);
            if (! $status['running']) {
                $subprocess['status'] = 'finished';
                $this->output[] = "[{$subprocess['label']}] Process finished with code {$status['exitcode']}";

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
        // Clear screen and move cursor to top
        echo "\033[2J\033[H";

        // Header
        echo "=== Async TUI Process Monitor ===\n";
        echo "Press 'q' to quit, 'c' to clear output, ↑/↓ to scroll\n";
        echo 'Active processes: ' . count(array_filter($this->subprocesses,
            fn ($p) => $p['status'] === 'running')) . "\n";
        echo str_repeat('-', 50) . "\n";

        // Output window (with scrolling)
        $terminalHeight = 20; // Adjust based on your terminal
        $outputHeight = $terminalHeight - 6; // Reserve space for header/footer

        $startLine = max(0, $this->currentLine);
        $endLine = min(count($this->output), $startLine + $outputHeight);

        for ($i = $startLine; $i < $endLine; $i++) {
            echo $this->output[$i] . "\n";
        }

        // Fill remaining lines
        for ($i = $endLine - $startLine; $i < $outputHeight; $i++) {
            echo "\n";
        }

        // Footer with scroll indicator
        $totalLines = count($this->output);
        if ($totalLines > $outputHeight) {
            $scrollPercent = $totalLines > 0 ? round(($startLine / max(1, $totalLines - $outputHeight)) * 100) : 0;
            echo "--- Scroll: {$scrollPercent}% ({$startLine} / " . max(0, $totalLines - $outputHeight) . ') ---';
        }
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
                break;
            case "\033": // Escape sequence (arrow keys)
                // This is simplified - full arrow key handling requires reading the full sequence
                break;
            case 'j': // Vim-style down
                $this->scrollDown();
                break;
            case 'k': // Vim-style up
                $this->scrollUp();
                break;
        }
    }

    private function scrollDown(): void
    {
        $maxScroll = max(0, count($this->output) - 15); // Adjust based on display height
        $this->currentLine = min($maxScroll, $this->currentLine + 1);
    }

    private function scrollUp(): void
    {
        $this->currentLine = max(0, $this->currentLine - 1);
    }
}

class LogMonitorTUI extends AsyncTUI
{
    private $logFiles = [];

    private $filters = [];

    public function addLogFile(string $path, string $label): void
    {
        if (file_exists($path)) {
            $handle = fopen($path, 'r');
            fseek($handle, 0, SEEK_END); // Start at end of file

            $this->logFiles[] = [
                'handle' => $handle,
                'path' => $path,
                'label' => $label,
                'lastSize' => filesize($path),
            ];
        }
    }

    public function addFilter(string $pattern, string $color = ''): void
    {
        $this->filters[] = [
            'pattern' => $pattern,
            'color' => $color,
        ];
    }

    public function logMonitorLoop(): void
    {
        while ($this->running) {
            foreach ($this->logFiles as &$logFile) {
                $currentSize = filesize($logFile['path']);

                if ($currentSize > $logFile['lastSize']) {
                    // File has grown, read new content
                    $newContent = fread($logFile['handle'], $currentSize - $logFile['lastSize']);
                    $lines = explode("\n", rtrim($newContent));

                    foreach ($lines as $line) {
                        if ($line !== '') {
                            $formattedLine = $this->formatLogLine($line, $logFile['label']);
                            $this->output[] = $formattedLine;
                        }
                    }

                    $logFile['lastSize'] = $currentSize;
                }
            }

            Fiber::suspend();
        }
    }

    public function run(): void
    {
        // Add log monitoring fiber
        $logFiber = new Fiber([$this, 'logMonitorLoop']);
        $logFiber->start();

        // Start the base event loop with additional fiber
        parent::run();
    }

    private function formatLogLine(string $line, string $label): string
    {
        $formatted = "[{$label}] {$line}";

        // Apply filters for coloring
        foreach ($this->filters as $filter) {
            if (preg_match('/' . $filter['pattern'] . '/i', $line)) {
                $formatted = $filter['color'] . $formatted . "\033[0m";
                break;
            }
        }

        return $formatted;
    }
}

// Create TUI instance
$tui = new AsyncTUI;

// Add some long-running processes
$tui->addSubprocess('ping -c 10 google.com', 'ping');
$tui->addSubprocess('find /usr -name "*.so" 2>/dev/null | head -20', 'find');

// Start the TUI
$tui->run();
