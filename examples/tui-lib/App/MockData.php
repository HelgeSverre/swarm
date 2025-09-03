<?php

declare(strict_types=1);

namespace Examples\TuiLib\App;

use DateTimeImmutable;

/**
 * Activity types for the activity log
 */
enum ActivityType: string
{
    case Command = 'command';
    case Response = 'response';
    case Error = 'error';
    case Info = 'info';
    case Warning = 'warning';
    case Success = 'success';
}

/**
 * Activity log entry
 */
readonly class Activity
{
    public function __construct(
        public string $id,
        public ActivityType $type,
        public string $message,
        public DateTimeImmutable $timestamp,
        public array $metadata = []
    ) {}

    public function getIcon(): string
    {
        return match ($this->type) {
            ActivityType::Command => '▶',
            ActivityType::Response => '◀',
            ActivityType::Error => '✗',
            ActivityType::Info => 'ℹ',
            ActivityType::Warning => '⚠',
            ActivityType::Success => '✓',
        };
    }

    public function getColor(): string
    {
        return match ($this->type) {
            ActivityType::Command => '36',    // Cyan
            ActivityType::Response => '32',   // Green
            ActivityType::Error => '31',      // Red
            ActivityType::Info => '34',       // Blue
            ActivityType::Warning => '33',    // Yellow
            ActivityType::Success => '32',    // Green
        };
    }
}

/**
 * Task status enumeration
 */
enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}

/**
 * Task entry
 */
readonly class Task
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public TaskStatus $status,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
        public array $metadata = []
    ) {}

    public function getStatusIcon(): string
    {
        return match ($this->status) {
            TaskStatus::Pending => '○',
            TaskStatus::InProgress => '◐',
            TaskStatus::Completed => '●',
            TaskStatus::Failed => '✗',
            TaskStatus::Cancelled => '◌',
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            TaskStatus::Pending => '37',      // White
            TaskStatus::InProgress => '33',   // Yellow
            TaskStatus::Completed => '32',    // Green
            TaskStatus::Failed => '31',       // Red
            TaskStatus::Cancelled => '90',    // Dark Gray
        };
    }
}

/**
 * File entry for file browser
 */
readonly class FileEntry
{
    public function __construct(
        public string $name,
        public string $path,
        public bool $isDirectory,
        public int $size = 0,
        public ?DateTimeImmutable $modifiedAt = null
    ) {}

    public function getIcon(): string
    {
        if ($this->isDirectory) {
            return '📁';
        }

        $extension = pathinfo($this->name, PATHINFO_EXTENSION);

        return match (mb_strtolower($extension)) {
            'php' => '🐘',
            'js', 'ts' => '📜',
            'json' => '📋',
            'md' => '📝',
            'txt' => '📄',
            'css' => '🎨',
            'html', 'htm' => '🌐',
            'xml' => '📊',
            'yml', 'yaml' => '⚙️',
            'sql' => '🗃️',
            default => '📄',
        };
    }

    public function formatSize(): string
    {
        if ($this->isDirectory) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return sprintf('%.1f %s', $size, $units[$unit]);
    }
}

/**
 * Note entry
 */
readonly class Note
{
    public function __construct(
        public string $id,
        public string $title,
        public string $content,
        public array $tags = [],
        public DateTimeImmutable $createdAt = new DateTimeImmutable,
        public ?DateTimeImmutable $updatedAt = null
    ) {}
}

/**
 * Mock data generator for the TUI demo
 */
class MockData
{
    private static int $activityCounter = 0;

    private static int $taskCounter = 0;

    private static int $noteCounter = 0;

    /**
     * Generate sample activities
     *
     * @return Activity[]
     */
    public static function generateActivities(int $count = 20): array
    {
        $activities = [];
        $types = ActivityType::cases();
        $commands = [
            'find . -name "*.php"',
            'grep -r "function" src/',
            'composer install',
            'php artisan migrate',
            'npm run build',
            'git status',
            'docker ps',
            'tail -f app.log',
        ];
        $responses = [
            'Found 15 PHP files',
            'Search completed successfully',
            'Dependencies installed',
            'Migration completed',
            'Build finished',
            'Working directory clean',
            'No containers running',
            'Log monitoring started',
        ];
        $errors = [
            'File not found: config.php',
            'Syntax error in line 42',
            'Connection timeout',
            'Permission denied',
            'Invalid argument provided',
        ];
        $infos = [
            'Application started',
            'Cache cleared',
            'Session initialized',
            'Config loaded',
            'Database connected',
        ];

        for ($i = 0; $i < $count; $i++) {
            $type = $types[array_rand($types)];
            $timestamp = new DateTimeImmutable('-' . rand(0, 3600) . ' seconds');

            $message = match ($type) {
                ActivityType::Command => $commands[array_rand($commands)],
                ActivityType::Response => $responses[array_rand($responses)],
                ActivityType::Error => $errors[array_rand($errors)],
                ActivityType::Info, ActivityType::Success => $infos[array_rand($infos)],
                ActivityType::Warning => 'Deprecated function used in line ' . rand(1, 100),
            };

            $activities[] = new Activity(
                id: 'activity_' . ++self::$activityCounter,
                type: $type,
                message: $message,
                timestamp: $timestamp,
                metadata: ['source' => 'demo']
            );
        }

        // Sort by timestamp (newest first)
        usort($activities, fn ($a, $b) => $b->timestamp <=> $a->timestamp);

        return $activities;
    }

    /**
     * Generate sample tasks
     *
     * @return Task[]
     */
    public static function generateTasks(int $count = 10): array
    {
        $tasks = [];
        $statuses = TaskStatus::cases();
        $titles = [
            'Implement user authentication',
            'Add file upload functionality',
            'Optimize database queries',
            'Create API documentation',
            'Fix responsive design issues',
            'Add unit tests',
            'Implement caching layer',
            'Update dependencies',
            'Refactor legacy code',
            'Setup CI/CD pipeline',
        ];
        $descriptions = [
            'Implement JWT-based authentication system with refresh tokens',
            'Add support for multiple file formats with validation',
            'Optimize slow queries and add proper indexing',
            'Generate OpenAPI documentation for all endpoints',
            'Fix layout issues on mobile devices',
            'Increase test coverage to 80%',
            'Implement Redis caching for frequently accessed data',
            'Update all packages to latest stable versions',
            'Modernize legacy PHP code to use new features',
            'Setup automated testing and deployment workflows',
        ];

        for ($i = 0; $i < $count; $i++) {
            $createdAt = new DateTimeImmutable('-' . rand(3600, 86400 * 7) . ' seconds');
            $status = $statuses[array_rand($statuses)];

            $updatedAt = null;
            if ($status !== TaskStatus::Pending) {
                $updatedAt = new DateTimeImmutable('-' . rand(0, 3600) . ' seconds');
            }

            $tasks[] = new Task(
                id: 'task_' . ++self::$taskCounter,
                title: $titles[$i % count($titles)],
                description: $descriptions[$i % count($descriptions)],
                status: $status,
                createdAt: $createdAt,
                updatedAt: $updatedAt,
                metadata: ['priority' => ['low', 'medium', 'high'][rand(0, 2)]]
            );
        }

        return $tasks;
    }

    /**
     * Generate sample file entries
     *
     * @return FileEntry[]
     */
    public static function generateFiles(): array
    {
        $files = [
            new FileEntry('src', '/project/src', true),
            new FileEntry('tests', '/project/tests', true),
            new FileEntry('vendor', '/project/vendor', true),
            new FileEntry('public', '/project/public', true),
            new FileEntry('composer.json', '/project/composer.json', false, 2048, new DateTimeImmutable('-1 hour')),
            new FileEntry('composer.lock', '/project/composer.lock', false, 45632, new DateTimeImmutable('-1 hour')),
            new FileEntry('package.json', '/project/package.json', false, 1024, new DateTimeImmutable('-2 hours')),
            new FileEntry('README.md', '/project/README.md', false, 3072, new DateTimeImmutable('-1 day')),
            new FileEntry('phpunit.xml', '/project/phpunit.xml', false, 512, new DateTimeImmutable('-3 days')),
            new FileEntry('docker-compose.yml', '/project/docker-compose.yml', false, 1536, new DateTimeImmutable('-1 week')),
            new FileEntry('index.php', '/project/index.php', false, 4096, new DateTimeImmutable('-2 days')),
            new FileEntry('config.php', '/project/config.php', false, 2560, new DateTimeImmutable('-5 hours')),
            new FileEntry('routes.php', '/project/routes.php', false, 1792, new DateTimeImmutable('-3 hours')),
            new FileEntry('database.sql', '/project/database.sql', false, 8192, new DateTimeImmutable('-1 week')),
            new FileEntry('style.css', '/project/style.css', false, 5120, new DateTimeImmutable('-2 days')),
        ];

        return $files;
    }

    /**
     * Generate sample notes
     *
     * @return Note[]
     */
    public static function generateNotes(int $count = 8): array
    {
        $notes = [];
        $titles = [
            'Project Setup Notes',
            'API Design Decisions',
            'Performance Optimization Ideas',
            'Bug Investigation Notes',
            'Code Review Checklist',
            'Deployment Procedures',
            'Database Schema Changes',
            'Third-party Integration Notes',
        ];
        $contents = [
            'Remember to configure environment variables...',
            'RESTful API design principles to follow...',
            'Identified slow database queries that need optimization...',
            'Steps to reproduce the authentication bug...',
            'Items to check during code reviews...',
            'Step-by-step deployment process...',
            'Schema migration notes and considerations...',
            'Integration details for payment gateway...',
        ];
        $tagSets = [
            ['setup', 'config'],
            ['api', 'design'],
            ['performance', 'database'],
            ['bug', 'authentication'],
            ['process', 'quality'],
            ['deployment', 'production'],
            ['database', 'migration'],
            ['integration', 'payments'],
        ];

        for ($i = 0; $i < $count; $i++) {
            $createdAt = new DateTimeImmutable('-' . rand(86400, 86400 * 30) . ' seconds');

            $notes[] = new Note(
                id: 'note_' . ++self::$noteCounter,
                title: $titles[$i % count($titles)],
                content: $contents[$i % count($contents)],
                tags: $tagSets[$i % count($tagSets)],
                createdAt: $createdAt
            );
        }

        return $notes;
    }

    /**
     * Reset counters (useful for testing)
     */
    public static function resetCounters(): void
    {
        self::$activityCounter = 0;
        self::$taskCounter = 0;
        self::$noteCounter = 0;
    }
}
