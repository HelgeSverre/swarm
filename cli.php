<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use HelgeSverre\Swarm\Exceptions\ToolNotFoundException;
use HelgeSverre\Swarm\TUIRenderer;
use OpenAI;
use Exception;
use InvalidArgumentException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;


/**
 * CodeSwarm CLI: AI Coding Assistant with Tool Routing
 *
 * Agents dispatch to "tool routes" instead of HTTP routes
 * Each tool call creates an audit trail for visualization
 */

// =============================================================================
// CORE: Tool Router (like HTTP Router but for CLI tools)
// =============================================================================

class ToolRouter
{
    protected $tools = [];
    protected $executionLog = [];

    public function registerTool(string $name, callable $handler): self
    {
        $this->tools[$name] = $handler;
        return $this;
    }

    public function dispatch(string $tool, array $params): ToolResponse
    {
        $startTime = microtime(true);
        $logId = uniqid();

        if (!isset($this->tools[$tool])) {
            throw new ToolNotFoundException("Tool '$tool' not found");
        }

        // Log the execution start
        $this->logExecution($logId, $tool, $params, 'started');

        try {
            $response = $this->tools[$tool]($params);
            $this->logExecution($logId, $tool, $params, 'completed', $response, microtime(true) - $startTime);
            return $response;
        } catch (Exception $e) {
            $this->logExecution($logId, $tool, $params, 'failed', null, microtime(true) - $startTime, $e);
            throw $e;
        }
    }

    public function getExecutionLog(): array
    {
        return $this->executionLog;
    }

    protected function logExecution(string $id, string $tool, array $params, string $status, $response = null, float $duration = 0, ?Exception $error = null): void
    {
        $this->executionLog[] = [
            'id' => $id,
            'tool' => $tool,
            'params' => $params,
            'status' => $status,
            'response' => $response,
            'duration' => $duration,
            'error' => $error?->getMessage(),
            'timestamp' => time()
        ];
    }
}

// =============================================================================
// TASK MANAGEMENT SYSTEM
// =============================================================================

class TaskManager
{
    protected $tasks = [];
    public $currentTask = null;

    public function addTasks(array $extractedTasks): void
    {
        foreach ($extractedTasks as $task) {
            $this->tasks[] = [
                'id' => uniqid(),
                'description' => $task['description'],
                'status' => 'pending',
                'plan' => null,
                'steps' => [],
                'created_at' => time()
            ];
        }
    }

    public function planTask(string $taskId, string $plan, array $steps): void
    {
        foreach ($this->tasks as &$task) {
            if ($task['id'] === $taskId) {
                $task['plan'] = $plan;
                $task['steps'] = $steps;
                $task['status'] = 'planned';
                break;
            }
        }
    }

    public function getNextTask(): ?array
    {
        foreach ($this->tasks as &$task) {
            if ($task['status'] === 'planned') {
                $task['status'] = 'executing';
                $this->currentTask = $task;
                return $task;
            }
        }
        return null;
    }

    public function completeCurrentTask(): void
    {
        if ($this->currentTask) {
            foreach ($this->tasks as &$task) {
                if ($task['id'] === $this->currentTask['id']) {
                    $task['status'] = 'completed';
                    break;
                }
            }
            $this->currentTask = null;
        }
    }

    public function getTasks(): array
    {
        return $this->tasks;
    }
}

// =============================================================================
// CORE TOOLS (File ops, bash, search, etc.)
// =============================================================================

class CoreTools
{
    public static function register(ToolRouter $router): void
    {

        // File reading
        $router->registerTool('read_file', function ($params) {
            $path = $params['path'] ?? throw new InvalidArgumentException('path required');

            if (!file_exists($path)) {
                return ToolResponse::error("File not found: $path");
            }

            $content = file_get_contents($path);
            return ToolResponse::success([
                'path' => $path,
                'content' => $content,
                'size' => strlen($content),
                'lines' => substr_count($content, "\n") + 1
            ]);
        });

        // File writing
        $router->registerTool('write_file', function ($params) {
            $path = $params['path'] ?? throw new InvalidArgumentException('path required');
            $content = $params['content'] ?? throw new InvalidArgumentException('content required');
            $backup = $params['backup'] ?? true;

            // Backup existing file if requested
            if ($backup && file_exists($path)) {
                copy($path, $path . '.backup.' . time());
            }

            $bytes = file_put_contents($path, $content);

            return ToolResponse::success([
                'path' => $path,
                'bytes_written' => $bytes,
                'backup_created' => $backup && file_exists($path . '.backup.' . time())
            ]);
        });

        // File/directory search with glob patterns
        $router->registerTool('find_files', function ($params) {
            $pattern = $params['pattern'] ?? '*';
            $directory = $params['directory'] ?? '.';
            $recursive = $params['recursive'] ?? true;

            $flags = $recursive ? GLOB_BRACE : 0;
            $searchPattern = rtrim($directory, '/') . '/' . $pattern;

            if ($recursive) {
                // For recursive search, we need to implement it manually
                $files = [];
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if (fnmatch($pattern, $file->getFilename())) {
                        $files[] = $file->getPathname();
                    }
                }
            } else {
                $files = glob($searchPattern, $flags);
            }

            return ToolResponse::success([
                'pattern' => $pattern,
                'directory' => $directory,
                'files' => $files ?: [],
                'count' => count($files ?: [])
            ]);
        });

        // Content search (grep-like)
        $router->registerTool('search_content', function ($params) use ($router) {
            $search = $params['search'] ?? throw new InvalidArgumentException('search required');
            $pattern = $params['pattern'] ?? '*';
            $directory = $params['directory'] ?? '.';
            $case_sensitive = $params['case_sensitive'] ?? false;

            $results = [];
            $flags = $case_sensitive ? 0 : PREG_CASE_INSENSITIVE;

            // First find files matching pattern
            $findResponse = $router->dispatch('find_files', ['pattern' => $pattern, 'directory' => $directory]);
            $files = $findResponse->getData()['files'];

            foreach ($files as $file) {
                if (is_file($file)) {
                    $content = file_get_contents($file);
                    $lines = explode("\n", $content);

                    foreach ($lines as $lineNum => $line) {
                        if (preg_match("/$search/", $line, $matches, $flags)) {
                            $results[] = [
                                'file' => $file,
                                'line' => $lineNum + 1,
                                'content' => trim($line),
                                'match' => $matches[0] ?? $search
                            ];
                        }
                    }
                }
            }

            return ToolResponse::success([
                'search' => $search,
                'pattern' => $pattern,
                'results' => $results,
                'count' => count($results)
            ]);
        });

        // Execute bash commands
        $router->registerTool('bash', function ($params) {
            $command = $params['command'] ?? throw new InvalidArgumentException('command required');
            $timeout = $params['timeout'] ?? 30;
            $directory = $params['directory'] ?? getcwd();

            // Change to specified directory
            $oldCwd = getcwd();
            chdir($directory);

            $descriptorspec = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ];

            $process = proc_open($command, $descriptorspec, $pipes);

            if (is_resource($process)) {
                // Close stdin
                fclose($pipes[0]);

                // Read stdout and stderr
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);

                fclose($pipes[1]);
                fclose($pipes[2]);

                $returnCode = proc_close($process);

                // Restore original directory
                chdir($oldCwd);

                return ToolResponse::success([
                    'command' => $command,
                    'directory' => $directory,
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'return_code' => $returnCode,
                    'success' => $returnCode === 0
                ]);
            }

            chdir($oldCwd);
            return ToolResponse::error("Failed to execute command: $command");
        });
    }
}

// =============================================================================
// AI AGENT WITH TOOL ACCESS
// =============================================================================

class CodingAgent
{
    protected $toolRouter;
    protected $taskManager;
    protected $conversationHistory = [];
    protected OpenAI\Client $llmClient;

    public function __construct(ToolRouter $toolRouter, TaskManager $taskManager, $llmClient)
    {
        $this->toolRouter = $toolRouter;
        $this->taskManager = $taskManager;
        $this->llmClient = $llmClient;
    }

    public function processRequest(string $userInput): AgentResponse
    {
        $this->addToHistory('user', $userInput);

        // First, try to extract tasks from the input
        $tasks = $this->extractTasks($userInput);

        if (!empty($tasks)) {
            $this->taskManager->addTasks($tasks);

            // Plan each task
            foreach ($this->taskManager->getTasks() as $task) {
                if ($task['status'] === 'pending') {
                    $this->planTask($task);
                }
            }

            // Execute tasks one by one
            while ($currentTask = $this->taskManager->getNextTask()) {
                $this->executeTask($currentTask);
                $this->taskManager->completeCurrentTask();
            }

            return AgentResponse::success("All tasks completed successfully!");
        } else {
            // Handle as a regular conversation
            return $this->handleConversation($userInput);
        }
    }

    protected function extractTasks(string $input): array
    {
        // Use AI to extract structured tasks from natural language
        $prompt = "Extract coding tasks from this input. Return as JSON array with 'description' field for each task:\n\n$input";

        $response = $this->callOpenAI($prompt);

        try {
            return json_decode($response, true) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    protected function planTask(array $task): void
    {
        $context = $this->buildContext();
        $prompt = "Plan how to execute this coding task:\n\n{$task['description']}\n\nContext:\n$context\n\nReturn a plan and list of steps.";

        $planResponse = $this->callOpenAI($prompt);

        // Extract plan and steps (simplified - would need better parsing)
        $this->taskManager->planTask($task['id'], $planResponse, []);
    }

    protected function executeTask(array $task): void
    {
        $maxIterations = 10;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $context = $this->buildContext();
            $toolLog = $this->getRecentToolLog();

            $prompt = "Execute this task step by step:\n\n{$task['description']}\n\nPlan:\n{$task['plan']}\n\nContext:\n$context\n\nRecent tool results:\n$toolLog\n\nWhat tool should I use next? Return JSON with 'tool', 'params', and 'reasoning'.";

            $response = $this->callOpenAI($prompt);

            try {
                $action = json_decode($response, true);

                if (!$action || $action['tool'] === 'done') {
                    break; // Task complete
                }

                // Execute the tool
                $result = $this->toolRouter->dispatch($action['tool'], $action['params']);
                $this->addToHistory('tool', json_encode($action) . "\nResult: " . json_encode($result->toArray()));

            } catch (Exception $e) {
                $this->addToHistory('error', $e->getMessage());
                break;
            }

            $iteration++;
        }
    }

    protected function buildContext(): string
    {
        // Build current project context
        $context = "Current directory: " . getcwd() . "\n";
        $context .= "Recent conversation:\n" . $this->getRecentHistory() . "\n";
        return $context;
    }

    protected function getRecentToolLog(): string
    {
        $log = $this->toolRouter->getExecutionLog();
        $recent = array_slice($log, -5); // Last 5 tool calls
        return json_encode($recent, JSON_PRETTY_PRINT);
    }

    protected function getRecentHistory(): string
    {
        $recent = array_slice($this->conversationHistory, -10);
        return implode("\n", array_map(function ($msg) {
            return "{$msg['role']}: {$msg['content']}";
        }, $recent));
    }

    protected function addToHistory(string $role, string $content): void
    {
        $this->conversationHistory[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time()
        ];
    }

    protected function callOpenAI(string $prompt): string
    {
        try {
            $result = $this->llmClient->chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful coding assistant. Always return valid JSON when asked for JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
            ]);

            return $result->choices[0]->message->content;
        } catch (Exception $e) {
            throw new Exception('OpenAI API error: ' . $e->getMessage());
        }
    }

    public function getStatus(): array
    {
        return [
            'tasks' => $this->taskManager->getTasks(),
            'current_task' => $this->taskManager->currentTask ?? null
        ];
    }

    protected function handleConversation(string $userInput): AgentResponse
    {
        $prompt = "User: $userInput\n\nProvide a helpful response as a coding assistant.";
        $response = $this->callOpenAI($prompt);

        return AgentResponse::success($response);
    }
}

// =============================================================================
// CLI INTERFACE WITH TUI
// =============================================================================

class CodeSwarmCLI
{
    protected $agent;
    protected $tui;

    public function __construct()
    {
        // Load environment variables
        if (file_exists(__DIR__ . '/.env')) {
            $dotenv = Dotenv::createImmutable(__DIR__);

            $dotenv->load();
        }

        // Get API key from environment
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
        if (!$apiKey) {
            throw new Exception("OpenAI API key not found. Please set OPENAI_API_KEY environment variable or create a .env file.");
        }

        $toolRouter = new ToolRouter();
        CoreTools::register($toolRouter);

        $taskManager = new TaskManager();
        $llmClient = OpenAI::client($apiKey);

        $this->agent = new CodingAgent($toolRouter, $taskManager, $llmClient);
        $this->tui = new TUIRenderer();
    }

    public function run(): void
    {
        $this->tui->showWelcome();

        while (true) {
            $this->tui->refresh($this->agent->getStatus());

            $input = $this->tui->prompt("🤖 What would you like me to help you with?");

            if ($input === 'exit' || $input === 'quit') {
                break;
            }

            try {
                $response = $this->agent->processRequest($input);
                $this->tui->displayResponse($response);
            } catch (Exception $e) {
                $this->tui->displayError($e->getMessage());
            }
        }
    }
}

// =============================================================================
// RESPONSE CLASSES
// =============================================================================

class ToolResponse
{
    protected $success;
    protected $data;
    protected $error;

    public static function success(array $data): self
    {
        $instance = new self();
        $instance->success = true;
        $instance->data = $data;
        return $instance;
    }

    public static function error(string $error): self
    {
        $instance = new self();
        $instance->success = false;
        $instance->error = $error;
        return $instance;
    }

    public function getData(): array
    {
        return $this->data ?? [];
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error
        ];
    }
}

class AgentResponse
{
    protected $message;
    protected $success;

    public static function success(string $message): self
    {
        $instance = new self();
        $instance->message = $message;
        $instance->success = true;
        return $instance;
    }

    public function getMessage(): string
    {
        return $this->message ?? '';
    }

    public function isSuccess(): bool
    {
        return $this->success ?? false;
    }
}

// =============================================================================
// USAGE EXAMPLE
// =============================================================================

// Create and run the CLI
$cli = new CodeSwarmCLI();

// Example conversation:
// User: "Create a new Laravel migration for users table, run it, and then create a User model"
//
// The agent would:
// 1. Extract 3 tasks: create migration, run migration, create model
// 2. Plan each task with specific steps
// 3. Execute using tools:
//    - bash: "php artisan make:migration create_users_table"
//    - write_file: Add columns to migration file
//    - bash: "php artisan migrate"
//    - bash: "php artisan make:model User"
// 4. Update TUI with progress throughout

$cli->run();