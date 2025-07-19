<?php

namespace HelgeSverre\Swarm\CLI;

use Exception;
use Psr\Log\LoggerInterface;

/**
 * Enhanced background processor using pipe-based communication
 * for real-time progress updates instead of file-based IPC
 */
class StreamingBackgroundProcessor
{
    /** @var resource|false|null */
    protected $process = null;

    protected array $pipes = [];

    protected bool $isRunning = false;

    protected float $startTime;

    protected ?array $lastStatus = null;

    public function __construct(
        protected readonly ?LoggerInterface $logger = null
    ) {}

    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * Launch a background process with streaming communication
     */
    public function launch(string $input): void
    {
        $this->startTime = microtime(true);

        // Build command to run AsyncProcessor
        $phpBinary = PHP_BINARY;
        $scriptPath = dirname(__DIR__, 2) . '/cli-streaming-process.php';
        $encodedInput = base64_encode($input);

        // Get subprocess timeout from environment
        $subprocessTimeout = (int) ($_ENV['SWARM_SUBPROCESS_TIMEOUT'] ?? 300);

        // Build command array for proc_open to avoid shell escaping issues
        $command = [
            $phpBinary,
            '-d', "max_execution_time={$subprocessTimeout}",
            $scriptPath,
            $encodedInput,
            (string) $subprocessTimeout,
        ];

        $this->logger?->debug('Launching streaming background process', [
            'command' => implode(' ', array_map('escapeshellarg', $command)),
            'php_binary' => $phpBinary,
            'script' => $scriptPath,
        ]);

        // Launch process with pipes for real-time communication
        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout for progress updates
            2 => ['pipe', 'w'], // stderr for errors
        ];

        $this->process = proc_open($command, $descriptorspec, $this->pipes);

        if (! is_resource($this->process)) {
            throw new Exception('Failed to launch background process');
        }

        // Close stdin as we don't need it
        fclose($this->pipes[0]);

        // Make output pipes non-blocking for real-time reading
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        $this->isRunning = true;
    }

    /**
     * Read available updates from the process
     * Returns array of status updates received since last call
     */
    public function readUpdates(): array
    {
        $updates = [];

        if (! $this->isRunning || ! is_resource($this->pipes[1])) {
            return $updates;
        }

        // Read all available lines from stdout
        while (($line = fgets($this->pipes[1])) !== false) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Parse JSON message
            $data = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['timestamp'] = microtime(true);
                $updates[] = $data;
                $this->lastStatus = $data;

                $this->logger?->debug('Received progress update', $data);
            } else {
                $this->logger?->warning('Invalid JSON in process output', [
                    'line' => $line,
                    'error' => json_last_error_msg(),
                ]);
            }
        }

        // Check for errors on stderr
        while (($error = fgets($this->pipes[2])) !== false) {
            $error = trim($error);
            if (! empty($error)) {
                $this->logger?->error('Process error output', ['error' => $error]);
                $updates[] = [
                    'type' => 'error',
                    'message' => $error,
                    'timestamp' => microtime(true),
                ];
            }
        }

        // Update running status
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            $this->isRunning = $status['running'];
        }

        return $updates;
    }

    /**
     * Get the last known status
     */
    public function getLastStatus(): ?array
    {
        return $this->lastStatus;
    }

    /**
     * Check if the process is still running
     */
    public function isRunning(): bool
    {
        if (! $this->isRunning || ! is_resource($this->process)) {
            return false;
        }

        $status = proc_get_status($this->process);
        $this->isRunning = $status['running'];

        return $this->isRunning;
    }

    /**
     * Wait for the process to complete with a timeout
     */
    public function wait(float $timeout = 300): bool
    {
        $deadline = microtime(true) + $timeout;

        while ($this->isRunning() && microtime(true) < $deadline) {
            // Read any pending updates
            $this->readUpdates();
            usleep(50000); // 50ms
        }

        return ! $this->isRunning();
    }

    /**
     * Terminate the process if it's still running
     */
    public function terminate(): void
    {
        if ($this->isRunning && is_resource($this->process)) {
            // Try graceful termination first
            proc_terminate($this->process, SIGTERM);

            // Give it a moment to clean up
            usleep(500000); // 500ms

            // Force kill if still running
            if ($this->isRunning()) {
                proc_terminate($this->process, SIGKILL);
            }
        }

        $this->cleanup();
    }

    /**
     * Clean up resources
     */
    public function cleanup(): void
    {
        // Close pipes
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        // Close process
        if (is_resource($this->process)) {
            proc_close($this->process);
        }

        $this->isRunning = false;
        $this->pipes = [];
        $this->process = null;
    }
}
