<?php

namespace HelgeSverre\Swarm\Core;

use ErrorException;
use Exception;
use Psr\Log\LoggerInterface;
use Throwable;

class ExceptionHandler
{
    protected bool $debug;

    public function __construct(
        protected readonly ?LoggerInterface $logger = null
    ) {
        $this->debug = (bool) ($_ENV['DEBUG'] ?? false);
    }

    /**
     * Handle an exception/throwable
     */
    public function handle(Throwable $e): void
    {
        // Log the exception with full context
        $this->logException($e);

        // Display error to stderr (won't interfere with TUI)
        $this->displayError($e);

        // Exit with appropriate code
        exit($this->getExitCode($e));
    }

    /**
     * Set as global exception handler
     */
    public function register(): void
    {
        set_exception_handler([$this, 'handle']);

        // Also handle errors as exceptions
        set_error_handler(function ($severity, $message, $file, $line) {
            if (! (error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }

    /**
     * Log exception with full context
     */
    protected function logException(Throwable $e): void
    {
        if (! $this->logger) {
            return;
        }

        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        // Add stack trace at debug level
        if ($this->debug) {
            $context['trace'] = $e->getTraceAsString();
        }

        // Add previous exception if exists
        if ($previous = $e->getPrevious()) {
            $context['previous'] = [
                'exception' => get_class($previous),
                'message' => $previous->getMessage(),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
            ];
        }

        // Log as critical for uncaught exceptions
        $this->logger->critical('Uncaught exception', $context);
    }

    /**
     * Display error to stderr
     */
    protected function displayError(Throwable $e): void
    {
        $message = $this->formatErrorMessage($e);

        // Write to stderr to avoid interfering with TUI
        fwrite(STDERR, "\n\033[31mError: {$message}\033[0m\n");

        // Show debug info if enabled
        if ($this->debug) {
            fwrite(STDERR, "\033[90mFile: {$e->getFile()}:{$e->getLine()}\033[0m\n");
            fwrite(STDERR, "\033[90mType: " . get_class($e) . "\033[0m\n");
        }
    }

    /**
     * Format error message for display
     */
    protected function formatErrorMessage(Throwable $e): string
    {
        $message = $e->getMessage();

        // Clean up common error patterns
        if (str_contains($message, 'OpenAI API key not found')) {
            return 'OpenAI API key not found. Please set OPENAI_API_KEY environment variable or create a .env file.';
        }

        if (str_contains($message, 'Could not resolve host')) {
            return 'Network error: Unable to connect to OpenAI API. Please check your internet connection.';
        }

        if (str_contains($message, '401') || str_contains($message, 'Unauthorized')) {
            return 'Authentication failed. Please check your OpenAI API key.';
        }

        if (str_contains($message, '429') || str_contains($message, 'rate limit')) {
            return 'Rate limit exceeded. Please wait a moment and try again.';
        }

        // Return original message if no special handling
        return $message;
    }

    /**
     * Get exit code based on exception type
     */
    protected function getExitCode(Throwable $e): int
    {
        // Configuration errors
        if (str_contains($e->getMessage(), 'API key') || str_contains($e->getMessage(), 'environment')) {
            return 2;
        }

        // Authentication errors
        if (str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'Unauthorized')) {
            return 3;
        }

        // Network errors
        if (str_contains($e->getMessage(), 'Could not resolve host') || str_contains($e->getMessage(), 'Network')) {
            return 4;
        }

        // Default error code
        return 1;
    }
}
