<?php

declare(strict_types=1);

namespace Examples\TuiLib\Core;

/**
 * Debug logging levels
 */
enum LogLevel: string
{
    case DEBUG = 'DEBUG';
    case INFO = 'INFO';
    case WARN = 'WARN';
    case ERROR = 'ERROR';
    case RENDER = 'RENDER';
    case PERFORMANCE = 'PERF';
}

/**
 * Logger for TUI framework debugging and performance monitoring
 */
class Logger
{
    protected static ?self $instance = null;

    protected array $logs = [];

    protected array $enabledLevels = [];

    protected bool $logToFile = false;

    protected ?string $logFile = null;

    protected array $performanceMetrics = [];

    protected array $timers = [];

    protected function __construct()
    {
        // Default enabled levels
        $this->enabledLevels = [
            LogLevel::ERROR->value => true,
            LogLevel::WARN->value => true,
            LogLevel::INFO->value => true,
        ];

        // Enable file logging if TUI_DEBUG environment variable is set
        if (getenv('TUI_DEBUG')) {
            // Create logs directory if it doesn't exist
            $logDir = __DIR__ . '/../../storage/logs';
            if (! is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $this->enableFileLogging($logDir . '/tui-debug.log');
            $this->enabledLevels[LogLevel::DEBUG->value] = true;
            $this->enabledLevels[LogLevel::RENDER->value] = true;
            $this->enabledLevels[LogLevel::PERFORMANCE->value] = true;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Enable file logging
     */
    public function enableFileLogging(string $filePath): void
    {
        $this->logToFile = true;
        $this->logFile = $filePath;

        // Clear the log file
        file_put_contents($filePath, '');
    }

    /**
     * Enable specific log levels
     */
    public function enableLevel(LogLevel $level): void
    {
        $this->enabledLevels[$level->value] = true;
    }

    /**
     * Disable specific log levels
     */
    public function disableLevel(LogLevel $level): void
    {
        unset($this->enabledLevels[$level->value]);
    }

    /**
     * Log a message
     */
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        if (! isset($this->enabledLevels[$level->value])) {
            return;
        }

        $timestamp = microtime(true);
        $formattedTime = date('H:i:s', (int) $timestamp) . '.' . mb_str_pad((string) (($timestamp - floor($timestamp)) * 1000), 3, '0', STR_PAD_LEFT);

        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level->value,
            'message' => $message,
            'context' => $context,
            'formatted' => "[{$formattedTime}] [{$level->value}] {$message}",
        ];

        if (! empty($context)) {
            $logEntry['formatted'] .= ' ' . json_encode($context);
        }

        $this->logs[] = $logEntry;

        // Keep only last 1000 log entries in memory
        if (count($this->logs) > 1000) {
            array_shift($this->logs);
        }

        // Write to file if enabled
        if ($this->logToFile && $this->logFile) {
            file_put_contents($this->logFile, $logEntry['formatted'] . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Start a performance timer
     */
    public function startTimer(string $name): void
    {
        $this->timers[$name] = microtime(true);
    }

    /**
     * End a performance timer and log the result
     */
    public function endTimer(string $name): float
    {
        if (! isset($this->timers[$name])) {
            $this->log(LogLevel::WARN, "Timer '{$name}' was not started");

            return 0.0;
        }

        $duration = microtime(true) - $this->timers[$name];
        unset($this->timers[$name]);

        $this->log(LogLevel::PERFORMANCE, "Timer '{$name}' completed", [
            'duration_ms' => round($duration * 1000, 2),
        ]);

        return $duration;
    }

    /**
     * Static wrapper for startTimer
     */
    public static function startTimerStatic(string $name): void
    {
        self::getInstance()->startTimer($name);
    }

    /**
     * Static wrapper for endTimer
     */
    public static function endTimerStatic(string $name): float
    {
        return self::getInstance()->endTimer($name);
    }

    /**
     * Log render frame information
     */
    public function logFrame(int $frameNumber, array $metrics = []): void
    {
        $this->log(LogLevel::RENDER, "Frame {$frameNumber}", $metrics);
    }

    /**
     * Static wrapper for logFrame
     */
    public static function logFrameStatic(int $frameNumber, array $metrics = []): void
    {
        self::getInstance()->logFrame($frameNumber, $metrics);
    }

    /**
     * Log widget lifecycle events
     */
    public function logWidget(string $widgetId, string $phase, array $context = []): void
    {
        $this->log(LogLevel::RENDER, "Widget '{$widgetId}' {$phase}", $context);
    }

    /**
     * Static version for widget logging
     */
    public static function logWidgetStatic(string $widgetId, string $phase, array $context = []): void
    {
        self::getInstance()->logWidget($widgetId, $phase, $context);
    }

    /**
     * Log buffer changes
     */
    public function logBufferChange(string $operation, array $details = []): void
    {
        $this->log(LogLevel::DEBUG, "Buffer {$operation}", $details);
    }

    /**
     * Static wrapper for logBufferChange
     */
    public static function logBufferChangeStatic(string $operation, array $details = []): void
    {
        self::getInstance()->logBufferChange($operation, $details);
    }

    /**
     * Get recent logs
     */
    public function getRecentLogs(int $count = 50): array
    {
        return array_slice($this->logs, -$count);
    }

    /**
     * Get logs by level
     */
    public function getLogsByLevel(LogLevel $level): array
    {
        return array_filter($this->logs, fn ($log) => $log['level'] === $level->value);
    }

    /**
     * Get performance summary
     */
    public function getPerformanceSummary(): array
    {
        $perfLogs = $this->getLogsByLevel(LogLevel::PERFORMANCE);
        $summary = [];

        foreach ($perfLogs as $log) {
            $context = $log['context'];
            if (isset($context['duration_ms'])) {
                $operation = $log['message'];
                if (! isset($summary[$operation])) {
                    $summary[$operation] = [
                        'count' => 0,
                        'total_ms' => 0,
                        'min_ms' => PHP_FLOAT_MAX,
                        'max_ms' => 0,
                    ];
                }

                $duration = $context['duration_ms'];
                $summary[$operation]['count']++;
                $summary[$operation]['total_ms'] += $duration;
                $summary[$operation]['min_ms'] = min($summary[$operation]['min_ms'], $duration);
                $summary[$operation]['max_ms'] = max($summary[$operation]['max_ms'], $duration);
                $summary[$operation]['avg_ms'] = $summary[$operation]['total_ms'] / $summary[$operation]['count'];
            }
        }

        return $summary;
    }

    /**
     * Clear all logs
     */
    public function clear(): void
    {
        $this->logs = [];
        $this->performanceMetrics = [];
        $this->timers = [];
    }

    /**
     * Quick static logging methods
     */
    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->log(LogLevel::DEBUG, $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->log(LogLevel::INFO, $message, $context);
    }

    public static function warn(string $message, array $context = []): void
    {
        self::getInstance()->log(LogLevel::WARN, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->log(LogLevel::ERROR, $message, $context);
    }

    public static function render(string $message, array $context = []): void
    {
        self::getInstance()->log(LogLevel::RENDER, $message, $context);
    }

    public static function perf(string $message, array $context = []): void
    {
        self::getInstance()->log(LogLevel::PERFORMANCE, $message, $context);
    }
}
