#!/usr/bin/env php
<?php

/**
 * Claude Code Chat Demo - Sleek Minimal Interface
 * A sophisticated, minimalist chat interface showcasing Claude's capabilities
 * with smooth animations, typewriter effects, and premium polish
 */

// Terminal control sequences
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";
const ENTER_ALT_SCREEN = "\033[?1049h";
const EXIT_ALT_SCREEN = "\033[?1049l";
const CLEAR_SCREEN = "\033[2J";
const HOME = "\033[H";

// Premium color palette (subtle and sophisticated)
const C_TEXT = "\033[38;5;250m";        // Soft white text
const C_USER = "\033[38;5;117m";        // Subtle blue for user
const C_CLAUDE = "\033[38;5;121m";      // Soft green for Claude
const C_ICON = "\033[38;5;214m";        // Warm accent for icons
const C_SUCCESS = "\033[38;5;78m";      // Success green
const C_ACTIVE = "\033[38;5;221m";      // Activity yellow
const C_PENDING = "\033[38;5;240m";     // Muted gray
const C_MUTED = "\033[38;5;245m";       // Subtle gray
const C_SEPARATOR = "\033[38;5;238m";   // Gentle separator

function moveTo(int $row, int $col): string
{
    return "\033[{$row};{$col}H";
}

function formatTime(): string
{
    return date('H:i:s');
}

class ClaudeCodeChatDemo
{
    private int $width = 120;

    private int $height = 40;

    private int $sidebarWidth = 35;

    private int $frame = 0;

    private float $startTime;

    // Message system
    private array $messages = [];

    private array $currentTasks = [];

    // Animation state
    private array $typewriterState = [];

    private array $spinnerState = [];

    private string $currentSpinner = '';

    private bool $showingThinking = false;

    // Demo control
    private int $demoStep = 0;

    private float $lastStepTime = 0;

    private array $demoScenarios = [];

    private string $currentScenario = 'welcome';

    private bool $scenarioComplete = false;

    // Activity stream (unified chat + tools + tasks)
    private array $activities = [];

    private int $scrollOffset = 0;

    // Input handling
    private string $inputBuffer = '';

    private int $cursorPosition = 0;

    private bool $inputMode = false;

    // Spinner characters for premium feel
    private array $spinnerChars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->updateTerminalSize();
        $this->initializeScenarios();
        $this->registerSignalHandlers();
    }

    public function run(): void
    {
        $this->initializeTerminal();

        try {
            // Welcome sequence
            $this->startWelcomeSequence();

            // Main render loop
            while (true) {
                $this->update();
                $this->render();
                $this->handleInput();

                usleep(33333); // 30 FPS
                $this->frame++;
            }
        } finally {
            $this->cleanup();
        }
    }

    public function handleSignal(int $signal): void
    {
        $this->cleanup();
        exit(0);
    }

    private function initializeTerminal(): void
    {
        echo ENTER_ALT_SCREEN;
        echo HIDE_CURSOR;

        // Set raw mode for input
        system('stty -echo -icanon -isig min 0 time 1 2>/dev/null');
        stream_set_blocking(STDIN, false);
    }

    private function cleanup(): void
    {
        echo SHOW_CURSOR;
        echo EXIT_ALT_SCREEN;

        // Restore terminal
        system('stty sane 2>/dev/null');
        stream_set_blocking(STDIN, true);
    }

    private function registerSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        }
    }

    private function updateTerminalSize(): void
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;
    }

    private function render(): void
    {
        echo CLEAR_SCREEN . HOME;

        // Sleek header (minimal)
        $this->renderHeader();

        // Main activity stream (left side)
        $this->renderActivityStream();

        // Show thinking indicator if active
        if ($this->showingThinking) {
            $this->renderThinkingIndicator();
        }

        // Sidebar for summary (right side)
        $this->renderSidebar();

        // Minimal footer
        $this->renderFooter();
    }

    private function renderHeader(): void
    {
        echo moveTo(1, 1);
        echo C_ICON . '🤖 ' . RESET . C_TEXT . BOLD . 'Claude Code' . RESET;

        // Time on the right
        $time = formatTime();
        $timeCol = $this->width - mb_strlen($time);
        echo moveTo(1, $timeCol) . C_MUTED . $time . RESET;

        echo "\n\n";
    }

    private function renderActivityStream(): void
    {
        $startRow = 4;
        $maxRows = $this->height - 7; // Leave room for header and footer
        $mainAreaWidth = $this->width - $this->sidebarWidth - 6;

        // Calculate total content height needed
        $totalContentHeight = $this->calculateTotalContentHeight($mainAreaWidth);

        // Auto-scroll to follow newest content
        $this->updateScrollOffset($maxRows, $totalContentHeight);

        // Render visible activities
        $currentRow = $startRow;
        $renderedHeight = 0;
        $activityStart = 0;

        // Skip activities that are scrolled off screen
        foreach ($this->activities as $index => $activity) {
            $activityHeight = $this->calculateActivityHeight($activity, $mainAreaWidth) + 1;

            if ($activityStart + $activityHeight <= $this->scrollOffset) {
                $activityStart += $activityHeight;

                continue;
            }

            if ($renderedHeight >= $maxRows) {
                break;
            }

            // Calculate partial rendering if activity is partially visible
            $skipLines = max(0, $this->scrollOffset - $activityStart);
            $availableLines = min($activityHeight - $skipLines, $maxRows - $renderedHeight);

            $activityRows = $this->renderActivity($activity, $currentRow, $mainAreaWidth, $index, $skipLines, $availableLines);

            $currentRow += $activityRows;
            $renderedHeight += $activityRows;
            $activityStart += $activityHeight;

            // Only add minimal spacing between different activity types, not after every activity
            if ($activityRows > 0 && $this->needsSpacing($activity, $this->activities[$index + 1] ?? null)) {
                $currentRow++;
                $renderedHeight++;
            }
        }
    }

    private function needsSpacing(array $currentActivity, ?array $nextActivity): bool
    {
        // No spacing needed if no next activity
        if (! $nextActivity) {
            return false;
        }

        // Only add spacing when transitioning from tools back to messages
        if (in_array($currentActivity['type'], ['tool_start', 'tool_output', 'tool_complete']) &&
            $nextActivity['type'] === 'message') {
            return true;
        }

        // No spacing between messages or consecutive tool activities - keep it compact
        return false;
    }

    private function calculateTotalContentHeight(int $width): int
    {
        $totalHeight = 0;
        foreach ($this->activities as $index => $activity) {
            $activityHeight = $this->calculateActivityHeight($activity, $width);
            $totalHeight += $activityHeight;

            // Only add spacing where actually needed
            if ($this->needsSpacing($activity, $this->activities[$index + 1] ?? null)) {
                $totalHeight += 1;
            }
        }

        return $totalHeight;
    }

    private function calculateActivityHeight(array $activity, int $width): int
    {
        switch ($activity['type']) {
            case 'message':
                $lines = $this->wrapText($activity['content'] ?? '', $width - 10);

                return count($lines);
            case 'tool_start':
            case 'tool_complete':
                return 1;
            case 'tool_output':
                return count($activity['output'] ?? []);
            case 'task_list':
                // Task lists are not rendered inline anymore, only in sidebar
                return 0;
            default:
                return 1;
        }
    }

    private function updateScrollOffset(int $maxRows, int $totalHeight): void
    {
        if ($totalHeight <= $maxRows) {
            // Content fits in viewport, no scrolling needed
            $this->scrollOffset = 0;
        } else {
            // Auto-scroll to show the newest content (bottom)
            $this->scrollOffset = $totalHeight - $maxRows;

            // Add some padding to show thinking indicator
            if ($this->showingThinking) {
                $this->scrollOffset = max(0, $this->scrollOffset - 2);
            }
        }
    }

    private function renderActivity(array $activity, int $startRow, int $width, int $index, int $skipLines = 0, int $maxLines = PHP_INT_MAX): int
    {
        $row = $startRow;

        switch ($activity['type']) {
            case 'message':
                return $this->renderMessageActivity($activity, $row, $width, $index, $skipLines, $maxLines);
            case 'tool_start':
                return $this->renderToolStart($activity, $row, $width, $skipLines, $maxLines);
            case 'tool_output':
                return $this->renderToolOutput($activity, $row, $width, $skipLines, $maxLines);
            case 'tool_complete':
                return $this->renderToolComplete($activity, $row, $width, $skipLines, $maxLines);
            case 'task_list':
                // Don't render task lists inline - they're shown in sidebar only
                return 0;
            default:
                return 0;
        }
    }

    private function renderMessageActivity(array $activity, int $row, int $width, int $index, int $skipLines = 0, int $maxLines = PHP_INT_MAX): int
    {
        $content = $this->getActivityContent($activity, $index);
        $lines = $this->wrapText($content, $width - 10);
        $rowsUsed = 0;
        $renderedLines = 0;

        foreach ($lines as $lineIndex => $line) {
            // Skip lines that are scrolled off screen
            if ($lineIndex < $skipLines) {
                continue;
            }

            // Stop if we've reached the maximum lines to render
            if ($renderedLines >= $maxLines) {
                break;
            }

            echo moveTo($row + $renderedLines, 3);

            if ($lineIndex === 0) {
                $prefix = $activity['speaker'] === 'user' ? 'You: ' : 'Claude: ';
                $color = $activity['speaker'] === 'user' ? C_USER : C_CLAUDE;
                echo $color . $prefix . RESET . C_TEXT . $line . RESET;
            } else {
                $indent = $activity['speaker'] === 'user' ? '      ' : '        ';
                echo $indent . C_TEXT . $line . RESET;
            }

            $renderedLines++;
        }

        return $renderedLines;
    }

    private function renderToolStart(array $activity, int $row, int $width, int $skipLines = 0, int $maxLines = PHP_INT_MAX): int
    {
        if ($skipLines > 0 || $maxLines < 1) {
            return 0;
        }

        echo moveTo($row, 5);

        $icon = $this->getToolIcon($activity['tool']);
        echo C_ICON . $icon . RESET . ' ';
        echo C_ACTIVE . 'Using ' . $activity['tool'] . RESET;

        if (! empty($activity['file'])) {
            echo C_TEXT . ' → ' . $activity['file'] . RESET;
        }

        return 1;
    }

    private function renderToolOutput(array $activity, int $row, int $width, int $skipLines = 0, int $maxLines = PHP_INT_MAX): int
    {
        $renderedLines = 0;

        foreach ($activity['output'] as $lineIndex => $line) {
            // Skip lines that are scrolled off screen
            if ($lineIndex < $skipLines) {
                continue;
            }

            // Stop if we've reached the maximum lines to render
            if ($renderedLines >= $maxLines) {
                break;
            }

            echo moveTo($row + $renderedLines, 7);
            echo C_MUTED . '│ ' . RESET . C_TEXT . $line . RESET;
            $renderedLines++;
        }

        return $renderedLines;
    }

    private function renderToolComplete(array $activity, int $row, int $width, int $skipLines = 0, int $maxLines = PHP_INT_MAX): int
    {
        if ($skipLines > 0 || $maxLines < 1) {
            return 0;
        }

        echo moveTo($row, 5);

        $icon = $this->getToolIcon($activity['tool']);
        echo C_ICON . $icon . RESET . ' ';
        echo C_SUCCESS . '✓ ' . $activity['tool'] . ' completed' . RESET;
        echo C_TEXT . ' - ' . $activity['result'] . RESET;

        return 1;
    }

    private function renderTaskList(array $activity, int $row, int $width, int $skipLines = 0, int $maxLines = PHP_INT_MAX): int
    {
        $renderedLines = 0;

        foreach ($activity['tasks'] as $taskIndex => $task) {
            // Skip lines that are scrolled off screen
            if ($taskIndex < $skipLines) {
                continue;
            }

            // Stop if we've reached the maximum lines to render
            if ($renderedLines >= $maxLines) {
                break;
            }

            echo moveTo($row + $renderedLines, 7);

            $icon = $this->getTaskIcon($task['status']);
            $color = $this->getTaskColor($task['status']);

            echo $color . $icon . RESET . ' ' . C_TEXT . $task['description'] . RESET;
            $renderedLines++;
        }

        return $renderedLines;
    }

    private function renderSidebar(): void
    {
        $sidebarStart = $this->width - $this->sidebarWidth + 1;

        // Draw vertical separator
        for ($row = 3; $row <= $this->height - 3; $row++) {
            echo moveTo($row, $sidebarStart - 2);
            echo C_SEPARATOR . '│' . RESET;
        }

        // Task list header
        echo moveTo(4, $sidebarStart);
        echo C_ICON . '📋 ' . RESET . C_TEXT . BOLD . 'Tasks' . RESET;

        $row = 6;
        $hasAnyTasks = false;

        // Find and render all tasks from the activity stream
        foreach ($this->activities as $activity) {
            if ($activity['type'] === 'task_list' && ! empty($activity['tasks'])) {
                $hasAnyTasks = true;

                foreach ($activity['tasks'] as $task) {
                    // Check if we have room (leave space for bottom margin)
                    if ($row >= $this->height - 4) {
                        break 2; // Break out of both loops
                    }

                    echo moveTo($row, $sidebarStart);

                    $icon = $this->getTaskIcon($task['status']);
                    $color = $this->getTaskColor($task['status']);

                    // Render with beautiful progress indicator
                    echo $color . $icon . RESET . ' ';

                    // Truncate task description to fit sidebar
                    $maxDescLength = $this->sidebarWidth - 4;
                    $description = $task['description'];
                    if ($this->graphemeLength($description) > $maxDescLength) {
                        $description = $this->graphemeSubstr($description, 0, $maxDescLength - 3) . '...';
                    }

                    echo C_TEXT . $description . RESET;
                    $row++;
                }

                $row++; // Add spacing between task groups
            }
        }

        // Show minimal message if no tasks yet
        if (! $hasAnyTasks) {
            echo moveTo($row, $sidebarStart);
            echo C_MUTED . 'No active tasks' . RESET;
        }
    }

    private function renderThinkingIndicator(): void
    {
        $row = $this->height - 5;
        echo moveTo($row, 3);

        $spinner = $this->spinnerChars[$this->frame % count($this->spinnerChars)];
        echo C_ACTIVE . $spinner . RESET . ' ' . C_TEXT . $this->currentSpinner . RESET;
    }

    private function renderFooter(): void
    {
        $footerRow = $this->height - 2;
        $mainAreaWidth = $this->width - $this->sidebarWidth - 6;

        // Subtle separator for main area only
        echo moveTo($footerRow - 1, 3);
        echo C_SEPARATOR . str_repeat('─', $mainAreaWidth) . RESET;

        echo moveTo($footerRow, 3);

        if ($this->inputMode) {
            // Show input box with current message and cursor
            $prompt = 'You: ';
            $inputAreaWidth = $mainAreaWidth - mb_strlen($prompt) - 2;

            // Display the input with proper truncation if needed
            $displayText = $this->inputBuffer;
            $displayCursor = $this->cursorPosition;

            // If text is too long, scroll the view
            if ($this->graphemeLength($displayText) > $inputAreaWidth) {
                $startPos = max(0, $displayCursor - $inputAreaWidth + 5);
                $displayText = $this->graphemeSubstr($displayText, $startPos, $inputAreaWidth);
                $displayCursor = min($displayCursor - $startPos, $inputAreaWidth);
            }

            echo C_USER . $prompt . RESET;

            // Render text with cursor
            if (empty($displayText)) {
                echo C_MUTED . '│' . RESET; // Cursor at start
            } else {
                $beforeCursor = $this->graphemeSubstr($displayText, 0, $displayCursor);
                $afterCursor = $this->graphemeSubstr($displayText, $displayCursor);

                echo C_TEXT . $beforeCursor;
                echo C_ACTIVE . '│' . RESET; // Cursor
                echo C_TEXT . $afterCursor . RESET;
            }

            // Controls hint for input mode
            echo moveTo($footerRow, $mainAreaWidth - 25);
            echo C_MUTED . '[Enter] Send  [Tab] Demo  [Q] Quit' . RESET;
        } else {
            // Demo mode
            echo C_MUTED . 'Demo mode - Press [Tab] to type messages' . RESET;

            // Controls hint
            echo moveTo($footerRow, $mainAreaWidth - 15);
            echo C_MUTED . '[Any key] Demo  [Q] Quit' . RESET;
        }
    }

    // Animation and typewriter methods
    private function getActivityContent(array $activity, int $index): string
    {
        if ($activity['type'] !== 'message') {
            return $activity['content'] ?? '';
        }

        if (! isset($this->typewriterState[$index])) {
            return $activity['content'];
        }

        $state = $this->typewriterState[$index];
        if ($state['complete']) {
            return $activity['content'];
        }

        $elapsed = microtime(true) - $state['start_time'];
        $targetChars = (int) ($elapsed * 60); // 60 chars per second
        $visibleChars = min($targetChars, mb_strlen($activity['content']));

        $visible = mb_substr($activity['content'], 0, $visibleChars);

        // Add blinking cursor while typing
        if ($visibleChars < mb_strlen($activity['content'])) {
            if (($this->frame % 15) < 8) {
                $visible .= '▋';
            }
        } else {
            $this->typewriterState[$index]['complete'] = true;
        }

        return $visible;
    }

    private function getTaskIcon(string $status): string
    {
        return match ($status) {
            'completed' => '✓',
            'active' => '▶',
            'pending' => '○',
            default => '○'
        };
    }

    private function getTaskColor(string $status): string
    {
        return match ($status) {
            'completed' => C_SUCCESS,
            'active' => C_ACTIVE,
            'pending' => C_PENDING,
            default => C_PENDING
        };
    }

    private function getToolIcon(string $toolName): string
    {
        return match ($toolName) {
            'ReadFile' => '📄',
            'WriteFile' => '📝',
            'EditFile' => '✏️',
            'Terminal' => '💻',
            'Search' => '🔍',
            'CreateFile' => '📄',
            'RunCommand' => '⚡',
            'TestRunner' => '🧪',
            default => '🔧'
        };
    }

    private function getToolStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => C_SUCCESS,
            'running' => C_ACTIVE,
            'pending' => C_PENDING,
            'error' => "\033[38;5;203m", // Red for errors
            default => C_PENDING
        };
    }

    // Grapheme-aware text handling for proper emoji and multi-byte character support
    private function graphemeLength(string $text): int
    {
        return function_exists('grapheme_strlen') ? grapheme_strlen($text) : mb_strlen($text);
    }

    private function graphemeSubstr(string $text, int $start, ?int $length = null): string
    {
        if (function_exists('grapheme_substr')) {
            return $length !== null ? grapheme_substr($text, $start, $length) : grapheme_substr($text, $start);
        }

        return $length !== null ? mb_substr($text, $start, $length) : mb_substr($text, $start);
    }

    private function wrapText(string $text, int $width): array
    {
        if (empty($text)) {
            return [''];
        }

        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            if (empty($currentLine)) {
                $currentLine = $word;
            } elseif ($this->graphemeLength($currentLine . ' ' . $word) <= $width) {
                $currentLine .= ' ' . $word;
            } else {
                $lines[] = $currentLine;
                $currentLine = $word;
            }
        }

        if (! empty($currentLine)) {
            $lines[] = $currentLine;
        }

        return empty($lines) ? [''] : $lines;
    }

    // Demo scenarios and content
    private function initializeScenarios(): void
    {
        $this->demoScenarios = [
            'welcome' => [
                'steps' => [
                    ['type' => 'activity', 'activity_type' => 'message', 'speaker' => 'claude', 'content' => 'Hi! I\'m Claude, your AI coding assistant. I can help you build applications, fix bugs, and tackle complex development tasks.', 'delay' => 1.0],
                    ['type' => 'activity', 'activity_type' => 'message', 'speaker' => 'claude', 'content' => 'Try asking me something like "Create a login system" or press any key to see a demo!', 'delay' => 2.0],
                ],
            ],
            'login_system' => [
                'steps' => [
                    ['type' => 'activity', 'activity_type' => 'message', 'speaker' => 'user', 'content' => 'Create a login system', 'delay' => 3.0],
                    ['type' => 'thinking', 'message' => 'Analyzing requirements...', 'delay' => 1.5],
                    ['type' => 'activity', 'activity_type' => 'message', 'speaker' => 'claude', 'content' => 'I\'ll build a comprehensive authentication system for you. Let me work through this step by step:', 'delay' => 1.0],
                    ['type' => 'activity', 'activity_type' => 'task_list', 'tasks' => [
                        ['description' => 'Database schema design', 'status' => 'pending'],
                        ['description' => 'User model implementation', 'status' => 'pending'],
                        ['description' => 'Password hashing setup', 'status' => 'pending'],
                        ['description' => 'Session management', 'status' => 'pending'],
                        ['description' => 'Security middleware', 'status' => 'pending'],
                    ], 'delay' => 1.0],
                    ['type' => 'activity', 'activity_type' => 'tool_start', 'tool' => 'CreateFile', 'file' => 'database/migrations/create_users_table.php', 'delay' => 1.5],
                    ['type' => 'thinking', 'message' => 'Generating secure database schema...', 'delay' => 2.0],
                    ['type' => 'activity', 'activity_type' => 'tool_complete', 'tool' => 'CreateFile', 'result' => 'Migration created successfully', 'delay' => 1.0],
                    ['type' => 'activity', 'activity_type' => 'message', 'speaker' => 'claude', 'content' => 'Creating the User model with proper validation...', 'delay' => 0.5],
                    ['type' => 'activity', 'activity_type' => 'tool_start', 'tool' => 'WriteFile', 'file' => 'app/Models/User.php', 'delay' => 1.0],
                    ['type' => 'activity', 'activity_type' => 'tool_output', 'output' => [
                        'Writing User model...',
                        'Adding password hashing methods',
                        'Implementing validation rules',
                        'Setting up relationships',
                    ], 'delay' => 2.0],
                    ['type' => 'activity', 'activity_type' => 'tool_complete', 'tool' => 'WriteFile', 'result' => '156 lines written', 'delay' => 1.0],
                    ['type' => 'activity', 'activity_type' => 'tool_start', 'tool' => 'EditFile', 'file' => 'config/auth.php', 'delay' => 1.0],
                    ['type' => 'thinking', 'message' => 'Configuring password hashing...', 'delay' => 1.5],
                    ['type' => 'activity', 'activity_type' => 'tool_complete', 'tool' => 'EditFile', 'result' => 'Auth config updated', 'delay' => 1.0],
                    ['type' => 'activity', 'activity_type' => 'tool_start', 'tool' => 'WriteFile', 'file' => 'app/Http/Controllers/AuthController.php', 'delay' => 1.0],
                    ['type' => 'activity', 'activity_type' => 'tool_complete', 'tool' => 'WriteFile', 'result' => 'Controller created', 'delay' => 2.0],
                    ['type' => 'activity', 'activity_type' => 'tool_start', 'tool' => 'TestRunner', 'file' => 'tests/Feature/AuthTest.php', 'delay' => 1.0],
                    ['type' => 'activity', 'activity_type' => 'tool_output', 'output' => [
                        'Running authentication tests...',
                        '✓ User registration test passed',
                        '✓ Login validation test passed',
                        '✓ Password hashing test passed',
                        '✓ Session management test passed',
                    ], 'delay' => 2.5],
                    ['type' => 'activity', 'activity_type' => 'tool_complete', 'tool' => 'TestRunner', 'result' => 'All tests passed', 'delay' => 1.0],
                    ['type' => 'activity', 'activity_type' => 'message', 'speaker' => 'claude', 'content' => '🎉 Authentication system complete! Added secure password hashing, session management, CSRF protection, and comprehensive testing.', 'delay' => 2.0],
                ],
            ],
        ];
    }

    private function startWelcomeSequence(): void
    {
        $this->currentScenario = 'welcome';
        $this->demoStep = 0;
        $this->lastStepTime = microtime(true);
        $this->scenarioComplete = false;
    }

    private function update(): void
    {
        $currentTime = microtime(true);

        // Process current demo scenario
        if (! $this->scenarioComplete && isset($this->demoScenarios[$this->currentScenario])) {
            $scenario = $this->demoScenarios[$this->currentScenario];

            if ($this->demoStep < count($scenario['steps'])) {
                $step = $scenario['steps'][$this->demoStep];

                if ($currentTime - $this->lastStepTime >= $step['delay']) {
                    $this->executeStep($step);
                    $this->demoStep++;
                    $this->lastStepTime = $currentTime;
                }
            } else {
                $this->scenarioComplete = true;
            }
        }

        // Update animations
        $this->updateAnimations();
    }

    private function executeStep(array $step): void
    {
        switch ($step['type']) {
            case 'activity':
                $this->addActivity($step);
                break;
            case 'thinking':
                $this->showThinking($step['message']);
                break;
        }
    }

    private function addActivity(array $step): void
    {
        $activityIndex = count($this->activities);

        $activity = [
            'type' => $step['activity_type'],
            'timestamp' => microtime(true),
        ];

        // Add specific fields based on activity type
        switch ($step['activity_type']) {
            case 'message':
                $activity['speaker'] = $step['speaker'];
                $activity['content'] = $step['content'];

                // Initialize typewriter for Claude messages
                if ($step['speaker'] === 'claude') {
                    $this->typewriterState[$activityIndex] = [
                        'start_time' => microtime(true),
                        'complete' => false,
                    ];
                }
                break;
            case 'tool_start':
                $activity['tool'] = $step['tool'];
                $activity['file'] = $step['file'] ?? '';
                break;
            case 'tool_output':
                $activity['output'] = $step['output'];
                break;
            case 'tool_complete':
                $activity['tool'] = $step['tool'];
                $activity['result'] = $step['result'];
                break;
            case 'task_list':
                $activity['tasks'] = $step['tasks'];
                break;
        }

        $this->activities[] = $activity;
        $this->hideThinking();
    }

    private function showThinking(string $message): void
    {
        $this->showingThinking = true;
        $this->currentSpinner = $message;
    }

    private function hideThinking(): void
    {
        $this->showingThinking = false;
    }

    private function updateAnimations(): void
    {
        // Update typewriter states
        foreach ($this->typewriterState as &$state) {
            if (! $state['complete']) {
                // Already handled in getMessageContent
            }
        }
    }

    private function handleInput(): void
    {
        $input = fread(STDIN, 1024);
        if ($input === false || $input === '') {
            return;
        }

        // Handle various key inputs
        if ($input === 'q' || $input === 'Q' || $input === "\x03") { // Ctrl+C
            $this->cleanup();
            exit(0);
        }

        // Tab to toggle input mode
        if ($input === "\t") {
            $this->inputMode = ! $this->inputMode;

            return;
        }

        // If not in input mode, trigger demo scenarios
        if (! $this->inputMode) {
            if ($this->scenarioComplete && $this->currentScenario === 'welcome') {
                $this->currentScenario = 'login_system';
                $this->demoStep = 0;
                $this->lastStepTime = microtime(true);
                $this->scenarioComplete = false;
            }

            return;
        }

        // Input mode handling
        if ($input === "\n" || $input === "\r") {
            // Enter - submit message
            if (! empty(trim($this->inputBuffer))) {
                $this->submitMessage(trim($this->inputBuffer));
                $this->inputBuffer = '';
                $this->cursorPosition = 0;
            }
        } elseif ($input === "\x7f" || $input === "\x08") {
            // Backspace
            if ($this->cursorPosition > 0) {
                $this->inputBuffer = $this->graphemeSubstr($this->inputBuffer, 0, $this->cursorPosition - 1) .
                                  $this->graphemeSubstr($this->inputBuffer, $this->cursorPosition);
                $this->cursorPosition--;
            }
        } elseif ($input === "\x1b[C") {
            // Right arrow
            if ($this->cursorPosition < $this->graphemeLength($this->inputBuffer)) {
                $this->cursorPosition++;
            }
        } elseif ($input === "\x1b[D") {
            // Left arrow
            if ($this->cursorPosition > 0) {
                $this->cursorPosition--;
            }
        } elseif (ord($input[0]) >= 32 || mb_strlen($input) > 1) {
            // Regular character or multi-byte (emoji, etc.)
            $this->inputBuffer = $this->graphemeSubstr($this->inputBuffer, 0, $this->cursorPosition) .
                               $input .
                               $this->graphemeSubstr($this->inputBuffer, $this->cursorPosition);
            $this->cursorPosition++;
        }
    }

    private function submitMessage(string $message): void
    {
        // Add user message to activity stream
        $this->activities[] = [
            'type' => 'message',
            'speaker' => 'user',
            'content' => $message,
            'timestamp' => microtime(true),
        ];

        // Trigger a Claude response (simulated for demo)
        $this->triggerClaudeResponse($message);
    }

    private function triggerClaudeResponse(string $userMessage): void
    {
        // Simple response generation for demo
        $responses = [
            'login' => "I'll help you create a login system. Let me start by examining your project structure.",
            'database' => "I'll help you set up the database. Let me check your current configuration.",
            'api' => "I'll help you build that API. Let me analyze your requirements and create the endpoints.",
            'test' => "I'll help you write tests for that functionality. Let me examine the code structure.",
            'default' => 'I understand what you need. Let me break this down and help you implement it step by step.',
        ];

        $response = $responses['default'];
        foreach ($responses as $keyword => $text) {
            if ($keyword !== 'default' && mb_stripos($userMessage, $keyword) !== false) {
                $response = $text;
                break;
            }
        }

        // Add Claude's response
        $this->activities[] = [
            'type' => 'message',
            'speaker' => 'claude',
            'content' => $response,
            'timestamp' => microtime(true),
        ];

        // Simulate some tool usage
        $this->simulateToolUsage($userMessage);
    }

    private function simulateToolUsage(string $userMessage): void
    {
        // Add tool usage simulation based on message content
        if (mb_stripos($userMessage, 'login') !== false) {
            $this->activities[] = [
                'type' => 'tool_start',
                'tool' => 'ReadFile',
                'file' => 'routes/web.php',
                'timestamp' => microtime(true),
            ];

            $this->activities[] = [
                'type' => 'tool_output',
                'tool' => 'ReadFile',
                'output' => [
                    '<?php',
                    '',
                    'use Illuminate\\Support\\Facades\\Route;',
                    '',
                    'Route::get(\'/\', function () {',
                    '    return view(\'welcome\');',
                    '});',
                ],
                'timestamp' => microtime(true),
            ];

            $this->activities[] = [
                'type' => 'tool_complete',
                'tool' => 'ReadFile',
                'result' => 'Found basic routes, need to add authentication',
                'timestamp' => microtime(true),
            ];
        }
    }
}

// Initialize and run
$demo = new ClaudeCodeChatDemo;

try {
    $demo->run();
} catch (Exception $e) {
    echo SHOW_CURSOR . EXIT_ALT_SCREEN;
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
