<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

/**
 * Full Terminal UI with positioned rendering
 * This creates the actual rich terminal interface with panels and positioned output
 */
class FullTerminalUI
{
    protected int $terminalWidth;

    protected int $terminalHeight;

    protected int $mainAreaWidth;

    protected int $sidebarWidth;

    protected array $mainContent = [];

    protected array $tasks = [];

    protected array $context = [];

    protected string $input = '';

    protected string $status = 'Ready';

    protected array $history = [];

    protected bool $showSidebar = true;

    protected string $currentFocus = 'main';

    protected static ?self $instance = null;

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->calculateLayout();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Clear screen and prepare for rendering
     */
    public function clear(): void
    {
        // Clear screen and scrollback buffer
        echo "\033[2J\033[3J\033[H";
        // Hide cursor during rendering
        echo "\033[?25l";
    }

    /**
     * Render the full UI
     */
    public function render(): void
    {
        $this->clear();

        // Draw vertical divider if sidebar is shown
        if ($this->showSidebar) {
            for ($row = 1; $row <= $this->terminalHeight; $row++) {
                $this->moveCursor($row, $this->mainAreaWidth + 1);
                echo Ansi::DIM . Ansi::BOX_V . Ansi::RESET;
            }
        }

        // Render components
        $this->renderMainArea();
        if ($this->showSidebar) {
            $this->renderSidebar();
        }

        // Position cursor at input
        $this->positionCursorAtInput();

        // Show cursor
        echo "\033[?25h";
    }

    /**
     * Add to history
     */
    public function addHistory(string $type, string $content, ?int $time = null): void
    {
        $this->history[] = [
            'type' => $type,
            'content' => $content,
            'time' => $time ?? time(),
        ];

        // Keep last 100 entries
        if (count($this->history) > 100) {
            array_shift($this->history);
        }
    }

    /**
     * Update status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * Update tasks
     */
    public function setTasks(array $tasks): void
    {
        $this->tasks = $tasks;
    }

    /**
     * Update context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Update input
     */
    public function setInput(string $input): void
    {
        $this->input = $input;
    }

    /**
     * Toggle sidebar
     */
    public function toggleSidebar(): void
    {
        $this->showSidebar = ! $this->showSidebar;
        $this->calculateLayout();
    }

    /**
     * Set focus
     */
    public function setFocus(string $focus): void
    {
        $this->currentFocus = $focus;
    }

    /**
     * Update terminal dimensions
     */
    protected function updateTerminalSize(): void
    {
        $this->terminalWidth = (int) exec('tput cols') ?: 80;
        $this->terminalHeight = (int) exec('tput lines') ?: 24;
    }

    /**
     * Calculate layout dimensions
     */
    protected function calculateLayout(): void
    {
        if ($this->showSidebar) {
            $this->sidebarWidth = max(30, (int) ($this->terminalWidth * 0.25));
            $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;
        } else {
            $this->mainAreaWidth = $this->terminalWidth;
            $this->sidebarWidth = 0;
        }
    }

    /**
     * Render main area
     */
    protected function renderMainArea(): void
    {
        $row = 1;

        // Status bar
        $this->moveCursor($row++, 2);
        echo $this->buildStatusBar();

        // Activity area
        if (! empty($this->history)) {
            $row++;
            $this->moveCursor($row++, 2);
            echo Ansi::BOLD . 'Recent activity:' . Ansi::RESET;

            $availableLines = $this->terminalHeight - $row - 6;
            $recentHistory = array_slice($this->history, -$availableLines);

            foreach ($recentHistory as $entry) {
                if ($row >= $this->terminalHeight - 5) {
                    break;
                }
                $this->moveCursor($row++, 2);
                echo $this->formatHistoryEntry($entry);
            }
        }

        // Footer separator
        $this->moveCursor($this->terminalHeight - 3, 1);
        echo Ansi::DIM . str_repeat(Ansi::BOX_H, $this->mainAreaWidth) . Ansi::RESET;

        // Shortcuts
        $this->moveCursor($this->terminalHeight - 2, 2);
        echo Ansi::DIM . '⌥T: tasks  ⌥H: help  Tab: focus  ⌥Q: quit' . Ansi::RESET;

        // Input prompt
        $this->moveCursor($this->terminalHeight, 2);
        $isActive = $this->currentFocus === 'main';
        if ($isActive) {
            echo Ansi::BLUE . 'swarm >' . Ansi::RESET . ' ' . $this->input;
        } else {
            echo Ansi::DIM . 'swarm > ' . $this->input . Ansi::RESET;
        }
    }

    /**
     * Render sidebar
     */
    protected function renderSidebar(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 1;

        // Task Queue
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . Ansi::UNDERLINE . 'Task Queue' . Ansi::RESET;
        if ($this->currentFocus === 'tasks') {
            echo Ansi::BRIGHT_CYAN . ' [ACTIVE]' . Ansi::RESET;
        }

        $row++;

        // Tasks
        if (! empty($this->tasks)) {
            $maxTasks = min(8, (int) (($this->terminalHeight / 2) - 4));
            $taskDisplay = array_slice($this->tasks, 0, $maxTasks);

            foreach ($taskDisplay as $i => $task) {
                $this->moveCursor($row++, $col);
                echo $this->formatTaskLine($task, $i + 1);
            }

            if (count($this->tasks) > $maxTasks) {
                $this->moveCursor($row++, $col);
                echo Ansi::DIM . '... +' . (count($this->tasks) - $maxTasks) . ' more' . Ansi::RESET;
            }
        } else {
            $this->moveCursor($row++, $col);
            echo Ansi::DIM . 'No tasks' . Ansi::RESET;
        }

        // Separator
        $row += 2;
        $this->moveCursor($row++, $this->mainAreaWidth + 2);
        echo Ansi::DIM . str_repeat(Ansi::BOX_H, $this->sidebarWidth - 1) . Ansi::RESET;

        // Context
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . Ansi::UNDERLINE . 'Context' . Ansi::RESET;
        if ($this->currentFocus === 'context') {
            echo Ansi::BRIGHT_CYAN . ' [ACTIVE]' . Ansi::RESET;
        }

        $row++;

        if (! empty($this->context)) {
            foreach ($this->context as $key => $value) {
                if ($row >= $this->terminalHeight - 2) {
                    break;
                }

                $this->moveCursor($row++, $col);
                echo Ansi::CYAN . ucfirst($key) . ':' . Ansi::RESET;

                if (is_array($value)) {
                    foreach ($value as $item) {
                        if ($row >= $this->terminalHeight - 2) {
                            break;
                        }
                        $this->moveCursor($row++, $col);
                        echo '  ' . Ansi::DIM . '• ' . Ansi::RESET . $this->truncate((string) $item, $this->sidebarWidth - 6);
                    }
                } else {
                    $this->moveCursor($row++, $col);
                    echo '  ' . $this->truncate((string) $value, $this->sidebarWidth - 4);
                }
                $row++;
            }
        }
    }

    /**
     * Build status bar
     */
    protected function buildStatusBar(): string
    {
        $prefix = ' 💮 swarm ';
        $separator = Ansi::DIM . Ansi::BOX_V . Ansi::RESET;

        $parts = [];
        $parts[] = $prefix;

        if ($this->status !== 'Ready') {
            $parts[] = $separator . ' ' . Ansi::GREEN . '● ' . $this->status . Ansi::RESET . ' ';
        } else {
            $parts[] = $separator . ' ' . Ansi::DIM . $this->status . Ansi::RESET . ' ';
        }

        return implode('', $parts);
    }

    /**
     * Format history entry
     */
    protected function formatHistoryEntry(array $entry): string
    {
        $time = date('H:i:s', $entry['time'] ?? time());
        $prefix = Ansi::DIM . "[{$time}]" . Ansi::RESET . ' ';

        $type = $entry['type'] ?? 'info';
        $content = $entry['content'] ?? '';

        $icon = Ansi::ICONS[$type] ?? '•';
        $color = match ($type) {
            'success', 'running' => Ansi::GREEN,
            'error' => Ansi::RED,
            'command' => Ansi::BLUE,
            'tool' => Ansi::CYAN,
            'thinking' => Ansi::WHITE,
            default => Ansi::WHITE
        };

        return $prefix . $color . $icon . Ansi::RESET . ' ' . $content;
    }

    /**
     * Format task line
     */
    protected function formatTaskLine(array $task, int $number): string
    {
        $status = $task['status'] ?? 'pending';
        $icon = match ($status) {
            'completed' => Ansi::GREEN . Ansi::CHECK,
            'running' => Ansi::YELLOW . Ansi::PLAY,
            'error' => Ansi::RED . '✗',
            default => Ansi::DIM . Ansi::CIRCLE
        };

        $num = mb_str_pad($number . '.', 3);
        $desc = $this->truncate($task['description'] ?? '', $this->sidebarWidth - 8);

        return $num . ' ' . $icon . Ansi::RESET . ' ' . $desc;
    }

    /**
     * Move cursor to position
     */
    protected function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    /**
     * Position cursor at input location
     */
    protected function positionCursorAtInput(): void
    {
        if ($this->currentFocus === 'main') {
            $this->moveCursor($this->terminalHeight, 10 + mb_strlen($this->input));
        }
    }

    /**
     * Truncate text
     */
    protected function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3) . '...';
    }
}
