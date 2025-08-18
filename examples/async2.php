<?php

/**
 * Simple Async TUI Example using Fibers and Alternative Buffer
 * Run with: php async_tui.php
 * Press 'q' to quit, 'r' to reset counters, space to add task
 */
class SimpleTUI
{
    private bool $running = true;

    private int $counter = 0;

    private int $taskCounter = 0;

    private array $tasks = [];

    private array $logs = [];

    private int $frame = 0;

    public function __construct()
    {
        // Enter alternative buffer and setup terminal
        $this->enterAlternativeBuffer();
        $this->setupTerminal();

        // Register cleanup on exit
        register_shutdown_function([$this, 'cleanup']);

        // Handle signals for clean exit
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        }
    }

    public function run(): void
    {
        // Create fibers for different responsibilities
        $renderFiber = new Fiber(function () {
            while ($this->running) {
                $this->render();
                Fiber::suspend();
            }
        });

        $inputFiber = new Fiber(function () {
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

        $backgroundFiber = new Fiber(function () {
            while ($this->running) {
                $this->updateBackground();
                Fiber::suspend();
            }
        });

        $taskFiber = new Fiber(function () {
            while ($this->running) {
                $this->processTasks();
                Fiber::suspend();
            }
        });

        // Start all fibers
        $renderFiber->start();
        $inputFiber->start();
        $backgroundFiber->start();
        $taskFiber->start();

        // Main event loop
        while ($this->running) {
            // Resume all fibers
            if ($renderFiber->isSuspended()) {
                $renderFiber->resume();
            }
            if ($inputFiber->isSuspended()) {
                $inputFiber->resume();
            }
            if ($backgroundFiber->isSuspended()) {
                $backgroundFiber->resume();
            }
            if ($taskFiber->isSuspended()) {
                $taskFiber->resume();
            }

            // Handle signals if available
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // 60 FPS (~16.67ms)
            usleep(16667);
        }
    }

    public function handleSignal(int $signal): void
    {
        $this->addLog("📡 Received signal {$signal}");
        $this->running = false;
    }

    public function cleanup(): void
    {
        $this->restoreTerminal();
        $this->exitAlternativeBuffer();
    }

    private function enterAlternativeBuffer(): void
    {
        // Enter alternative buffer - this prevents messing up the user's terminal
        echo "\033[?1049h";
    }

    private function exitAlternativeBuffer(): void
    {
        // Exit alternative buffer - restores original terminal content
        echo "\033[?1049l";
    }

    private function setupTerminal(): void
    {
        // Disable echo and canonical mode for immediate input
        system('stty -echo -icanon min 0 time 0 2>/dev/null', $result);

        // Hide cursor and clear screen
        echo "\033[?25l\033[2J\033[H";
    }

    private function restoreTerminal(): void
    {
        // Show cursor
        echo "\033[?25h";

        // Restore terminal settings
        system('stty echo icanon 2>/dev/null');
    }

    private function render(): void
    {
        $this->frame++;

        // Clear screen and move to top
        echo "\033[H\033[2J";

        // Header with animated title
        $spinner = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $spinChar = $spinner[$this->frame % count($spinner)];

        echo "\033[1;36m╔══════════════════════════════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m║\033[0m  \033[1;32m{$spinChar} Simple Async TUI Demo\033[0m" . str_repeat(' ', 38) . "\033[1;36m║\033[0m\n";
        echo "\033[1;36m║\033[0m  \033[33mTime: " . date('H:i:s') . "\033[0m" . str_repeat(' ', 48) . "\033[1;36m║\033[0m\n";
        echo "\033[1;36m╠══════════════════════════════════════════════════════════════╣\033[0m\n";

        // Status section
        echo "\033[1;36m║\033[0m \033[1mStatus:\033[0m" . str_repeat(' ', 53) . "\033[1;36m║\033[0m\n";
        echo "\033[1;36m║\033[0m   Counter: \033[1;32m{$this->counter}\033[0m" . str_repeat(' ', 50 - mb_strlen((string) $this->counter)) . "\033[1;36m║\033[0m\n";
        echo "\033[1;36m║\033[0m   Active Tasks: \033[1;33m" . count($this->tasks) . "\033[0m" . str_repeat(' ', 43 - mb_strlen((string) count($this->tasks))) . "\033[1;36m║\033[0m\n";
        echo "\033[1;36m║\033[0m   Tasks Completed: \033[1;35m{$this->taskCounter}\033[0m" . str_repeat(' ', 40 - mb_strlen((string) $this->taskCounter)) . "\033[1;36m║\033[0m\n";
        echo "\033[1;36m╠══════════════════════════════════════════════════════════════╣\033[0m\n";

        // Tasks section
        echo "\033[1;36m║\033[0m \033[1mActive Tasks:\033[0m" . str_repeat(' ', 47) . "\033[1;36m║\033[0m\n";

        $maxTasks = 5;
        $displayedTasks = 0;

        foreach ($this->tasks as $id => $task) {
            if ($displayedTasks >= $maxTasks) {
                break;
            }

            $progress = round(($task['elapsed'] / $task['duration']) * 100);
            $progressBar = $this->createProgressBar($progress, 20);
            $name = mb_substr($task['name'], 0, 15);

            echo "\033[1;36m║\033[0m   {$name}: {$progressBar} {$progress}%" . str_repeat(' ', 20 - mb_strlen($name) - mb_strlen((string) $progress)) . "\033[1;36m║\033[0m\n";
            $displayedTasks++;
        }

        // Fill remaining task lines
        for ($i = $displayedTasks; $i < $maxTasks; $i++) {
            echo "\033[1;36m║\033[0m" . str_repeat(' ', 60) . "\033[1;36m║\033[0m\n";
        }

        echo "\033[1;36m╠══════════════════════════════════════════════════════════════╣\033[0m\n";

        // Logs section
        echo "\033[1;36m║\033[0m \033[1mRecent Activity:\033[0m" . str_repeat(' ', 45) . "\033[1;36m║\033[0m\n";

        $maxLogs = 8;
        $recentLogs = array_slice($this->logs, -$maxLogs);

        foreach ($recentLogs as $log) {
            $logText = mb_substr($log, 0, 58);
            echo "\033[1;36m║\033[0m {$logText}" . str_repeat(' ', 59 - mb_strlen($logText)) . "\033[1;36m║\033[0m\n";
        }

        // Fill remaining log lines
        for ($i = count($recentLogs); $i < $maxLogs; $i++) {
            echo "\033[1;36m║\033[0m" . str_repeat(' ', 60) . "\033[1;36m║\033[0m\n";
        }

        // Footer
        echo "\033[1;36m╠══════════════════════════════════════════════════════════════╣\033[0m\n";
        echo "\033[1;36m║\033[0m \033[1mControls:\033[0m \033[32mSPACE\033[0m=Add Task  \033[32mR\033[0m=Reset  \033[32mQ\033[0m=Quit" . str_repeat(' ', 24) . "\033[1;36m║\033[0m\n";
        echo "\033[1;36m╚══════════════════════════════════════════════════════════════╝\033[0m\n";

        // Performance indicator
        echo "\033[90mFrame: {$this->frame} | Memory: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB\033[0m";
    }

    private function createProgressBar(int $percent, int $width): string
    {
        $filled = round(($percent / 100) * $width);
        $empty = $width - $filled;

        return "\033[42m" . str_repeat(' ', $filled) . "\033[0m" .
            "\033[100m" . str_repeat(' ', $empty) . "\033[0m";
    }

    private function updateBackground(): void
    {
        // Increment counter every ~500ms
        if ($this->frame % 30 == 0) { // 30 frames at 60fps = ~500ms
            $this->counter++;
        }

        // Add some random activity
        if ($this->frame % 180 == 0) { // Every ~3 seconds
            $activities = [
                'System heartbeat',
                'Background sync',
                'Cache refresh',
                'Health check',
                'Metric collection',
            ];

            $activity = $activities[array_rand($activities)];
            $this->addLog("🔄 {$activity}");
        }
    }

    private function processTasks(): void
    {
        foreach ($this->tasks as $id => &$task) {
            $task['elapsed'] += 0.017; // ~16.67ms per frame

            if ($task['elapsed'] >= $task['duration']) {
                $this->addLog("✅ Task '{$task['name']}' completed!");
                $this->taskCounter++;
                unset($this->tasks[$id]);
            }
        }
    }

    private function handleInput(string $char): void
    {
        switch (mb_strtolower($char)) {
            case 'q':
                $this->addLog('👋 Shutting down...');
                $this->running = false;
                break;
            case 'r':
                $this->counter = 0;
                $this->taskCounter = 0;
                $this->tasks = [];
                $this->logs = [];
                $this->addLog('🔄 Reset all counters');
                break;
            case ' ':
                $this->addRandomTask();
                break;
            case "\033": // ESC key
                $this->running = false;
                break;
        }
    }

    private function addRandomTask(): void
    {
        $taskNames = [
            'Data Processing',
            'File Upload',
            'Image Resize',
            'Email Send',
            'Report Generation',
            'Database Backup',
            'Log Analysis',
            'API Call',
            'Cache Warming',
            'Index Rebuild',
        ];

        $name = $taskNames[array_rand($taskNames)];
        $duration = rand(2, 8); // 2-8 seconds

        $this->tasks[uniqid()] = [
            'name' => $name,
            'duration' => $duration,
            'elapsed' => 0,
        ];

        $this->addLog("🚀 Started: {$name} ({$duration}s)");
    }

    private function addLog(string $message): void
    {
        $timestamp = date('H:i:s');
        $this->logs[] = "\033[90m{$timestamp}\033[0m {$message}";

        // Keep only last 50 logs to prevent memory issues
        if (count($this->logs) > 50) {
            $this->logs = array_slice($this->logs, -50);
        }
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    try {
        $tui = new SimpleTUI;
        $tui->run();
    } catch (Exception $e) {
        // Ensure we clean up even on exceptions
        echo "\033[?1049l\033[?25h";
        system('stty echo icanon 2>/dev/null');
        echo 'Error: ' . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "This script must be run from the command line.\n";
    exit(1);
}
