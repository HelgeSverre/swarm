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
 * Simple Demo - Basic usage of the minimal TUI library
 */
class SimpleDemo extends TuiApp
{
    protected ListWidget $todoList;

    protected InputField $inputField;

    protected StatusBar $statusBar;

    protected array $todos = [];

    public function __construct()
    {
        parent::__construct();
        $this->setupDemo();
    }

    public function run(): void
    {
        echo Terminal::BRIGHT_GREEN . "📝 Simple Todo Demo\n" . Terminal::RESET;
        echo "Commands: /done <n>, /remove <n>, /clear, /help\n";
        echo 'Press any key to start...';

        // Wait for key press
        $this->terminal->readKey();

        parent::run();

        echo Terminal::BRIGHT_CYAN . "\n✨ Demo completed! Hope you enjoyed the minimal TUI library.\n" . Terminal::RESET;
    }

    protected function setupDemo(): void
    {
        // Create components
        $this->todoList = new ListWidget([], true);
        $this->todoList->setEmptyMessage('No todos yet. Add one below!');

        $this->inputField = new InputField('Type a todo and press Enter...');
        $this->statusBar = StatusBar::create('Todo App', 'Ready');

        // Setup simple layout - main area with input at bottom
        $layout = Layout::grid($this->width, $this->height, ['status_height' => 1]);
        $layout->subdivide('main', ['bottom_height' => 3]);

        $this->setLayout($layout);

        // Add components
        $this->addComponent('status', $this->statusBar, 'status');
        $this->addComponent('todos', new Panel('📝 Todo List', $this->todoList), 'main_top');
        $this->addComponent('input', new Panel('Add Todo', $this->inputField), 'main_bottom');

        // Start with input focused
        $this->setFocus('input');

        // Add some sample todos
        $this->addTodo('Learn the minimal TUI library');
        $this->addTodo('Build an awesome terminal application');
        $this->addTodo('Share it with the world');
        $this->todos[0]['completed'] = true; // Mark first as done
    }

    protected function addTodo(string $text): void
    {
        $this->todos[] = [
            'text' => $text,
            'completed' => false,
            'created' => time(),
        ];
        $this->updateTodoList();
    }

    protected function toggleTodo(int $index): void
    {
        if (isset($this->todos[$index])) {
            $this->todos[$index]['completed'] = ! $this->todos[$index]['completed'];
            $this->updateTodoList();
        }
    }

    protected function removeTodo(int $index): void
    {
        if (isset($this->todos[$index])) {
            array_splice($this->todos, $index, 1);
            $this->updateTodoList();
        }
    }

    protected function updateTodoList(): void
    {
        $formattedTodos = array_map(function ($todo, $index) {
            $icon = $todo['completed']
                ? Terminal::GREEN . '✓' . Terminal::RESET
                : Terminal::DIM . '○' . Terminal::RESET;

            $text = $todo['completed']
                ? Terminal::DIM . Terminal::STRIKETHROUGH . $todo['text'] . Terminal::RESET
                : $todo['text'];

            return "{$icon} {$text}";
        }, $this->todos, array_keys($this->todos));

        $this->todoList->setItems($formattedTodos);

        // Update status
        $total = count($this->todos);
        $completed = count(array_filter($this->todos, fn ($t) => $t['completed']));
        $this->statusBar->status("{$completed}/{$total} completed");
    }

    protected function onCommand(string $command): void
    {
        $command = trim($command);
        if (empty($command)) {
            return;
        }

        // Check for special commands
        if (str_starts_with($command, '/')) {
            $this->handleSpecialCommand(mb_substr($command, 1));
        } else {
            // Regular todo
            $this->addTodo($command);
            $this->statusBar->status('Todo added!');
        }

        $this->inputField->clear();
        $this->redraw();
    }

    protected function handleSpecialCommand(string $cmd): void
    {
        $parts = explode(' ', $cmd, 2);
        $action = $parts[0];
        $arg = $parts[1] ?? '';

        switch ($action) {
            case 'done':
                if (is_numeric($arg)) {
                    $this->toggleTodo((int) $arg - 1); // 1-based to 0-based
                    $this->statusBar->status('Todo toggled!');
                } else {
                    $this->statusBar->status('Usage: /done <number>');
                }
                break;
            case 'remove':
            case 'rm':
                if (is_numeric($arg)) {
                    $this->removeTodo((int) $arg - 1);
                    $this->statusBar->status('Todo removed!');
                } else {
                    $this->statusBar->status('Usage: /remove <number>');
                }
                break;
            case 'clear':
                $this->todos = [];
                $this->updateTodoList();
                $this->statusBar->status('All todos cleared!');
                break;
            case 'help':
                $this->showHelp();
                break;
            default:
                $this->statusBar->status("Unknown command: /{$action}. Try /help");
        }
    }

    protected function showHelp(): void
    {
        $helpTodos = [
            'Commands:',
            '  /done <number>   - Toggle todo completion',
            '  /remove <number> - Remove a todo',
            '  /clear           - Clear all todos',
            '  /help            - Show this help',
            '',
            'Navigation:',
            '  Tab              - Switch between input and list',
            '  Up/Down, j/k     - Navigate list',
            '  Enter            - Select item in list',
            '  Alt+Q            - Quit application',
        ];

        // Temporarily show help
        $originalTodos = $this->todoList->getItems();
        $this->todoList->setItems($helpTodos);
        $this->statusBar->status('Showing help - any input will return to todos');
        $this->redraw();

        // Wait for any key, then restore
        $this->terminal->readKey();
        $this->todoList->setItems($originalTodos);
        $this->updateTodoList();
    }
}

// Run the demo
if (basename($_SERVER['argv'][0] ?? '') === 'simple-demo.php') {
    $demo = new SimpleDemo;
    $demo->run();
}
