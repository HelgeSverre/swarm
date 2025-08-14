<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

/**
 * Terminal Layout Renderer - Advanced terminal UI layout and rendering utilities
 * Handles panels, expandable sections, and complex terminal layouts
 */
class Renderer
{
    // Layout constants
    public const LAYOUT_SINGLE = 'single';

    public const LAYOUT_SPLIT = 'split';

    public const LAYOUT_PANELS = 'panels';

    // Focus modes
    public const FOCUS_MAIN = 'main';

    public const FOCUS_TASKS = 'tasks';

    public const FOCUS_CONTEXT = 'context';

    // Keyboard shortcuts
    public const SHORTCUTS = [
        'toggle_tasks' => '⌥T',
        'show_help' => '⌥H',
        'clear_history' => '⌥C',
        'refresh' => '⌥R',
        'quit' => '⌥Q',
        'cycle_focus' => 'Tab',
        'focus_main' => '⌥1',
        'focus_tasks' => '⌥2',
        'focus_context' => '⌥3',
    ];

    protected static array $expandedSections = [];

    protected static string $currentFocus = self::FOCUS_MAIN;

    protected static bool $showTaskPanel = false;

    protected static bool $showContextPanel = false;

    /**
     * Create a layout with main area and optional side panels
     */
    public static function createLayout(
        string $mainContent,
        ?array $tasks = null,
        ?array $context = null,
        array $options = []
    ): string {
        $terminalWidth = Ansi::getTerminalWidth();
        $terminalHeight = (int) exec('tput lines') ?: 24;

        // Calculate dimensions
        $sidebarWidth = $options['sidebar_width'] ?? max(30, (int) ($terminalWidth * 0.25));
        $mainWidth = $terminalWidth;

        if ($tasks !== null || $context !== null) {
            $mainWidth = $terminalWidth - $sidebarWidth - 1;
        }

        $output = '';

        // Clear screen if requested
        if ($options['clear'] ?? false) {
            Ansi::clearScreen();
        }

        // Render based on layout
        if ($tasks !== null || $context !== null) {
            $output .= self::renderSplitLayout($mainContent, $tasks, $context, $mainWidth, $sidebarWidth, $terminalHeight);
        } else {
            $output .= self::renderSingleLayout($mainContent, $mainWidth, $terminalHeight);
        }

        return $output;
    }

    /**
     * Create an expandable section
     */
    public static function expandableSection(
        string $id,
        string $title,
        string $content,
        bool $defaultExpanded = false,
        int $previewLines = 3
    ): string {
        $isExpanded = self::$expandedSections[$id] ?? $defaultExpanded;
        $lines = explode("\n", trim($content));
        $totalLines = count($lines);

        $output = '';

        // Title with expand/collapse indicator
        $indicator = $isExpanded ? '▼' : '▶';
        $output .= Ansi::BOLD . $indicator . ' ' . $title . Ansi::RESET . "\n";

        if ($isExpanded) {
            // Show all content
            foreach ($lines as $line) {
                $output .= '  ' . $line . "\n";
            }
        } else {
            // Show preview
            for ($i = 0; $i < min($previewLines, $totalLines); $i++) {
                $output .= '  ' . Ansi::DIM . $lines[$i] . Ansi::RESET . "\n";
            }

            if ($totalLines > $previewLines) {
                $remaining = $totalLines - $previewLines;
                $output .= '  ' . Ansi::DIM . "... +{$remaining} more lines (⌥R to expand)" . Ansi::RESET . "\n";
            }
        }

        return $output;
    }

    /**
     * Toggle expanded state of a section
     */
    public static function toggleSection(string $id): void
    {
        self::$expandedSections[$id] = ! (self::$expandedSections[$id] ?? false);
    }

    /**
     * Set current focus
     */
    public static function setFocus(string $focus): void
    {
        if (in_array($focus, [self::FOCUS_MAIN, self::FOCUS_TASKS, self::FOCUS_CONTEXT])) {
            self::$currentFocus = $focus;
        }
    }

    /**
     * Get current focus
     */
    public static function getFocus(): string
    {
        return self::$currentFocus;
    }

    /**
     * Cycle focus to next panel
     */
    public static function cycleFocus(): void
    {
        $focuses = [self::FOCUS_MAIN, self::FOCUS_TASKS, self::FOCUS_CONTEXT];
        $current = array_search(self::$currentFocus, $focuses);
        $next = ($current + 1) % count($focuses);
        self::$currentFocus = $focuses[$next];
    }

    /**
     * Create a help overlay showing keyboard shortcuts
     */
    public static function helpOverlay(): string
    {
        $shortcuts = self::SHORTCUTS;
        $width = 40;

        $output = "\n";
        $output .= Ansi::infoBar(' Keyboard Shortcuts ', '', Ansi::BG_DARK, false);
        $output .= Ansi::DIM . str_repeat('─', $width) . Ansi::RESET . "\n";

        foreach ($shortcuts as $action => $key) {
            $actionText = str_replace('_', ' ', ucfirst($action));
            $output .= sprintf("  %-20s %s\n", $actionText, Ansi::BRIGHT_CYAN . $key . Ansi::RESET);
        }

        $output .= Ansi::DIM . str_repeat('─', $width) . Ansi::RESET . "\n";
        $output .= Ansi::DIM . '  Press any key to close' . Ansi::RESET . "\n";

        return $output;
    }

    /**
     * Create a progress detail view
     */
    public static function progressDetail(array $task): string
    {
        $output = '';

        // Task header
        $output .= Ansi::sectionHeader($task['description'] ?? 'Task Progress');

        // Overall progress
        if (isset($task['progress'])) {
            $current = $task['progress']['current'] ?? 0;
            $total = $task['progress']['total'] ?? 1;
            $percentage = $total > 0 ? round(($current / $total) * 100) : 0;

            $output .= Ansi::progressBar($current, $total, 40, true) . "\n";
            $output .= "\n";
        }

        // Step details
        if (isset($task['steps']) && is_array($task['steps'])) {
            $output .= Ansi::BOLD . 'Steps:' . Ansi::RESET . "\n";

            foreach ($task['steps'] as $index => $step) {
                $stepNum = $index + 1;
                $status = $step['status'] ?? 'pending';
                $icon = match ($status) {
                    'completed' => Ansi::GREEN . Ansi::CHECK,
                    'running' => Ansi::YELLOW . Ansi::PLAY,
                    'error' => Ansi::RED . '✗',
                    default => Ansi::DIM . Ansi::CIRCLE
                };

                $output .= sprintf("  %2d. %s %s\n", $stepNum, $icon . Ansi::RESET, $step['description'] ?? '');

                // Show step details if running
                if ($status === 'running' && isset($step['detail'])) {
                    $output .= '      ' . Ansi::DIM . $step['detail'] . Ansi::RESET . "\n";
                }

                // Show error if failed
                if ($status === 'error' && isset($step['error'])) {
                    $output .= '      ' . Ansi::RED . '✗ ' . $step['error'] . Ansi::RESET . "\n";
                }
            }
        }

        // Timing information
        if (isset($task['timing'])) {
            $output .= "\n";
            $output .= Ansi::DIM . 'Started: ' . $task['timing']['started'] . Ansi::RESET . "\n";

            if (isset($task['timing']['elapsed'])) {
                $output .= Ansi::DIM . 'Elapsed: ' . $task['timing']['elapsed'] . Ansi::RESET . "\n";
            }
        }

        return $output;
    }

    /**
     * Render single column layout
     */
    protected static function renderSingleLayout(string $content, int $width, int $height): string
    {
        return $content;
    }

    /**
     * Render split layout with main area and sidebar
     */
    protected static function renderSplitLayout(
        string $mainContent,
        ?array $tasks,
        ?array $context,
        int $mainWidth,
        int $sidebarWidth,
        int $height
    ): string {
        $output = '';
        $mainLines = explode("\n", $mainContent);
        $sidebarLines = [];

        // Build sidebar content
        if ($tasks !== null) {
            $sidebarLines = array_merge($sidebarLines, self::renderTaskPanel($tasks, $sidebarWidth));
        }

        if ($context !== null) {
            if (! empty($sidebarLines)) {
                $sidebarLines[] = Ansi::DIM . str_repeat('─', $sidebarWidth) . Ansi::RESET;
            }
            $sidebarLines = array_merge($sidebarLines, self::renderContextPanel($context, $sidebarWidth));
        }

        // Combine main and sidebar line by line
        $maxLines = max(count($mainLines), count($sidebarLines));

        for ($i = 0; $i < $maxLines; $i++) {
            // Main content
            if (isset($mainLines[$i])) {
                $line = $mainLines[$i];
                $lineLength = mb_strlen(Ansi::stripAnsi($line));
                $padding = max(0, $mainWidth - $lineLength);
                $output .= $line . str_repeat(' ', $padding);
            } else {
                $output .= str_repeat(' ', $mainWidth);
            }

            // Separator
            $output .= Ansi::DIM . Ansi::BOX_V . Ansi::RESET;

            // Sidebar content
            if (isset($sidebarLines[$i])) {
                $output .= ' ' . $sidebarLines[$i];
            }

            $output .= "\n";
        }

        return $output;
    }

    /**
     * Render task panel
     */
    protected static function renderTaskPanel(array $tasks, int $width): array
    {
        $lines = [];
        $isActive = self::$currentFocus === self::FOCUS_TASKS;

        // Header
        $header = Ansi::BOLD . Ansi::UNDERLINE . 'Task Queue' . Ansi::RESET;
        if ($isActive) {
            $header .= Ansi::BRIGHT_CYAN . ' [ACTIVE]' . Ansi::RESET;
        }
        $lines[] = $header;
        $lines[] = '';

        // Tasks
        foreach ($tasks as $index => $task) {
            $status = $task['status'] ?? 'pending';
            $icon = match ($status) {
                'completed' => Ansi::GREEN . Ansi::CHECK,
                'running' => Ansi::YELLOW . Ansi::PLAY,
                'error' => Ansi::RED . '✗',
                default => Ansi::DIM . Ansi::CIRCLE
            };

            $number = ($index + 1) . '.';
            $description = Ansi::truncate($task['description'] ?? '', $width - 6);

            $line = sprintf('%3s %s %s', $number, $icon . Ansi::RESET, $description);

            // Highlight if selected in task focus mode
            if ($isActive && $index === ($task['selected'] ?? 0)) {
                $line = Ansi::REVERSE . $line . Ansi::RESET;
            }

            $lines[] = $line;

            // Show progress for running tasks
            if ($status === 'running' && isset($task['progress'])) {
                $progress = Ansi::progressBar($task['progress']['current'] ?? 0, $task['progress']['total'] ?? 1, 20, false);
                $lines[] = '    ' . $progress;
            }
        }

        return $lines;
    }

    /**
     * Render context panel
     */
    protected static function renderContextPanel(array $context, int $width): array
    {
        $lines = [];
        $isActive = self::$currentFocus === self::FOCUS_CONTEXT;

        // Header
        $header = Ansi::BOLD . Ansi::UNDERLINE . 'Context' . Ansi::RESET;
        if ($isActive) {
            $header .= Ansi::BRIGHT_CYAN . ' [ACTIVE]' . Ansi::RESET;
        }
        $lines[] = $header;
        $lines[] = '';

        // Context items
        foreach ($context as $key => $value) {
            $lines[] = Ansi::CYAN . ucfirst($key) . ':' . Ansi::RESET;

            if (is_array($value)) {
                foreach ($value as $item) {
                    $lines[] = '  ' . Ansi::DIM . '• ' . Ansi::RESET . Ansi::truncate((string) $item, $width - 4);
                }
            } else {
                $lines[] = '  ' . Ansi::truncate((string) $value, $width - 2);
            }

            $lines[] = '';
        }

        return $lines;
    }
}
