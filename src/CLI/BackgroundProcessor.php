<?php

namespace HelgeSverre\Swarm\CLI;

use Exception;
use Psr\Log\LoggerInterface;

/**
 * Handles launching and monitoring background processes for async operations
 */
class BackgroundProcessor
{
    protected $process;

    protected string $statusFile;

    protected float $startTime;

    protected bool $isRunning = false;

    public function __construct(
        protected readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Destructor to ensure cleanup
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * Launch a background process to handle the request
     */
    public function launch(string $input): string
    {
        // Create unique status file
        $this->statusFile = sys_get_temp_dir() . '/swarm_status_' . uniqid() . '.json';
        $this->startTime = microtime(true);

        // Build command to run AsyncProcessor
        $phpBinary = PHP_BINARY;
        $scriptPath = dirname(__DIR__, 2) . '/cli-process.php';
        $encodedInput = base64_encode($input);

        $command = sprintf(
            '%s %s %s %s',
            escapeshellcmd($phpBinary),
            escapeshellarg($scriptPath),
            escapeshellarg($encodedInput),
            escapeshellarg($this->statusFile)
        );

        $this->logger?->debug('Launching background process', [
            'command' => $command,
            'status_file' => $this->statusFile,
        ]);

        // Launch process
        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $this->process = proc_open($command, $descriptorspec, $pipes, null, null, [
            'bypass_shell' => true,
        ]);

        if (! is_resource($this->process)) {
            throw new Exception('Failed to launch background process');
        }

        // Close pipes we don't need
        fclose($pipes[0]);

        // Make output pipes non-blocking
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $this->isRunning = true;

        return $this->statusFile;
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
     * Read the current status from the status file
     */
    public function getStatus(): ?array
    {
        if (! file_exists($this->statusFile)) {
            return null;
        }

        $content = file_get_contents($this->statusFile);
        if (empty($content)) {
            return null;
        }

        $status = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger?->warning('Invalid JSON in status file', [
                'file' => $this->statusFile,
                'content' => $content,
                'error' => json_last_error_msg(),
            ]);

            return null;
        }

        // Add elapsed time
        $status['elapsed'] = microtime(true) - $this->startTime;

        return $status;
    }

    /**
     * Wait for the process to complete with a timeout
     */
    public function wait(float $timeout = 300): bool
    {
        $deadline = microtime(true) + $timeout;

        while ($this->isRunning() && microtime(true) < $deadline) {
            usleep(100000); // 100ms
        }

        return ! $this->isRunning();
    }

    /**
     * Wait for the status file to be created
     */
    public function waitForStatusFile(float $timeout = 5): bool
    {
        $deadline = microtime(true) + $timeout;

        while (! file_exists($this->statusFile) && microtime(true) < $deadline) {
            usleep(50000); // 50ms
        }

        return file_exists($this->statusFile);
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

            proc_close($this->process);
        }

        $this->isRunning = false;
    }

    /**
     * Clean up resources
     */
    public function cleanup(): void
    {
        $this->terminate();

        if (file_exists($this->statusFile)) {
            unlink($this->statusFile);
        }
    }
}
