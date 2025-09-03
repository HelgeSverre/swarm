<?php

declare(strict_types=1);

namespace Examples\TuiLib\App;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;

/**
 * Task list widget for displaying and managing tasks
 */
class TaskList extends Widget
{
    protected array $tasks = [];

    protected int $scrollOffset = 0;

    protected int $selectedIndex = -1;

    protected bool $showDescriptions = false;

    protected array $statusFilter = [];

    protected string $title = 'Tasks';

    public function __construct(?string $id = null)
    {
        parent::__construct($id);
        $this->focusable = true;
    }

    /**
     * Set the tasks to display
     *
     * @param Task[] $tasks
     */
    public function setTasks(array $tasks): void
    {
        $this->tasks = $tasks;

        // Reset scroll and selection if tasks changed
        $this->scrollOffset = 0;
        $this->selectedIndex = -1;

        $this->markNeedsRepaint();
    }

    /**
     * Add a new task
     */
    public function addTask(Task $task): void
    {
        $this->tasks[] = $task;
        $this->markNeedsRepaint();
    }

    /**
     * Get all tasks
     *
     * @return Task[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get filtered tasks based on status filter
     *
     * @return Task[]
     */
    public function getFilteredTasks(): array
    {
        if (empty($this->statusFilter)) {
            return $this->tasks;
        }

        return array_filter($this->tasks, fn (Task $task) => in_array($task->status, $this->statusFilter, true)
        );
    }

    /**
     * Set status filter
     *
     * @param TaskStatus[] $statuses
     */
    public function setStatusFilter(array $statuses): void
    {
        $this->statusFilter = $statuses;
        $this->scrollOffset = 0;
        $this->selectedIndex = -1;
        $this->markNeedsRepaint();
    }

    /**
     * Clear status filter
     */
    public function clearStatusFilter(): void
    {
        $this->statusFilter = [];
        $this->markNeedsRepaint();
    }

    /**
     * Set whether to show task descriptions
     */
    public function setShowDescriptions(bool $show): void
    {
        if ($this->showDescriptions !== $show) {
            $this->showDescriptions = $show;
            $this->markNeedsRepaint();
        }
    }

    /**
     * Set the widget title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->markNeedsRepaint();
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        return $constraints->biggest();
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

        $output = '';
        $width = $this->bounds->width;
        $height = $this->bounds->height;
        $hasFocus = $context->hasFocus;

        // Render title bar
        $titleLine = $this->renderTitleBar($width, $hasFocus);
        $output .= "\033[{$this->bounds->y};{$this->bounds->x}H" . $titleLine;

        // Calculate content area (subtract title bar)
        $contentHeight = max(0, $height - 1);
        $filteredTasks = $this->getFilteredTasks();

        if ($contentHeight === 0 || count($filteredTasks) === 0) {
            if ($contentHeight > 0 && count($filteredTasks) === 0) {
                $emptyMessage = count($this->tasks) === 0 ? 'No tasks' : 'No tasks match filter';
                $centeredMessage = $this->centerText($emptyMessage, $width);
                $y = $this->bounds->y + 1 + intval($contentHeight / 2);
                $output .= "\033[{$y};{$this->bounds->x}H\033[90m{$centeredMessage}\033[0m";
            }

            return $output;
        }

        // Calculate visible range
        $itemHeight = $this->showDescriptions ? 2 : 1;
        $visibleCount = intval($contentHeight / $itemHeight);
        $maxScroll = max(0, count($filteredTasks) - $visibleCount);
        $this->scrollOffset = max(0, min($this->scrollOffset, $maxScroll));

        $visibleTasks = array_slice($filteredTasks, $this->scrollOffset, $visibleCount);

        foreach ($visibleTasks as $index => $task) {
            $actualIndex = $this->scrollOffset + $index;
            $isSelected = $hasFocus && $actualIndex === $this->selectedIndex;

            $taskLines = $this->formatTaskLines($task, $width, $isSelected);

            // Position and render task lines
            $startY = $this->bounds->y + 1 + ($index * $itemHeight);
            foreach ($taskLines as $lineIndex => $line) {
                $y = $startY + $lineIndex;
                if ($y < $this->bounds->y + $height) {
                    $output .= "\033[{$y};{$this->bounds->x}H" . $line;
                }
            }
        }

        // Add scrollbar if needed
        if (count($filteredTasks) > $visibleCount) {
            $output .= $this->renderScrollbar($contentHeight);
        }

        $this->clearRepaintFlag();

        return $output;
    }

    public function handleKeyEvent(string $key): bool
    {
        $filteredTasks = $this->getFilteredTasks();
        $oldOffset = $this->scrollOffset;
        $oldSelected = $this->selectedIndex;

        switch ($key) {
            case "\033[A": // Up arrow
                if ($this->selectedIndex > 0) {
                    $this->selectedIndex--;
                    if ($this->selectedIndex < $this->scrollOffset) {
                        $this->scrollOffset = $this->selectedIndex;
                    }
                } elseif ($this->selectedIndex === -1 && count($filteredTasks) > 0) {
                    $this->selectedIndex = 0;
                }
                break;
            case "\033[B": // Down arrow
                if ($this->selectedIndex < count($filteredTasks) - 1) {
                    $this->selectedIndex++;
                    $itemHeight = $this->showDescriptions ? 2 : 1;
                    $visibleCount = intval(max(0, ($this->bounds?->height ?? 10) - 1) / $itemHeight);
                    if ($this->selectedIndex >= $this->scrollOffset + $visibleCount) {
                        $this->scrollOffset = $this->selectedIndex - $visibleCount + 1;
                    }
                } elseif ($this->selectedIndex === -1 && count($filteredTasks) > 0) {
                    $this->selectedIndex = 0;
                }
                break;
            case "\033[5~": // Page Up
                $itemHeight = $this->showDescriptions ? 2 : 1;
                $visibleCount = max(1, intval(max(0, ($this->bounds?->height ?? 10) - 1) / $itemHeight));
                $pageSize = max(1, $visibleCount - 1);
                $this->scrollOffset = max(0, $this->scrollOffset - $pageSize);
                if ($this->selectedIndex !== -1) {
                    $this->selectedIndex = max(0, $this->selectedIndex - $pageSize);
                }
                break;
            case "\033[6~": // Page Down
                $itemHeight = $this->showDescriptions ? 2 : 1;
                $visibleCount = max(1, intval(max(0, ($this->bounds?->height ?? 10) - 1) / $itemHeight));
                $pageSize = max(1, $visibleCount - 1);
                $maxScroll = max(0, count($filteredTasks) - $visibleCount);
                $this->scrollOffset = min($maxScroll, $this->scrollOffset + $pageSize);
                if ($this->selectedIndex !== -1) {
                    $this->selectedIndex = min(count($filteredTasks) - 1, $this->selectedIndex + $pageSize);
                }
                break;
            case "\033[1~": // Home
                $this->scrollOffset = 0;
                $this->selectedIndex = count($filteredTasks) > 0 ? 0 : -1;
                break;
            case "\033[4~": // End
                $itemHeight = $this->showDescriptions ? 2 : 1;
                $visibleCount = max(1, intval(max(0, ($this->bounds?->height ?? 10) - 1) / $itemHeight));
                $this->scrollOffset = max(0, count($filteredTasks) - $visibleCount);
                $this->selectedIndex = count($filteredTasks) > 0 ? count($filteredTasks) - 1 : -1;
                break;
            case 'd': // Toggle descriptions
                $this->setShowDescriptions(! $this->showDescriptions);

                return true;
            case 'f': // Filter by status
                $this->cycleStatusFilter();

                return true;
            case 'r': // Reset filter
                $this->clearStatusFilter();

                return true;
            default:
                return false;
        }

        // Repaint if anything changed
        if ($this->scrollOffset !== $oldOffset || $this->selectedIndex !== $oldSelected) {
            $this->markNeedsRepaint();

            return true;
        }

        return false;
    }

    public function handleFocusChange(bool $focused): void
    {
        if (! $focused) {
            $this->selectedIndex = -1;
        }
        parent::handleFocusChange($focused);
    }

    /**
     * Get the currently selected task
     */
    public function getSelectedTask(): ?Task
    {
        $filteredTasks = $this->getFilteredTasks();
        if ($this->selectedIndex >= 0 && $this->selectedIndex < count($filteredTasks)) {
            return $filteredTasks[$this->selectedIndex];
        }

        return null;
    }

    /**
     * Get task statistics
     */
    public function getTaskStats(): array
    {
        $stats = [
            'total' => count($this->tasks),
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];

        foreach ($this->tasks as $task) {
            $stats[$task->status->value]++;
        }

        return $stats;
    }

    /**
     * Render the title bar
     */
    protected function renderTitleBar(int $width, bool $hasFocus): string
    {
        $filteredTasks = $this->getFilteredTasks();
        $totalTasks = count($this->tasks);
        $filteredCount = count($filteredTasks);

        $title = $this->title;
        if (! empty($this->statusFilter)) {
            $statusNames = array_map(fn ($s) => $s->value, $this->statusFilter);
            $title .= ' (' . implode(', ', $statusNames) . ')';
        }

        $countText = $filteredCount !== $totalTasks
            ? " {$filteredCount}/{$totalTasks}"
            : " {$totalTasks}";

        // Calculate available space
        $availableWidth = max(1, $width - mb_strlen($countText));
        if (mb_strlen($title) > $availableWidth) {
            $title = mb_substr($title, 0, $availableWidth - 1) . '…';
        }

        $line = $title . str_repeat(' ', max(0, $width - mb_strlen($title) - mb_strlen($countText))) . $countText;

        // Style the title bar
        $style = $hasFocus ? "\033[7;1m" : "\033[100;37m";

        return $style . $line . "\033[0m";
    }

    /**
     * Format task lines (title and optionally description)
     *
     * @return string[]
     */
    protected function formatTaskLines(Task $task, int $width, bool $isSelected): array
    {
        $lines = [];

        // Format title line
        $icon = $task->getStatusIcon();
        $color = $task->getStatusColor();
        $title = $task->title;

        // Calculate available width for title
        $prefixWidth = 2; // Icon + space
        $titleWidth = max(1, $width - $prefixWidth);

        if (mb_strlen($title) > $titleWidth) {
            $title = mb_substr($title, 0, $titleWidth - 1) . '…';
        }

        // Build title line
        $titleLine = '';
        if ($isSelected) {
            $titleLine .= "\033[7m";
        }

        $titleLine .= "\033[{$color}m{$icon}\033[0m ";
        if ($isSelected) {
            $titleLine .= "\033[7m";
        }

        $titleLine .= $title;

        // Pad to full width
        $currentLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $titleLine));
        $padding = max(0, $width - $currentLength);
        $titleLine .= str_repeat(' ', $padding);
        $titleLine .= "\033[0m";

        $lines[] = $titleLine;

        // Add description line if enabled
        if ($this->showDescriptions) {
            $description = $task->description;
            if (mb_strlen($description) > $width - 2) {
                $description = mb_substr($description, 0, $width - 3) . '…';
            }

            $descLine = '  ' . $description;
            $descLine .= str_repeat(' ', max(0, $width - mb_strlen($descLine)));

            if ($isSelected) {
                $descLine = "\033[7m" . $descLine . "\033[0m";
            } else {
                $descLine = "\033[90m" . $descLine . "\033[0m";
            }

            $lines[] = $descLine;
        }

        return $lines;
    }

    /**
     * Render scrollbar indicator
     */
    protected function renderScrollbar(int $height): string
    {
        $filteredTasks = $this->getFilteredTasks();
        $itemHeight = $this->showDescriptions ? 2 : 1;
        $visibleCount = intval($height / $itemHeight);

        if (count($filteredTasks) <= $visibleCount) {
            return '';
        }

        $output = '';
        $scrollbarX = $this->bounds->x + $this->bounds->width - 1;

        // Calculate scrollbar position
        $totalItems = count($filteredTasks);
        $thumbSize = max(1, intval($height * $visibleCount / $totalItems));
        $thumbPosition = intval($this->scrollOffset * ($height - $thumbSize) / max(1, $totalItems - $visibleCount));

        for ($i = 0; $i < $height; $i++) {
            $y = $this->bounds->y + 1 + $i; // +1 for title bar
            $char = ($i >= $thumbPosition && $i < $thumbPosition + $thumbSize) ? '█' : '░';
            $output .= "\033[{$y};{$scrollbarX}H\033[90m{$char}\033[0m";
        }

        return $output;
    }

    /**
     * Cycle through status filters
     */
    protected function cycleStatusFilter(): void
    {
        $allStatuses = TaskStatus::cases();

        if (empty($this->statusFilter)) {
            // Start with pending tasks
            $this->setStatusFilter([TaskStatus::Pending]);
        } elseif ($this->statusFilter === [TaskStatus::Pending]) {
            // Show in-progress tasks
            $this->setStatusFilter([TaskStatus::InProgress]);
        } elseif ($this->statusFilter === [TaskStatus::InProgress]) {
            // Show completed tasks
            $this->setStatusFilter([TaskStatus::Completed]);
        } elseif ($this->statusFilter === [TaskStatus::Completed]) {
            // Show failed tasks
            $this->setStatusFilter([TaskStatus::Failed]);
        } else {
            // Back to all tasks
            $this->clearStatusFilter();
        }
    }

    /**
     * Center text within given width
     */
    protected function centerText(string $text, int $width): string
    {
        $textLength = mb_strlen($text);
        if ($textLength >= $width) {
            return mb_substr($text, 0, $width);
        }

        $padding = ($width - $textLength) / 2;
        $leftPadding = intval($padding);
        $rightPadding = $width - $textLength - $leftPadding;

        return str_repeat(' ', $leftPadding) . $text . str_repeat(' ', $rightPadding);
    }
}
