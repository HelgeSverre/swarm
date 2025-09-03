<?php

declare(strict_types=1);

namespace Examples\TuiLib\SwarmMock;

/**
 * Mock data generator for Swarm-specific patterns
 * Replicates the exact data structures and patterns from FullTerminalUI
 */
class SwarmMockData
{
    /**
     * Generate Swarm-style conversation history entries
     */
    public static function generateSwarmHistory(int $count = 50): array
    {
        $history = [];
        $baseTime = time() - ($count * 120); // Start 2 minutes per entry ago

        $commandPatterns = [
            'Analyze this error message and suggest a fix',
            'Create a new React component for user authentication',
            'Refactor the database connection logic',
            'Add unit tests for the payment processing module',
            'Update the documentation for the API endpoints',
            'Optimize the SQL query in the user search function',
            'Fix the responsive design issues on mobile',
            'Implement caching for the product catalog',
            'Add validation to the user registration form',
            'Debug the intermittent timeout issues',
        ];

        $assistantResponses = [
            "I'll analyze the error and provide a solution. Let me examine the stack trace first.",
            "I'll create a React authentication component with proper form validation and state management.",
            "I'll refactor the database connection to use a singleton pattern with connection pooling.",
            "I'll add comprehensive unit tests covering edge cases and error scenarios.",
            "I'll update the API documentation with proper examples and response schemas.",
            "I'll optimize the query by adding appropriate indexes and restructuring the WHERE clause.",
            "I'll fix the mobile responsive issues using CSS Grid and proper media queries.",
            "I'll implement Redis caching with appropriate TTL values for the product data.",
            "I'll add client-side and server-side validation with proper error messages.",
            "I'll investigate the timeout issues and implement proper retry logic with exponential backoff.",
        ];

        $statusMessages = [
            'Analyzing code structure...',
            'Reading file: src/components/Auth.tsx',
            'Executing tool: find_files',
            'Writing file: tests/PaymentTest.php',
            'Updating documentation: api/users.md',
            'Optimizing database query...',
            'Applying CSS fixes...',
            'Configuring Redis cache...',
            'Adding form validation...',
            'Debugging network requests...',
        ];

        $toolActivities = [
            ['tool' => 'read_file', 'params' => ['path' => 'src/auth/LoginForm.tsx'], 'result' => 'Success'],
            ['tool' => 'write_file', 'params' => ['path' => 'components/AuthComponent.tsx'], 'result' => 'Success'],
            ['tool' => 'find_files', 'params' => ['pattern' => '*.sql'], 'result' => 'Found 12 files'],
            ['tool' => 'search_code', 'params' => ['query' => 'database connection'], 'result' => '8 matches'],
            ['tool' => 'terminal', 'params' => ['command' => 'npm test'], 'result' => 'Tests passed: 15/15'],
            ['tool' => 'edit_file', 'params' => ['path' => 'database/query.sql'], 'result' => 'Success'],
            ['tool' => 'write_file', 'params' => ['path' => 'styles/mobile.css'], 'result' => 'Success'],
            ['tool' => 'terminal', 'params' => ['command' => 'redis-cli ping'], 'result' => 'PONG'],
            ['tool' => 'read_file', 'params' => ['path' => 'forms/validation.js'], 'result' => 'Success'],
            ['tool' => 'terminal', 'params' => ['command' => 'curl -v api/health'], 'result' => 'HTTP 200'],
        ];

        // Generate alternating conversation pattern
        for ($i = 0; $i < $count; $i++) {
            $time = $baseTime + ($i * 120);

            if ($i % 8 === 0) {
                // User command
                $history[] = [
                    'time' => $time,
                    'type' => 'command',
                    'content' => $commandPatterns[array_rand($commandPatterns)],
                ];
            } elseif ($i % 8 === 1) {
                // Assistant response
                $history[] = [
                    'time' => $time + 10,
                    'type' => 'assistant',
                    'content' => $assistantResponses[array_rand($assistantResponses)],
                    'thought' => 'I need to break this down into steps: 1) Analyze the current implementation, 2) Identify the issues, 3) Create a solution that follows best practices, 4) Test the implementation, 5) Document the changes for future reference.',
                ];
            } elseif ($i % 8 < 6) {
                // Status updates and tool calls
                if (rand(0, 1)) {
                    $history[] = [
                        'time' => $time + 20,
                        'type' => 'status',
                        'content' => $statusMessages[array_rand($statusMessages)],
                    ];
                } else {
                    $toolActivity = $toolActivities[array_rand($toolActivities)];
                    $history[] = [
                        'time' => $time + 30,
                        'type' => 'tool_activity',
                        'content' => "🔧 {$toolActivity['tool']} " . implode(' ', $toolActivity['params']) . " → {$toolActivity['result']}",
                        'activity_object' => (object) [
                            'tool' => $toolActivity['tool'],
                            'params' => $toolActivity['params'],
                            'result' => $toolActivity['result'],
                            'timestamp' => $time + 30,
                            'getMessage' => function () use ($toolActivity) {
                                return "🔧 {$toolActivity['tool']} " . implode(' ', $toolActivity['params']) . " → {$toolActivity['result']}";
                            },
                        ],
                    ];
                }
            } else {
                // System messages and errors
                $errorMessages = [
                    'File not found: config/database.php',
                    'Syntax error in routes/api.php:42',
                    'Connection timeout to Redis server',
                    'Permission denied: storage/logs/app.log',
                    'Class not found: App\\Services\\PaymentProcessor',
                    'Invalid JSON in package.json',
                    'Port 3306 already in use',
                ];

                if (rand(0, 3) === 0) {
                    $history[] = [
                        'time' => $time + 40,
                        'type' => 'error',
                        'content' => $errorMessages[array_rand($errorMessages)],
                    ];
                } else {
                    $history[] = [
                        'time' => $time + 50,
                        'type' => 'system',
                        'content' => 'Task completed successfully',
                    ];
                }
            }
        }

        return $history;
    }

    /**
     * Generate Swarm-style task queue
     */
    public static function generateSwarmTasks(int $count = 15): array
    {
        $tasks = [];
        $taskDescriptions = [
            'Implement user authentication system',
            'Add file upload functionality to dashboard',
            'Optimize database queries for better performance',
            'Create comprehensive API documentation',
            'Fix responsive design issues on mobile devices',
            'Add unit tests for payment processing',
            'Implement Redis caching layer',
            'Update all npm dependencies to latest versions',
            'Refactor legacy authentication code',
            'Setup CI/CD pipeline with GitHub Actions',
            'Add TypeScript support to existing JavaScript',
            'Implement real-time notifications with WebSockets',
            'Create automated backup system for database',
            'Add internationalization support (i18n)',
            'Optimize images and implement lazy loading',
            'Setup error monitoring with Sentry',
            'Implement rate limiting for API endpoints',
            'Add comprehensive logging throughout application',
            'Create admin dashboard for user management',
            'Setup automated testing with Jest and Cypress',
        ];

        $statuses = ['pending', 'running', 'completed', 'failed'];
        $statusWeights = [60, 20, 15, 5]; // Mostly pending, some running, few completed/failed

        for ($i = 0; $i < $count; $i++) {
            $statusIndex = self::weightedRandom($statusWeights);
            $status = $statuses[$statusIndex];

            $steps = rand(3, 8);
            $completedSteps = match ($status) {
                'pending' => 0,
                'running' => rand(1, $steps - 1),
                'completed' => $steps,
                'failed' => rand(1, $steps - 1),
            };

            $tasks[] = [
                'id' => 'task_' . ($i + 1),
                'description' => $taskDescriptions[$i % count($taskDescriptions)],
                'status' => $status,
                'steps' => $steps,
                'completed_steps' => $completedSteps,
                'created_at' => time() - rand(3600, 86400), // 1 hour to 1 day ago
                'updated_at' => time() - rand(0, 3600), // Up to 1 hour ago
            ];
        }

        return $tasks;
    }

    /**
     * Generate Swarm-style context information
     */
    public static function generateSwarmContext(): array
    {
        return [
            'directory' => '/Users/developer/projects/swarm-demo',
            'files' => [
                'src/components/AuthForm.tsx',
                'src/services/api.ts',
                'database/migrations/001_users.sql',
                'tests/auth.test.js',
                'package.json',
                'README.md',
                'src/utils/validation.ts',
                'styles/main.css',
                'config/database.php',
                'routes/api.php',
            ],
            'tools' => [
                'read_file',
                'write_file',
                'find_files',
                'search_code',
                'terminal',
                'edit_file',
            ],
            'notes' => [
                'Authentication flow needs to be simplified',
                'Database connection pooling shows 30% performance improvement',
                'Mobile breakpoint should be 768px instead of 640px',
                'Redis cache TTL set to 1 hour for user sessions',
                'API rate limit: 100 requests per minute per user',
                'Jest tests cover 87% of codebase',
            ],
        ];
    }

    /**
     * Get current progress information for display
     */
    public static function getCurrentProgress(): array
    {
        return [
            'current_step' => rand(2, 5),
            'total_steps' => 6,
            'operation' => 'Implementing authentication middleware...',
            'last_tool' => 'write_file',
            'last_tool_params' => ['path' => 'middleware/auth.php'],
            'last_tool_result' => 'Success',
        ];
    }

    /**
     * Generate activity feed entries that match ToolCallEntry format
     */
    public static function generateRecentActivities(int $count = 20): array
    {
        $activities = [];
        $baseTime = time() - ($count * 60); // One per minute going back

        $tools = ['read_file', 'write_file', 'find_files', 'search_code', 'terminal', 'edit_file'];
        $filePaths = [
            'src/auth/LoginForm.tsx',
            'src/services/AuthService.ts',
            'database/schema.sql',
            'tests/auth.test.js',
            'config/app.php',
            'routes/web.php',
            'styles/components.css',
            'package.json',
        ];

        for ($i = 0; $i < $count; $i++) {
            $tool = $tools[array_rand($tools)];
            $timestamp = $baseTime + ($i * 60);

            $params = match ($tool) {
                'read_file', 'write_file', 'edit_file' => ['path' => $filePaths[array_rand($filePaths)]],
                'find_files' => ['pattern' => '*.php'],
                'search_code' => ['query' => 'function authenticate'],
                'terminal' => ['command' => ['npm test', 'composer install', 'php artisan migrate'][array_rand(['npm test', 'composer install', 'php artisan migrate'])]],
                default => [],
            };

            $success = rand(0, 10) > 1; // 90% success rate
            $result = $success ? 'Success' : 'Failed';

            // Create a mock object that mimics ToolCallEntry behavior
            $activities[] = (object) [
                'tool' => $tool,
                'params' => $params,
                'result' => $result,
                'timestamp' => $timestamp,
                'getMessage' => function () use ($tool, $params, $result) {
                    $paramStr = is_array($params) ? implode(' ', $params) : '';

                    return "🔧 {$tool} {$paramStr} → {$result}";
                },
            ];
        }

        return $activities;
    }

    /**
     * Helper method for weighted random selection
     */
    protected static function weightedRandom(array $weights): int
    {
        $totalWeight = array_sum($weights);
        $random = rand(1, $totalWeight);

        $currentWeight = 0;
        for ($i = 0; $i < count($weights); $i++) {
            $currentWeight += $weights[$i];
            if ($random <= $currentWeight) {
                return $i;
            }
        }

        return count($weights) - 1;
    }
}
