#!/usr/bin/env php
<?php

/**
 * Fiber-based Async POC for Swarm Architecture
 *
 * Demonstrates how we could refactor the current process-based async
 * architecture to use PHP Fibers for better concurrency and responsiveness.
 *
 * Run with: php examples/fiber_swarm_poc.php
 * Press 'q' to quit, 's' to simulate a task, 'c' to clear output
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HelgeSverre\Swarm\Events\EventBus;
use HelgeSverre\Swarm\Events\ProcessProgressEvent;
use HelgeSverre\Swarm\Events\StateUpdateEvent;

class FiberSwarm
{
    private bool $running = true;

    private array $activeProcesses = [];

    private array $output = [];

    private array $tasks = [];

    private string $status = 'Ready';

    private string $input = '';

    private int $frame = 0;

    private EventBus $eventBus;

    private array $terminalSize;

    private int $scrollOffset = 0;

    // Fibers
    private ?Fiber $renderFiber = null;

    private ?Fiber $inputFiber = null;

    private ?Fiber $eventFiber = null;

    private array $processFibers = [];

    public function __construct()
    {
        $this->eventBus = new EventBus;
        $this->setupTerminal();
        $this->getTerminalSize();

        // Subscribe to events
        $this->eventBus->on(ProcessProgressEvent::class, function ($event) {
            $this->handleProgressEvent($event);
        });

        $this->eventBus->on(StateUpdateEvent::class, function ($event) {
            $this->handleStateUpdate($event);
        });

        register_shutdown_function([$this, 'cleanup']);

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        }
    }

    public function run(): void
    {
        $this->addOutput('[System] Fiber-based Swarm POC started', 'system');
        $this->addOutput("[System] Press 's' to simulate a task, 'q' to quit", 'info');

        // Create main fibers
        $this->createFibers();

        // Start all fibers
        $this->renderFiber->start();
        $this->inputFiber->start();
        $this->eventFiber->start();

        // Main fiber coordination loop
        while ($this->running) {
            // Resume render fiber for smooth UI updates
            if ($this->renderFiber->isSuspended()) {
                $this->renderFiber->resume();
            }

            // Resume input fiber for responsive input
            if ($this->inputFiber->isSuspended()) {
                $this->inputFiber->resume();
            }

            // Resume event processing fiber
            if ($this->eventFiber->isSuspended()) {
                $this->eventFiber->resume();
            }

            // Resume all process monitoring fibers
            foreach ($this->processFibers as $id => $fiber) {
                if (! isset($this->activeProcesses[$id])) {
                    // Process completed, remove fiber
                    unset($this->processFibers[$id]);

                    continue;
                }

                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }
            }

            // Handle signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Very short sleep to prevent CPU spinning
            // Much shorter than the 50ms in current implementation
            usleep(1000); // 1ms vs 50ms in current Swarm
        }
    }

    private function createFibers(): void
    {
        // Render fiber - handles UI updates at ~60 FPS
        $this->renderFiber = new Fiber(function () {
            $lastRender = microtime(true);
            $targetFps = 60;
            $frameTime = 1.0 / $targetFps;

            while ($this->running) {
                $now = microtime(true);
                if ($now - $lastRender >= $frameTime) {
                    $this->render();
                    $lastRender = $now;
                    $this->frame++;
                }
                Fiber::suspend();
            }
        });

        // Input fiber - handles keyboard input without blocking
        $this->inputFiber = new Fiber(function () {
            $stdin = fopen('php://stdin', 'r');
            stream_set_blocking($stdin, false);

            while ($this->running) {
                $char = fgetc($stdin);
                if ($char !== false) {
                    $this->handleInput($char);
                }
                Fiber::suspend();
            }

            fclose($stdin);
        });

        // Event processing fiber - handles events asynchronously
        $this->eventFiber = new Fiber(function () {
            while ($this->running) {
                // In a real implementation, we'd process async events here
                // For now, just suspend to keep the fiber alive
                Fiber::suspend();
            }
        });
    }

    private function createProcessFiber(string $processId, array $processData): Fiber
    {
        return new Fiber(function () use ($processId, $processData) {
            $pipes = $processData['pipes'];
            $startTime = microtime(true);

            while ($this->running && isset($this->activeProcesses[$processId])) {
                // Read updates from process stdout (non-blocking)
                if (is_resource($pipes[1])) {
                    while (($line = fgets($pipes[1])) !== false) {
                        $line = trim($line);
                        if (empty($line)) {
                            continue;
                        }

                        // Parse JSON update from worker
                        $data = json_decode($line, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $this->handleProcessUpdate($processId, $data);
                        }
                    }
                }

                // Check for errors on stderr
                if (is_resource($pipes[2])) {
                    while (($error = fgets($pipes[2])) !== false) {
                        $error = trim($error);
                        if (! empty($error)) {
                            $this->addOutput("[Error] Process {$processId}: {$error}", 'error');
                        }
                    }
                }

                // Check if process is still running
                $status = proc_get_status($processData['process']);
                if (! $status['running']) {
                    $runtime = round(microtime(true) - $startTime, 2);
                    $this->addOutput("[Process] {$processId} completed (runtime: {$runtime}s)", 'success');

                    // Cleanup
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($processData['process']);

                    // Remove from active processes
                    unset($this->activeProcesses[$processId]);

                    // Update status
                    $this->updateStatus();
                    break;
                }

                Fiber::suspend();
            }
        });
    }

    private function simulateWorkerProcess(): void
    {
        $processId = 'proc_' . uniqid();
        $this->addOutput("[Launch] Starting worker process {$processId}", 'info');

        // Simulate launching a worker process
        // In real implementation, this would launch the actual worker script
        $command = [
            PHP_BINARY,
            __DIR__ . '/fiber_worker_simulator.php',
            $processId,
        ];

        // Check if simulator exists, if not create a simple one
        $simulatorPath = __DIR__ . '/fiber_worker_simulator.php';
        if (! file_exists($simulatorPath)) {
            $this->createWorkerSimulator($simulatorPath);
        }

        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (! is_resource($process)) {
            $this->addOutput("[Error] Failed to launch process {$processId}", 'error');

            return;
        }

        // Close stdin
        fclose($pipes[0]);

        // Make output pipes non-blocking
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        // Store process data
        $this->activeProcesses[$processId] = [
            'process' => $process,
            'pipes' => $pipes,
            'startTime' => microtime(true),
            'status' => 'running',
        ];

        // Create dedicated fiber for this process
        $fiber = $this->createProcessFiber($processId, $this->activeProcesses[$processId]);
        $this->processFibers[$processId] = $fiber;
        $fiber->start();

        // Add a mock task
        $this->tasks[] = [
            'id' => uniqid(),
            'description' => 'Processing request in ' . $processId,
            'status' => 'running',
            'processId' => $processId,
        ];

        $this->updateStatus();
    }

    private function createWorkerSimulator(string $path): void
    {
        $code = <<<'PHP'
#!/usr/bin/env php
<?php
// Worker process simulator for Fiber POC
$processId = $argv[1] ?? 'unknown';

// Simulate sending progress updates
$steps = [
    ['type' => 'status', 'status' => 'initializing', 'message' => 'Starting worker...'],
    ['type' => 'progress', 'operation' => 'classifying', 'message' => 'Analyzing request...'],
    ['type' => 'state_sync', 'data' => ['tasks' => [['description' => 'Sample task', 'status' => 'running']]]],
    ['type' => 'progress', 'operation' => 'executing', 'message' => 'Executing task...'],
    ['type' => 'tool_started', 'tool' => 'ReadFile', 'message' => 'Reading file...'],
    ['type' => 'tool_completed', 'tool' => 'ReadFile', 'message' => 'File read complete'],
    ['type' => 'status', 'status' => 'completed', 'message' => 'Task completed successfully']
];

foreach ($steps as $i => $update) {
    $update['timestamp'] = microtime(true);
    $update['processId'] = $processId;
    $update['step'] = $i + 1;
    $update['total'] = count($steps);
    
    echo json_encode($update) . "\n";
    flush();
    
    // Simulate work
    usleep(500000); // 0.5 seconds per step
}
PHP;

        file_put_contents($path, $code);
        chmod($path, 0755);
    }

    private function handleProcessUpdate(string $processId, array $data): void
    {
        $type = $data['type'] ?? 'unknown';
        $message = $data['message'] ?? '';

        // Emit events for different update types
        switch ($type) {
            case 'progress':
                $this->eventBus->emit(new ProcessProgressEvent($processId, $type, $data));
                $this->addOutput("[Progress] {$processId}: {$message}", 'progress');
                break;
            case 'state_sync':
                if (isset($data['data']['tasks'])) {
                    $this->eventBus->emit(new StateUpdateEvent(
                        tasks: $data['data']['tasks'],
                        currentTask: $data['data']['current_task'] ?? null,
                        status: $data['data']['operation'] ?? 'processing'
                    ));
                }
                break;
            case 'tool_started':
            case 'tool_completed':
                $tool = $data['tool'] ?? 'unknown';
                $this->addOutput("[Tool] {$tool}: {$message}", 'tool');
                break;
            case 'status':
                $status = $data['status'] ?? '';
                if ($status === 'completed') {
                    // Mark associated tasks as completed
                    foreach ($this->tasks as &$task) {
                        if (($task['processId'] ?? '') === $processId) {
                            $task['status'] = 'completed';
                        }
                    }
                }
                $this->addOutput("[Status] {$processId}: {$message}", 'status');
                break;
            default:
                $this->addOutput("[Update] {$processId}: " . json_encode($data), 'debug');
        }
    }

    private function handleProgressEvent(ProcessProgressEvent $event): void
    {
        // Update UI state based on progress events
        $this->status = $event->data['message'] ?? 'Processing...';
    }

    private function handleStateUpdate(StateUpdateEvent $event): void
    {
        // Update task list from state sync
        if (! empty($event->tasks)) {
            foreach ($event->tasks as $newTask) {
                $found = false;
                foreach ($this->tasks as &$task) {
                    if ($task['id'] === ($newTask['id'] ?? '')) {
                        $task = array_merge($task, $newTask);
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $this->tasks[] = $newTask;
                }
            }
        }
    }

    private function handleInput(string $char): void
    {
        switch ($char) {
            case 'q':
            case 'Q':
                $this->running = false;
                break;
            case 's':
            case 'S':
                $this->simulateWorkerProcess();
                break;
            case 'c':
            case 'C':
                $this->output = [];
                $this->addOutput('[System] Output cleared', 'system');
                break;
            case "\033": // Escape sequence
                // Read the rest of the sequence
                $seq = $char;
                $seq .= fgetc(STDIN);
                $seq .= fgetc(STDIN);

                if ($seq === "\033[A") { // Up arrow
                    $this->scrollOffset = max(0, $this->scrollOffset - 1);
                } elseif ($seq === "\033[B") { // Down arrow
                    $maxScroll = max(0, count($this->output) - 10);
                    $this->scrollOffset = min($maxScroll, $this->scrollOffset + 1);
                }
                break;
            case "\n":
                if (! empty($this->input)) {
                    $this->addOutput('[Input] ' . $this->input, 'command');
                    $this->input = '';
                }
                break;
            case "\177": // Backspace
            case "\010":
                if (mb_strlen($this->input) > 0) {
                    $this->input = mb_substr($this->input, 0, -1);
                }
                break;
            default:
                if (ord($char) >= 32 && ord($char) <= 126) {
                    $this->input .= $char;
                }
        }
    }

    private function render(): void
    {
        $buffer = "\033[H"; // Move to home

        // Header
        $buffer .= "\033[1;36m╔══════════════════════════════════════════════════════════╗\033[0m\n";
        $buffer .= "\033[1;36m║         Fiber-based Swarm Architecture POC              ║\033[0m\n";
        $buffer .= "\033[1;36m╠══════════════════════════════════════════════════════════╣\033[0m\n";

        // Status line
        $activeCount = count($this->activeProcesses);
        $taskCount = count($this->tasks);
        $runningTasks = count(array_filter($this->tasks, fn ($t) => ($t['status'] ?? '') === 'running'));

        $statusLine = sprintf('Processes: %d | Tasks: %d (%d running) | Status: %s',
            $activeCount, $taskCount, $runningTasks, $this->status);
        $buffer .= "\033[1;36m║\033[0m " . mb_str_pad($statusLine, 57) . " \033[1;36m║\033[0m\n";

        // Frame counter (shows UI is updating smoothly)
        $fps = "Frame: {$this->frame} | FPS: ~60";
        $buffer .= "\033[1;36m║\033[0m " . mb_str_pad($fps, 57) . " \033[1;36m║\033[0m\n";

        $buffer .= "\033[1;36m╠══════════════════════════════════════════════════════════╣\033[0m\n";

        // Output area
        $outputHeight = min(15, $this->terminalSize[0] - 12);
        $displayOutput = array_slice($this->output, -$outputHeight + $this->scrollOffset);

        for ($i = 0; $i < $outputHeight; $i++) {
            if (isset($displayOutput[$i])) {
                $line = $this->formatOutput($displayOutput[$i]);
                $buffer .= "\033[1;36m║\033[0m " . mb_str_pad(mb_substr($line, 0, 57), 57) . " \033[1;36m║\033[0m\n";
            } else {
                $buffer .= "\033[1;36m║\033[0m " . str_repeat(' ', 57) . " \033[1;36m║\033[0m\n";
            }
        }

        $buffer .= "\033[1;36m╠══════════════════════════════════════════════════════════╣\033[0m\n";

        // Controls
        $buffer .= "\033[1;36m║\033[0m Controls: [s]imulate task [c]lear [↑↓]scroll [q]uit     \033[1;36m║\033[0m\n";

        // Input line
        $prompt = '> ' . $this->input;
        $buffer .= "\033[1;36m║\033[0m " . mb_str_pad($prompt, 57) . " \033[1;36m║\033[0m\n";

        $buffer .= "\033[1;36m╚══════════════════════════════════════════════════════════╝\033[0m\n";

        // Clear remaining lines
        for ($i = 0; $i < 5; $i++) {
            $buffer .= "\033[K\n";
        }

        echo $buffer;
    }

    private function formatOutput(array $entry): string
    {
        $type = $entry['type'] ?? 'info';
        $message = $entry['message'] ?? '';
        $time = date('H:i:s', $entry['time']);

        $color = match ($type) {
            'error' => "\033[31m",      // Red
            'success' => "\033[32m",    // Green
            'progress' => "\033[33m",   // Yellow
            'tool' => "\033[36m",       // Cyan
            'command' => "\033[34m",    // Blue
            'system' => "\033[35m",     // Magenta
            'status' => "\033[32m",     // Green
            default => "\033[37m"       // White
        };

        return "{$color}[{$time}] {$message}\033[0m";
    }

    private function addOutput(string $message, string $type = 'info'): void
    {
        $this->output[] = [
            'message' => $message,
            'type' => $type,
            'time' => time(),
        ];

        // Keep output buffer reasonable
        if (count($this->output) > 1000) {
            array_shift($this->output);
        }
    }

    private function updateStatus(): void
    {
        $activeCount = count($this->activeProcesses);
        if ($activeCount > 0) {
            $this->status = "Processing ({$activeCount} active)";
        } else {
            $this->status = 'Ready';
        }
    }

    private function setupTerminal(): void
    {
        // Enter alternate screen buffer
        echo "\033[?1049h";

        // Hide cursor
        echo "\033[?25l";

        // Clear screen
        echo "\033[2J\033[H";

        // Setup raw mode
        system('stty -echo -icanon min 1 time 0');
    }

    private function cleanup(): void
    {
        // Kill any remaining processes
        foreach ($this->activeProcesses as $id => $data) {
            if (is_resource($data['process'])) {
                proc_terminate($data['process']);
                proc_close($data['process']);
            }
        }

        // Restore terminal
        system('stty echo icanon');

        // Show cursor
        echo "\033[?25h";

        // Exit alternate screen buffer
        echo "\033[?1049l";

        echo "Fiber Swarm POC terminated.\n";
    }

    private function handleSignal(int $signal): void
    {
        $this->running = false;
    }

    private function getTerminalSize(): void
    {
        $this->terminalSize = [
            (int) exec('tput lines') ?: 24,
            (int) exec('tput cols') ?: 80,
        ];
    }
}

// Run the POC
if (php_sapi_name() === 'cli') {
    $swarm = new FiberSwarm;
    $swarm->run();
}
