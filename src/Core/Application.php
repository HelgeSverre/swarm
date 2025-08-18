<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Core;

use Dotenv\Dotenv;
use Exception;
use HelgeSverre\Swarm\Exceptions\EnvironmentLoadException;
use HelgeSverre\Swarm\Exceptions\MissingApiKeyException;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Application bootstrap and configuration container
 *
 * Handles environment loading, PHP runtime configuration,
 * error handling setup, and provides access to common paths and config.
 */
class Application
{
    const VERSION = '1.0.0';

    protected string $basePath;

    protected ?LoggerInterface $logger = null;

    protected ?ExceptionHandler $exceptionHandler = null;

    protected array $config = [];

    protected array $paths = [];

    protected string $projectPath;

    /**
     * Create and bootstrap the application
     */
    public function __construct(string $basePath, ?string $projectPath = null)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->projectPath = $projectPath ? rtrim($projectPath, '/') : $this->basePath;
        $this->initializePaths();
        $this->bootstrap();
    }

    /**
     * Get the base path with optional path appended
     */
    public function basePath(string $path = ''): string
    {
        return $this->paths['base'] . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Get the bin path with optional path appended
     */
    public function binPath(string $path = ''): string
    {
        return $this->paths['bin'] . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Get the storage path with optional path appended
     */
    public function storagePath(string $path = ''): string
    {
        return $this->paths['storage'] . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Get the log path with optional path appended
     */
    public function logPath(string $path = ''): string
    {
        return $this->paths['logs'] . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Get the project path (where CLI was called from)
     */
    public function projectPath(string $path = ''): string
    {
        return $this->projectPath . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Get configuration value by key
     *
     * @param string $key Dot notation key (e.g., 'openai.api_key')
     * @param mixed $default Default value if key doesn't exist
     */
    public function config(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (! isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get the logger instance
     */
    public function logger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Get the exception handler instance
     */
    public function exceptionHandler(): ?ExceptionHandler
    {
        return $this->exceptionHandler;
    }

    /**
     * Get the application version
     */
    public function version(): string
    {
        return self::VERSION;
    }

    /**
     * Initialize application paths
     */
    protected function initializePaths(): void
    {
        $this->paths = [
            'base' => $this->basePath,
            'bin' => $this->basePath . '/bin',
            'storage' => $this->basePath . '/storage',
            'logs' => $this->basePath . '/storage/logs',
            'cache' => $this->basePath . '/storage/cache',
            'temp' => $this->basePath . '/storage/temp',
        ];
    }

    /**
     * Bootstrap the application
     */
    protected function bootstrap(): void
    {
        // Set PHP runtime configuration
        $this->configurePHPRuntime();

        // Load environment variables
        $this->loadEnvironment();

        // Setup error handling
        $this->setupErrorHandling();

        // Setup logger if enabled
        $this->setupLogger();
    }

    /**
     * Configure PHP runtime settings
     */
    protected function configurePHPRuntime(): void
    {
        // Unlimited execution time for CLI
        set_time_limit(0);

        // Ensure we can handle large memory usage
        ini_set('memory_limit', '4G');
    }

    /**
     * Load environment variables
     */
    protected function loadEnvironment(): void
    {
        $envPath = $this->basePath . '/.env';

        if (file_exists($envPath)) {
            try {
                $dotenv = Dotenv::createImmutable($this->basePath);
                $dotenv->load();

                // Validate required environment variables
                $dotenv->required('OPENAI_API_KEY')->notEmpty();
            } catch (Exception $e) {
                throw new EnvironmentLoadException($e->getMessage());
            }
        } else {
            // Check if OPENAI_API_KEY is set in environment
            if (empty($_ENV['OPENAI_API_KEY']) && empty(getenv('OPENAI_API_KEY'))) {
                throw new MissingApiKeyException;
            }
        }

        // Store configuration from environment
        $this->config = [
            'openai' => [
                'api_key' => $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY'),
                'model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini',
                'temperature' => (float) ($_ENV['OPENAI_TEMPERATURE'] ?? 0.7),
            ],
            'logging' => [
                'enabled' => filter_var($_ENV['LOG_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
                'path' => $_ENV['LOG_PATH'] ?? $this->paths['logs'],
            ],
            'app' => [
                'version' => self::VERSION,
                'subprocess_timeout' => (int) ($_ENV['SWARM_SUBPROCESS_TIMEOUT'] ?? 300),
            ],
        ];
    }

    /**
     * Setup error and exception handling
     */
    protected function setupErrorHandling(): void
    {
        $this->exceptionHandler = new ExceptionHandler($this->logger);
        $this->exceptionHandler->register();
    }

    /**
     * Setup logger if enabled
     */
    protected function setupLogger(): void
    {
        if (! $this->config['logging']['enabled']) {
            return;
        }

        try {
            $this->logger = new Logger('swarm');
            $logPath = $this->config['logging']['path'];

            if (! is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }

            $logLevel = match (mb_strtolower($this->config['logging']['level'])) {
                'debug' => Level::Debug,
                'warning', 'warn' => Level::Warning,
                'error', 'err', 'danger' => Level::Error,
                default => Level::Info,
            };

            $this->logger->pushHandler(
                new RotatingFileHandler("{$logPath}/swarm.log", 7, $logLevel)
            );

            // Update exception handler with logger
            if ($this->exceptionHandler) {
                $this->exceptionHandler = new ExceptionHandler($this->logger);
                $this->exceptionHandler->register();
            }
        } catch (Exception $e) {
            // Ignore logging setup errors
            fwrite(STDERR, 'Warning: Could not set up logging: ' . $e->getMessage() . "\n");
        }
    }
}
