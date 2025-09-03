<?php

declare(strict_types=1);

namespace Examples\TuiLib\SwarmMock;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;

/**
 * SwarmSidebar widget - Exact replica of FullTerminalUI sidebar rendering
 *
 * This component replicates the exact logic from FullTerminalUI::renderSidebar()
 * which includes task queue and context sections.
 */
class SwarmSidebar extends Widget
{
    protected array $tasks = [];

    protected array $context = [
        'directory' => '',
        'files' => [],
        'tools' => [],
        'notes' => [],
    ];

    protected bool $isFocused = false;

    protected string $focusArea = 'tasks'; // 'tasks' or 'context'

    protected int $selectedTaskIndex = 0;

    protected int $selectedContextLine = 0;

    protected string $contextInput = '';

    public function __construct(string $id = 'swarm_sidebar')
    {
        parent::__construct($id);
    }

    /**
     * Set the task list
     */
    public function setTasks(array $tasks): void
    {
        $this->tasks = $tasks;
        $this->markNeedsRepaint();
    }

    /**
     * Set the context information
     */
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
        $this->markNeedsRepaint();
    }

    /**
     * Set focus state and area
     */
    public function setFocused(bool $focused, string $area = 'tasks'): void
    {
        $this->isFocused = $focused;
        $this->focusArea = $area;
        $this->markNeedsRepaint();
    }

    /**
     * Set selected task index
     */
    public function setSelectedTaskIndex(int $index): void
    {
        $this->selectedTaskIndex = max(0, min($index, count($this->tasks) - 1));
        $this->markNeedsRepaint();
    }

    /**
     * Set selected context line
     */
    public function setSelectedContextLine(int $line): void
    {
        $this->selectedContextLine = $line;
        $this->markNeedsRepaint();
    }

    /**
     * Set context input text
     */
    public function setContextInput(string $input): void
    {
        $this->contextInput = $input;
        $this->markNeedsRepaint();
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        return new Size($constraints->maxWidth, $constraints->maxHeight);
    }

    public function layout(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->clearLayoutFlag();
    }

    public function paint(BuildContext $context): string
    {
        if ($this->bounds === null) {
            return '';
        }

        $this->renderToCanvas($context->canvas);

        $this->clearRepaintFlag();

        return '';
    }

    /**
     * Handle key events for sidebar navigation
     */
    public function handleKeyEvent(string $key): bool
    {
        if (! $this->isFocused) {
            return false;
        }

        if ($this->focusArea === 'tasks') {
            return $this->handleTasksInput($key);
        } elseif ($this->focusArea === 'context') {
            return $this->handleContextInput($key);
        }

        return false;
    }

    /**
     * Get current selected task
     */
    public function getSelectedTask(): ?array
    {
        return $this->tasks[$this->selectedTaskIndex] ?? null;
    }

    /**
     * Add a note to the context
     */
    public function addContextNote(string $note): void
    {
        $this->context['notes'][] = $note;
        $this->markNeedsRepaint();
    }

    /**
     * Canvas-based rendering of sidebar
     */
    protected function renderToCanvas(?\Examples\TuiLib\Core\Canvas $canvas): void
    {
        if (! $canvas || ! $this->bounds) {
            return;
        }

        $col = $this->bounds->x;
        $row = $this->bounds->y;
        $sidebarWidth = $this->bounds->width;

        // Task Queue section
        $tasksActive = $this->isFocused && $this->focusArea === 'tasks';

        $taskQueueText = "\033[1m\033[4mTask Queue\033[0m";
        if ($tasksActive) {
            $taskQueueText .= "\033[96m [ACTIVE]\033[0m";
        }
        $canvas->drawText($col, $row++, $taskQueueText);

        $running = count(array_filter($this->tasks, fn ($t) => ($t['status'] ?? '') === 'running'));
        $pending = count(array_filter($this->tasks, fn ($t) => ($t['status'] ?? '') === 'pending'));

        $statusText = "\033[32m{$running} running\033[0m, \033[2m{$pending} pending\033[0m";
        $canvas->drawText($col, $row++, $statusText);

        $row++;

        // Show tasks
        $maxTasks = min(5, (int) (($this->bounds->height / 2) - 4));
        $taskDisplay = array_slice($this->tasks, 0, $maxTasks);

        foreach ($taskDisplay as $i => $task) {
            $isSelected = $tasksActive && $i === $this->selectedTaskIndex;
            $taskLine = $this->buildCompactTaskLine($task, $i + 1, $sidebarWidth - 4);

            if ($isSelected) {
                $taskLine = "\033[7m" . $taskLine . "\033[0m";
            }

            $canvas->drawText($col, $row++, $taskLine);
        }

        if (count($this->tasks) > $maxTasks) {
            $moreText = "\033[2m... +" . (count($this->tasks) - $maxTasks) . " more\033[0m";
            $canvas->drawText($col, $row++, $moreText);
        }

        // Separator
        $row += 1;
        $separatorText = "\033[2m" . str_repeat('─', $sidebarWidth - 1) . "\033[0m";
        $canvas->drawText($this->bounds->x, $row++, $separatorText);

        // Context section
        $contextActive = $this->isFocused && $this->focusArea === 'context';
        $contextHeaderText = "\033[1m\033[4mContext\033[0m";
        if ($contextActive) {
            $contextHeaderText .= "\033[96m [ACTIVE]\033[0m";
        }
        $canvas->drawText($col, $row++, $contextHeaderText);
        $row++;

        $contextLine = 0;

        // Directory
        if (! empty($this->context['directory'])) {
            $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
            $dirHeaderText = ($isSelected ? "\033[7m" : '') . "\033[36mDir:\033[0m";
            $canvas->drawText($col, $row++, $dirHeaderText);

            $dirText = '  ' . $this->truncate($this->context['directory'], $sidebarWidth - 5);
            $canvas->drawText($col, $row++, $dirText);
            $row++;
        }

        // Files
        if (! empty($this->context['files'])) {
            $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
            $filesHeaderText = ($isSelected ? "\033[7m" : '') . "\033[33mFiles:\033[0m";
            $canvas->drawText($col, $row++, $filesHeaderText);

            foreach ($this->context['files'] as $file) {
                if ($row >= $this->bounds->y + $this->bounds->height - 8) {
                    break;
                }
                $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
                $fileText = ($isSelected ? "\033[7m" : '') . '  ' . $this->truncate($file, $sidebarWidth - 5) . "\033[0m";
                $canvas->drawText($col, $row++, $fileText);
            }
        }

        // Notes
        if ($row < $this->bounds->y + $this->bounds->height - 6) {
            $row++;
            $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
            $notesHeaderText = ($isSelected ? "\033[7m" : '') . "\033[35mNotes:\033[0m";
            $canvas->drawText($col, $row++, $notesHeaderText);

            foreach ($this->context['notes'] as $i => $note) {
                if ($row >= $this->bounds->y + $this->bounds->height - 4) {
                    break;
                }
                $isSelected = $contextActive && $this->selectedContextLine === $contextLine++;
                $noteText = ($isSelected ? "\033[7m" : '') . '  • ' . $this->truncate($note, $sidebarWidth - 6) . "\033[0m";
                $canvas->drawText($col, $row++, $noteText);
            }

            // Input line for new notes
            if ($contextActive && $row < $this->bounds->y + $this->bounds->height - 2) {
                $inputText = '  + ' . $this->contextInput;
                $canvas->drawText($col, $row++, $inputText);
                // Note: Cursor positioning would need to be handled at a higher level since Canvas doesn't handle cursor directly
            }
        }
    }

    /**
     * Build compact task line (returns string instead of echoing)
     */
    protected function buildCompactTaskLine(array $task, int $number, int $maxWidth): string
    {
        $status = $task['status'] ?? 'pending';
        $icon = match ($status) {
            'completed' => "\033[32m✓", // GREEN
            'running' => "\033[33m▶", // YELLOW
            'pending' => "\033[2m○", // DIM
            'failed' => "\033[31m✗", // RED
            default => ' '
        };

        $num = mb_str_pad($number . '.', 3);
        $desc = $this->truncate($task['description'] ?? '', $maxWidth - 6);

        $line = "{$num} {$icon} \033[0m{$desc}";

        if ($status === 'running' && ($task['steps'] ?? 0) > 0) {
            $percent = round((($task['completed_steps'] ?? 0) / $task['steps']) * 100);
            $line .= ' ' . "\033[2m{$percent}%\033[0m"; // DIM
        }

        return $line;
    }

    /**
     * Truncate text to fit width
     */
    protected function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }

    /**
     * Handle task list navigation
     */
    protected function handleTasksInput(string $key): bool
    {
        switch ($key) {
            case 'UP':
            case 'k':
                if ($this->selectedTaskIndex > 0) {
                    $this->selectedTaskIndex--;
                    $this->markNeedsRepaint();
                }

                return true;
            case 'DOWN':
            case 'j':
                if ($this->selectedTaskIndex < count($this->tasks) - 1) {
                    $this->selectedTaskIndex++;
                    $this->markNeedsRepaint();
                }

                return true;
            case "\n": // Enter - select task
                if (isset($this->tasks[$this->selectedTaskIndex])) {
                    // Task selection logic would go here
                    return true;
                }
                break;
        }

        // Number keys for quick jump
        if (mb_strlen($key) === 1 && $key >= '1' && $key <= '9') {
            $index = intval($key) - 1;
            if ($index < count($this->tasks)) {
                $this->selectedTaskIndex = $index;
                $this->markNeedsRepaint();

                return true;
            }
        }

        return false;
    }

    /**
     * Handle context area navigation
     */
    protected function handleContextInput(string $key): bool
    {
        switch ($key) {
            case 'UP':
                if ($this->selectedContextLine > 0) {
                    $this->selectedContextLine--;
                    $this->markNeedsRepaint();
                }

                return true;
            case 'DOWN':
                $totalLines = 3 + count($this->context['files']) + count($this->context['notes']) + 2;
                if ($this->selectedContextLine < $totalLines - 1) {
                    $this->selectedContextLine++;
                    $this->markNeedsRepaint();
                }

                return true;
            case "\n": // Enter - add note
                if (! empty($this->contextInput)) {
                    $this->context['notes'][] = $this->contextInput;
                    $this->contextInput = '';
                    $this->markNeedsRepaint();

                    return true;
                }
                break;
            case "\177": // Backspace
            case "\010":
                // If we're on a note line, delete the note
                $noteStart = 3 + count($this->context['files']) + 1;
                $noteIndex = $this->selectedContextLine - $noteStart;
                if ($noteIndex >= 0 && $noteIndex < count($this->context['notes'])) {
                    array_splice($this->context['notes'], $noteIndex, 1);
                    if ($this->selectedContextLine > 0) {
                        $this->selectedContextLine--;
                    }
                    $this->markNeedsRepaint();
                } elseif (mb_strlen($this->contextInput) > 0) {
                    $this->contextInput = mb_substr($this->contextInput, 0, -1);
                    $this->markNeedsRepaint();
                }

                return true;
        }

        // Regular text input for notes
        if (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
            $this->contextInput .= $key;
            $this->markNeedsRepaint();

            return true;
        }

        return false;
    }
}
