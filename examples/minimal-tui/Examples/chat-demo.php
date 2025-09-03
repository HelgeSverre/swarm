<?php

declare(strict_types=1);

// Autoloader for minimal-tui classes
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'MinimalTui\\')) {
        $file = __DIR__ . '/../' . str_replace(['MinimalTui\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

use MinimalTui\Components\InputField;
use MinimalTui\Components\ListWidget;
use MinimalTui\Components\Panel;
use MinimalTui\Components\StatusBar;
use MinimalTui\Core\Layout;
use MinimalTui\Core\Terminal;
use MinimalTui\Core\TuiApp;

/**
 * Chat Demo - Replicates FullTerminalUI functionality with minimal code
 */
class ChatDemo extends TuiApp
{
    protected ListWidget $messagesList;

    protected ListWidget $tasksList;

    protected ListWidget $contextList;

    protected InputField $inputField;

    protected StatusBar $statusBar;

    protected array $messages = [];

    protected array $tasks = [];

    protected array $contextItems = [];

    protected string $currentStatus = 'Ready';

    public function __construct()
    {
        parent::__construct();
        $this->setupComponents();
        $this->setupLayout();
        $this->loadSampleData();
    }

    public function run(): void
    {
        echo Terminal::BRIGHT_GREEN . "🚀 Starting Chat Demo (Alt+Q to quit, Tab to switch focus)\n" . Terminal::RESET;
        echo 'Press any key to continue...';
        $this->terminal->readKey();

        parent::run();

        echo Terminal::BRIGHT_CYAN . "\n👋 Thanks for trying the minimal TUI library!\n" . Terminal::RESET;
    }

    protected function setupComponents(): void
    {
        // Message history list
        $this->messagesList = new ListWidget([], false);
        $this->messagesList->setEmptyMessage('No messages yet...');

        // Tasks list
        $this->tasksList = new ListWidget([], true);
        $this->tasksList->setEmptyMessage('No tasks');

        // Context list
        $this->contextList = new ListWidget([], false);
        $this->contextList->setEmptyMessage('No context');

        // Input field
        $this->inputField = new InputField('Enter your message...');

        // Status bar
        $this->statusBar = StatusBar::create('Swarm', $this->currentStatus);
    }

    protected function setupLayout(): void
    {
        // Create layout: status bar + main area with sidebar
        $layout = Layout::grid($this->width, $this->height, [
            'sidebar_width' => 30,
            'status_height' => 1,
        ]);

        // Subdivide main area for messages and input
        $layout->subdivide('main', ['bottom_height' => 3]);

        // Subdivide sidebar for tasks and context
        $layout->subdivide('sidebar', ['bottom_height' => 15]);

        $this->setLayout($layout);

        // Add components to layout areas
        $this->addComponent('status', $this->statusBar, 'status');
        $this->addComponent('messages', new Panel('Chat History', $this->messagesList), 'main_top');
        $this->addComponent('input', new Panel('Input', $this->inputField), 'main_bottom');
        $this->addComponent('tasks', new Panel('Tasks', $this->tasksList), 'sidebar_top');
        $this->addComponent('context', new Panel('Context', $this->contextList), 'sidebar_bottom');

        // Set initial focus to input
        $this->setFocus('input');
    }

    protected function loadSampleData(): void
    {
        // Sample messages
        $this->messages = [
            ['type' => 'user', 'content' => 'Hello!', 'time' => time() - 300],
            ['type' => 'assistant', 'content' => 'Hi there! How can I help you today?', 'time' => time() - 280],
            ['type' => 'user', 'content' => 'Can you help me with my project?', 'time' => time() - 260],
            ['type' => 'assistant', 'content' => 'Of course! What kind of project are you working on?', 'time' => time() - 240],
            ['type' => 'system', 'content' => 'Connection established', 'time' => time() - 220],
        ];

        // Sample tasks
        $this->tasks = [
            ['description' => 'Analyze codebase', 'status' => 'completed'],
            ['description' => 'Fix bug in authentication', 'status' => 'running'],
            ['description' => 'Update documentation', 'status' => 'pending'],
            ['description' => 'Deploy to staging', 'status' => 'pending'],
        ];

        // Sample context
        $this->contextItems = [
            'Working directory: /home/user/project',
            'Language: PHP 8.3',
            'Framework: Laravel',
            'Database: MySQL',
            'Environment: Development',
        ];

        $this->updateComponents();
    }

    protected function updateComponents(): void
    {
        // Format messages for display
        $formattedMessages = array_map(function ($msg) {
            $time = date('H:i:s', $msg['time']);
            $prefix = match ($msg['type']) {
                'user' => Terminal::BLUE . '[$]' . Terminal::RESET,
                'assistant' => Terminal::GREEN . '[●]' . Terminal::RESET,
                'system' => Terminal::YELLOW . '[!]' . Terminal::RESET,
                default => '[?]'
            };

            return "[{$time}] {$prefix} {$msg['content']}";
        }, $this->messages);

        $this->messagesList->setItems($formattedMessages);

        // Format tasks
        $formattedTasks = array_map(function ($task) {
            $icon = match ($task['status']) {
                'completed' => Terminal::GREEN . '✓' . Terminal::RESET,
                'running' => Terminal::YELLOW . '▶' . Terminal::RESET,
                'pending' => Terminal::DIM . '○' . Terminal::RESET,
                default => ' '
            };

            return "{$icon} {$task['description']}";
        }, $this->tasks);

        $this->tasksList->setItems($formattedTasks);
        $this->contextList->setItems($this->contextItems);

        // Update status bar
        $this->statusBar->status($this->currentStatus);
        $runningTasks = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'running'));
        if ($runningTasks > 0) {
            $this->statusBar->setSection('tasks', "{$runningTasks} running", Terminal::CYAN);
        }
    }

    protected function onCommand(string $command): void
    {
        if (empty(trim($command))) {
            return;
        }

        // Add user message
        $this->messages[] = [
            'type' => 'user',
            'content' => $command,
            'time' => time(),
        ];

        // Clear input
        $this->inputField->clear();

        // Simulate processing
        $this->currentStatus = 'Processing...';
        $this->updateComponents();
        $this->redraw();

        // Simulate AI response after a delay
        $this->simulateResponse($command);
    }

    protected function simulateResponse(string $userInput): void
    {
        // Simple response simulation
        $responses = [
            "I understand you're asking about: \"{$userInput}\"",
            "That's an interesting question. Let me think about that...",
            'Based on your input, I can help you with that.',
            "I'll need to analyze this further. Give me a moment.",
        ];

        $response = $responses[array_rand($responses)];

        // Add assistant response
        $this->messages[] = [
            'type' => 'assistant',
            'content' => $response,
            'time' => time(),
        ];

        // Maybe add a task
        if (rand(0, 2) === 0) {
            $this->tasks[] = [
                'description' => 'Process: ' . mb_substr($userInput, 0, 30) . '...',
                'status' => 'pending',
            ];
        }

        $this->currentStatus = 'Ready';
        $this->updateComponents();
        $this->redraw();
    }
}

// Run the demo
if (basename($_SERVER['argv'][0] ?? '') === 'chat-demo.php') {
    $demo = new ChatDemo;
    $demo->run();
}
