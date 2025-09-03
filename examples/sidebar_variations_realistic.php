#!/usr/bin/env php
<?php

/**
 * Realistic Sidebar Variations for Swarm
 * Based on actual available data: tasks, tool activity, status, progress
 * 5 variations showing practical implementations
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HelgeSverre\Swarm\CLI\Terminal\Ansi;

class RealisticSidebarVariations
{
    private int $terminalHeight;

    private int $terminalWidth;

    private int $mainAreaWidth;

    private int $sidebarWidth;

    private int $currentStyle = 1;

    private bool $running = true;

    // Realistic Swarm data
    private array $tasks = [
        ['id' => '1', 'status' => 'completed', 'description' => 'Search for authentication implementation'],
        ['id' => '2', 'status' => 'completed', 'description' => 'Read UserController.php'],
        ['id' => '3', 'status' => 'running', 'description' => 'Implement password hashing'],
        ['id' => '4', 'status' => 'pending', 'description' => 'Write tests for auth'],
        ['id' => '5', 'status' => 'pending', 'description' => 'Update documentation'],
    ];

    private array $toolActivity = [
        ['tool' => 'Grep', 'params' => 'auth* --type=php', 'status' => 'success', 'time' => '2s ago'],
        ['tool' => 'ReadFile', 'params' => 'UserController.php', 'status' => 'success', 'time' => '5s ago'],
        ['tool' => 'WriteFile', 'params' => 'auth/Hash.php', 'status' => 'running', 'time' => 'now'],
    ];

    private array $recentFiles = [
        'src/UserController.php',
        'src/auth/Hash.php',
        'tests/AuthTest.php',
        'config/auth.yaml',
    ];

    private string $currentOperation = 'Implementing password hashing...';

    private int $progress = 65;

    private string $agentStatus = 'Processing';

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->sidebarWidth = max(40, (int) ($this->terminalWidth * 0.35));
        $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;
    }

    public function run(): void
    {
        // Setup terminal
        system('stty -echo -icanon min 1 time 0');
        stream_set_blocking(STDIN, false);
        echo "\033[?1049h"; // Alternate screen
        echo "\033[?25l";   // Hide cursor

        while ($this->running) {
            $this->render();

            $key = $this->readKey();
            if ($key !== null) {
                $this->handleInput($key);
            }

            usleep(50000);
        }

        // Cleanup
        echo "\033[?25h";   // Show cursor
        echo "\033[?1049l"; // Exit alternate screen
        system('stty sane');
    }

    private function render(): void
    {
        $this->clearScreen();

        // Render header
        $this->renderHeader();

        // Render main area (activity feed)
        $this->renderMainArea();

        // Render sidebar based on current style
        switch ($this->currentStyle) {
            case 1:
                $this->renderStyle1_TaskFocused();
                break;
            case 2:
                $this->renderStyle2_ToolActivity();
                break;
            case 3:
                $this->renderStyle3_ProgressDriven();
                break;
            case 4:
                $this->renderStyle4_FileCentric();
                break;
            case 5:
                $this->renderStyle5_Hybrid();
                break;
        }

        // Render footer
        $this->renderFooter();
    }

    private function renderHeader(): void
    {
        $this->moveCursor(1, 1);
        echo Ansi::BG_DARK;
        echo ' 🤖 ' . Ansi::BOLD . 'Swarm Agent' . Ansi::RESET . Ansi::BG_DARK;
        echo ' │ ' . Ansi::GREEN . '● ' . $this->agentStatus . Ansi::RESET . Ansi::BG_DARK;
        echo ' │ ' . Ansi::YELLOW . $this->currentOperation . Ansi::RESET . Ansi::BG_DARK;
        echo str_repeat(' ', max(0, $this->terminalWidth - mb_strlen($this->currentOperation) - 30)) . Ansi::RESET;
    }

    private function renderMainArea(): void
    {
        $this->moveCursor(3, 2);
        echo Ansi::BOLD . 'Activity Feed' . Ansi::RESET;

        $row = 5;

        // Simulated activity entries
        $activities = [
            ['time' => '14:23:45', 'type' => 'command', 'content' => 'implement password hashing for user auth'],
            ['time' => '14:23:46', 'type' => 'tool', 'content' => 'Grep: Searching for auth implementations...'],
            ['time' => '14:23:47', 'type' => 'success', 'content' => 'Found 3 files with auth patterns'],
            ['time' => '14:23:48', 'type' => 'tool', 'content' => 'ReadFile: Reading UserController.php'],
            ['time' => '14:23:49', 'type' => 'status', 'content' => 'Analyzing code structure...'],
            ['time' => '14:23:50', 'type' => 'tool', 'content' => 'WriteFile: Creating auth/Hash.php'],
            ['time' => '14:23:51', 'type' => 'progress', 'content' => 'Writing password hashing implementation...'],
        ];

        foreach ($activities as $activity) {
            if ($row > $this->terminalHeight - 5) {
                break;
            }

            $this->moveCursor($row++, 2);
            echo Ansi::DIM . "[{$activity['time']}]" . Ansi::RESET . ' ';

            $icon = match ($activity['type']) {
                'command' => Ansi::BLUE . '$',
                'tool' => Ansi::CYAN . '🔧',
                'success' => Ansi::GREEN . '✓',
                'status' => Ansi::YELLOW . '●',
                'progress' => Ansi::YELLOW . '▶',
                default => '•'
            };

            echo $icon . Ansi::RESET . ' ' . $activity['content'];
        }
    }

    /**
     * Style 1: Task-Focused - Emphasizes task queue and progress
     */
    private function renderStyle1_TaskFocused(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Task Queue Header
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '📋 Task Queue' . Ansi::RESET;

        // Task statistics
        $completed = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));
        $total = count($this->tasks);

        $this->moveCursor($row++, $col);
        $this->renderProgressBar($completed, $total, 30);

        $this->moveCursor($row++, $col);
        echo Ansi::DIM . "{$completed}/{$total} completed" . Ansi::RESET;

        $row++;

        // Task list with status
        foreach ($this->tasks as $task) {
            if ($row > $this->terminalHeight - 8) {
                break;
            }

            $this->moveCursor($row++, $col);

            $statusIcon = match ($task['status']) {
                'completed' => Ansi::GREEN . '✓',
                'running' => Ansi::YELLOW . '▶',
                'pending' => Ansi::DIM . '○',
            };

            $taskText = $this->truncate($task['description'], 35);
            if ($task['status'] === 'running') {
                $taskText = Ansi::BOLD . $taskText . Ansi::RESET;
            } elseif ($task['status'] === 'completed') {
                $taskText = Ansi::DIM . $taskText . Ansi::RESET;
            }

            echo $statusIcon . ' ' . $taskText . Ansi::RESET;
        }

        // Current operation progress
        if ($this->progress > 0) {
            $row += 2;
            $this->moveCursor($row++, $col);
            echo Ansi::BOLD . 'Current Progress' . Ansi::RESET;

            $this->moveCursor($row++, $col);
            $this->renderProgressBar($this->progress, 100, 30);

            $this->moveCursor($row++, $col);
            echo Ansi::DIM . $this->progress . '% complete' . Ansi::RESET;
        }
    }

    /**
     * Style 2: Tool Activity - Shows recent tool calls
     */
    private function renderStyle2_ToolActivity(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Tool Activity Header
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '🔧 Tool Activity' . Ansi::RESET;

        $row++;

        // Recent tool calls
        foreach ($this->toolActivity as $activity) {
            if ($row > $this->terminalHeight / 2) {
                break;
            }

            $this->moveCursor($row++, $col);

            $statusIcon = match ($activity['status']) {
                'success' => Ansi::GREEN . '✓',
                'running' => Ansi::YELLOW . '●',
                'failed' => Ansi::RED . '✗',
                default => '•'
            };

            echo $statusIcon . ' ' . Ansi::CYAN . $activity['tool'] . Ansi::RESET;

            $this->moveCursor($row++, $col);
            echo '  ' . Ansi::DIM . $this->truncate($activity['params'], 35) . Ansi::RESET;

            $this->moveCursor($row++, $col);
            echo '  ' . Ansi::DIM . $activity['time'] . Ansi::RESET;

            $row++;
        }

        // Files being worked on
        $row++;
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '📁 Recent Files' . Ansi::RESET;

        $row++;
        foreach ($this->recentFiles as $file) {
            if ($row > $this->terminalHeight - 4) {
                break;
            }

            $this->moveCursor($row++, $col);
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $icon = match ($ext) {
                'php' => '🐘',
                'yaml' => '⚙️',
                'md' => '📝',
                default => '📄'
            };

            echo $icon . ' ' . Ansi::DIM . $this->truncate($file, 35) . Ansi::RESET;
        }
    }

    /**
     * Style 3: Progress-Driven - Detailed progress indicators
     */
    private function renderStyle3_ProgressDriven(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Overall Progress
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '📊 Progress Overview' . Ansi::RESET;

        $row++;

        // Task completion progress
        $completed = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));
        $total = count($this->tasks);

        $this->moveCursor($row++, $col);
        echo 'Tasks:';
        $this->moveCursor($row++, $col);
        $this->renderDetailedProgress($completed, $total, 35);

        $row++;

        // Current operation progress
        $this->moveCursor($row++, $col);
        echo 'Current Operation:';
        $this->moveCursor($row++, $col);
        $this->renderDetailedProgress($this->progress, 100, 35);

        $row++;

        // Time estimates
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '⏱️ Time Tracking' . Ansi::RESET;

        $row++;
        $this->moveCursor($row++, $col);
        echo 'Elapsed: ' . Ansi::CYAN . '2m 45s' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'Estimated: ' . Ansi::YELLOW . '~1m remaining' . Ansi::RESET;

        $row += 2;

        // Activity indicators
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '🎯 Current Focus' . Ansi::RESET;

        $row++;
        $runningTask = array_filter($this->tasks, fn ($t) => $t['status'] === 'running');
        if (! empty($runningTask)) {
            $task = reset($runningTask);
            $this->moveCursor($row++, $col);
            echo Ansi::YELLOW . '▶ ' . Ansi::RESET . $this->truncate($task['description'], 33);

            $row++;
            $this->moveCursor($row++, $col);
            echo 'Step: ' . Ansi::DIM . 'Writing implementation' . Ansi::RESET;

            $this->moveCursor($row++, $col);
            echo 'Tool: ' . Ansi::CYAN . 'WriteFile' . Ansi::RESET;
        }
    }

    /**
     * Style 4: File-Centric - Focus on files being modified
     */
    private function renderStyle4_FileCentric(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // File Operations
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '📂 File Operations' . Ansi::RESET;

        $row++;

        // Currently editing
        $this->moveCursor($row++, $col);
        echo Ansi::GREEN . '● Editing:' . Ansi::RESET;
        $this->moveCursor($row++, $col);
        echo '  ' . Ansi::BOLD . 'auth/Hash.php' . Ansi::RESET;
        $this->moveCursor($row++, $col);
        echo '  ' . Ansi::DIM . '+45 lines added' . Ansi::RESET;

        $row++;

        // Recently read
        $this->moveCursor($row++, $col);
        echo Ansi::BLUE . '◆ Recently Read:' . Ansi::RESET;
        foreach (array_slice($this->recentFiles, 0, 3) as $file) {
            $this->moveCursor($row++, $col);
            echo '  ' . $this->truncate($file, 35);
        }

        $row++;

        // File statistics
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '📈 Statistics' . Ansi::RESET;

        $row++;
        $this->moveCursor($row++, $col);
        echo 'Files read: ' . Ansi::CYAN . '12' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'Files modified: ' . Ansi::GREEN . '3' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'Lines written: ' . Ansi::YELLOW . '~150' . Ansi::RESET;

        $row += 2;

        // Next files to process
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '⏭️ Up Next' . Ansi::RESET;

        $row++;
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '• tests/AuthTest.php' . Ansi::RESET;
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '• docs/AUTH.md' . Ansi::RESET;
    }

    /**
     * Style 5: Hybrid - Balanced view of all information
     */
    private function renderStyle5_Hybrid(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Compact status line
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '📊 Status' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'Tasks: ';
        echo Ansi::GREEN . '✓2 ' . Ansi::RESET;
        echo Ansi::YELLOW . '●1 ' . Ansi::RESET;
        echo Ansi::DIM . '○2' . Ansi::RESET;
        echo ' │ ' . Ansi::CYAN . $this->progress . '%' . Ansi::RESET;

        $row += 2;

        // Current activity (compact)
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '🎯 Now' . Ansi::RESET;

        $runningTask = array_filter($this->tasks, fn ($t) => $t['status'] === 'running');
        if (! empty($runningTask)) {
            $task = reset($runningTask);
            $this->moveCursor($row++, $col);
            echo Ansi::YELLOW . '▶ ' . Ansi::RESET . $this->truncate($task['description'], 33);
        }

        $row++;

        // Last tool call
        $lastTool = $this->toolActivity[0] ?? null;
        if ($lastTool) {
            $this->moveCursor($row++, $col);
            echo Ansi::CYAN . '🔧 ' . $lastTool['tool'] . Ansi::RESET;
            $this->moveCursor($row++, $col);
            echo '   ' . Ansi::DIM . $this->truncate($lastTool['params'], 32) . Ansi::RESET;
        }

        $row += 2;

        // Files (compact list)
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '📁 Files' . Ansi::RESET;

        foreach (array_slice($this->recentFiles, 0, 4) as $file) {
            if ($row > $this->terminalHeight - 6) {
                break;
            }
            $this->moveCursor($row++, $col);
            echo '• ' . Ansi::DIM . basename($file) . Ansi::RESET;
        }

        // Bottom stats
        $row = $this->terminalHeight - 4;
        $this->moveCursor($row++, $col);
        echo Ansi::DIM . str_repeat('─', 35) . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::DIM . 'Time: 2m 45s │ Tools: 8 │ Files: 4' . Ansi::RESET;
    }

    private function renderProgressBar(int $current, int $total, int $width): void
    {
        $percentage = $total > 0 ? ($current / $total) : 0;
        $filled = (int) ($percentage * $width);

        echo Ansi::GREEN;
        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                echo '█';
            } elseif ($i === $filled) {
                echo '▓';
            } else {
                echo Ansi::DIM . '░';
            }
        }
        echo Ansi::RESET;
    }

    private function renderDetailedProgress(int $current, int $total, int $width): void
    {
        $percentage = $total > 0 ? ($current / $total) : 0;
        $percentText = round($percentage * 100) . '%';

        // Progress bar with percentage overlay
        $barWidth = $width - mb_strlen($percentText) - 2;
        $filled = (int) ($percentage * $barWidth);

        echo '[';
        for ($i = 0; $i < $barWidth; $i++) {
            if ($i < $filled) {
                echo Ansi::GREEN . '=' . Ansi::RESET;
            } else {
                echo Ansi::DIM . '-' . Ansi::RESET;
            }
        }
        echo '] ' . Ansi::CYAN . $percentText . Ansi::RESET;
    }

    private function renderFooter(): void
    {
        $this->moveCursor($this->terminalHeight - 1, 2);
        echo Ansi::DIM . 'Press 1-5 to switch styles | Q to quit | Style: '
             . $this->getStyleName($this->currentStyle) . Ansi::RESET;
    }

    private function getStyleName(int $style): string
    {
        return match ($style) {
            1 => 'Task-Focused',
            2 => 'Tool Activity',
            3 => 'Progress-Driven',
            4 => 'File-Centric',
            5 => 'Hybrid View',
            default => 'Unknown'
        };
    }

    private function handleInput(string $key): void
    {
        switch ($key) {
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
                $this->currentStyle = (int) $key;
                break;
            case 'q':
            case 'Q':
                $this->running = false;
                break;
        }
    }

    private function readKey(): ?string
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        if (stream_select($read, $write, $except, 0, 0) > 0) {
            return fgetc(STDIN);
        }

        return null;
    }

    private function clearScreen(): void
    {
        echo "\033[2J\033[H";
    }

    private function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    private function updateTerminalSize(): void
    {
        $this->terminalHeight = (int) exec('tput lines') ?: 24;
        $this->terminalWidth = (int) exec('tput cols') ?: 80;
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 2) . '…';
    }
}

// Run the demo
echo "Starting Realistic Sidebar Variations...\n";
$demo = new RealisticSidebarVariations;
$demo->run();
echo "Demo ended.\n";
