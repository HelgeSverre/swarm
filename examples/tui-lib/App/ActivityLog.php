<?php

declare(strict_types=1);

namespace Examples\TuiLib\App;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;

/**
 * Activity log widget for displaying a scrollable list of activities
 */
class ActivityLog extends Widget
{
    protected array $activities = [];

    protected int $scrollOffset = 0;

    protected int $selectedIndex = -1;

    protected bool $showTimestamps = true;

    protected int $maxItems = 100;

    public function __construct(?string $id = null)
    {
        parent::__construct($id);
        $this->focusable = true;
    }

    /**
     * Set the activities to display
     *
     * @param Activity[] $activities
     */
    public function setActivities(array $activities): void
    {
        $this->activities = array_slice($activities, 0, $this->maxItems);

        // Reset scroll and selection if activities changed
        $this->scrollOffset = 0;
        $this->selectedIndex = -1;

        $this->markNeedsRepaint();
    }

    /**
     * Add a new activity to the top of the list
     */
    public function addActivity(Activity $activity): void
    {
        array_unshift($this->activities, $activity);
        $this->activities = array_slice($this->activities, 0, $this->maxItems);
        $this->markNeedsRepaint();
    }

    /**
     * Get all activities
     *
     * @return Activity[]
     */
    public function getActivities(): array
    {
        return $this->activities;
    }

    /**
     * Set whether to show timestamps
     */
    public function setShowTimestamps(bool $show): void
    {
        if ($this->showTimestamps !== $show) {
            $this->showTimestamps = $show;
            $this->markNeedsRepaint();
        }
    }

    /**
     * Set maximum number of items to keep
     */
    public function setMaxItems(int $maxItems): void
    {
        if ($this->maxItems !== $maxItems) {
            $this->maxItems = $maxItems;
            $this->activities = array_slice($this->activities, 0, $maxItems);
            $this->markNeedsRepaint();
        }
    }

    /**
     * Clear all activities
     */
    public function clear(): void
    {
        $this->activities = [];
        $this->scrollOffset = 0;
        $this->selectedIndex = -1;
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
        if ($this->bounds === null || count($this->activities) === 0) {
            return '';
        }

        $output = '';
        $width = $this->bounds->width;
        $height = $this->bounds->height;
        $hasFocus = $context->hasFocus;

        // Calculate visible range
        $visibleCount = $height;
        $maxScroll = max(0, count($this->activities) - $visibleCount);
        $this->scrollOffset = max(0, min($this->scrollOffset, $maxScroll));

        $visibleActivities = array_slice(
            $this->activities,
            $this->scrollOffset,
            $visibleCount
        );

        foreach ($visibleActivities as $index => $activity) {
            $actualIndex = $this->scrollOffset + $index;
            $isSelected = $hasFocus && $actualIndex === $this->selectedIndex;

            $line = $this->formatActivityLine($activity, $width, $isSelected);

            // Position cursor and write line
            $y = $this->bounds->y + $index;
            $output .= "\033[{$y};{$this->bounds->x}H" . $line;
        }

        // Add scrollbar if needed
        if (count($this->activities) > $height) {
            $output .= $this->renderScrollbar($height);
        }

        $this->clearRepaintFlag();

        return $output;
    }

    public function handleKeyEvent(string $key): bool
    {
        $oldOffset = $this->scrollOffset;
        $oldSelected = $this->selectedIndex;

        switch ($key) {
            case "\033[A": // Up arrow
                if ($this->selectedIndex > 0) {
                    $this->selectedIndex--;
                    if ($this->selectedIndex < $this->scrollOffset) {
                        $this->scrollOffset = $this->selectedIndex;
                    }
                } elseif ($this->selectedIndex === -1 && count($this->activities) > 0) {
                    $this->selectedIndex = 0;
                }
                break;
            case "\033[B": // Down arrow
                if ($this->selectedIndex < count($this->activities) - 1) {
                    $this->selectedIndex++;
                    $visibleHeight = $this->bounds?->height ?? 10;
                    if ($this->selectedIndex >= $this->scrollOffset + $visibleHeight) {
                        $this->scrollOffset = $this->selectedIndex - $visibleHeight + 1;
                    }
                } elseif ($this->selectedIndex === -1 && count($this->activities) > 0) {
                    $this->selectedIndex = 0;
                }
                break;
            case "\033[5~": // Page Up
                $pageSize = max(1, ($this->bounds?->height ?? 10) - 1);
                $this->scrollOffset = max(0, $this->scrollOffset - $pageSize);
                if ($this->selectedIndex !== -1) {
                    $this->selectedIndex = max(0, $this->selectedIndex - $pageSize);
                }
                break;
            case "\033[6~": // Page Down
                $pageSize = max(1, ($this->bounds?->height ?? 10) - 1);
                $maxScroll = max(0, count($this->activities) - ($this->bounds?->height ?? 10));
                $this->scrollOffset = min($maxScroll, $this->scrollOffset + $pageSize);
                if ($this->selectedIndex !== -1) {
                    $this->selectedIndex = min(count($this->activities) - 1, $this->selectedIndex + $pageSize);
                }
                break;
            case "\033[1~": // Home
                $this->scrollOffset = 0;
                $this->selectedIndex = count($this->activities) > 0 ? 0 : -1;
                break;
            case "\033[4~": // End
                $visibleHeight = $this->bounds?->height ?? 10;
                $this->scrollOffset = max(0, count($this->activities) - $visibleHeight);
                $this->selectedIndex = count($this->activities) > 0 ? count($this->activities) - 1 : -1;
                break;
            case 'c': // Clear log
                $this->clear();

                return true;
            case 't': // Toggle timestamps
                $this->setShowTimestamps(! $this->showTimestamps);

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
     * Get the currently selected activity
     */
    public function getSelectedActivity(): ?Activity
    {
        if ($this->selectedIndex >= 0 && $this->selectedIndex < count($this->activities)) {
            return $this->activities[$this->selectedIndex];
        }

        return null;
    }

    /**
     * Scroll to show the most recent activity
     */
    public function scrollToTop(): void
    {
        $this->scrollOffset = 0;
        $this->selectedIndex = count($this->activities) > 0 ? 0 : -1;
        $this->markNeedsRepaint();
    }

    /**
     * Scroll to show the oldest activity
     */
    public function scrollToBottom(): void
    {
        $visibleHeight = $this->bounds?->height ?? 10;
        $this->scrollOffset = max(0, count($this->activities) - $visibleHeight);
        $this->selectedIndex = count($this->activities) > 0 ? count($this->activities) - 1 : -1;
        $this->markNeedsRepaint();
    }

    /**
     * Format a single activity line
     */
    protected function formatActivityLine(Activity $activity, int $width, bool $isSelected): string
    {
        $icon = $activity->getIcon();
        $color = $activity->getColor();
        $message = $activity->message;

        // Calculate available width for message
        $prefixWidth = 2; // Icon + space
        if ($this->showTimestamps) {
            $timestamp = $activity->timestamp->format('H:i:s');
            $prefixWidth += mb_strlen($timestamp) + 1; // timestamp + space
        }

        $messageWidth = max(1, $width - $prefixWidth);

        // Truncate message if too long
        if (mb_strlen($message) > $messageWidth) {
            $message = mb_substr($message, 0, $messageWidth - 1) . '…';
        }

        // Build the line
        $line = '';

        // Add background for selected item
        if ($isSelected) {
            $line .= "\033[7m"; // Reverse video
        }

        // Add timestamp if enabled
        if ($this->showTimestamps) {
            $timestamp = $activity->timestamp->format('H:i:s');
            $line .= "\033[90m{$timestamp}\033[0m ";
            if ($isSelected) {
                $line .= "\033[7m"; // Re-apply reverse video after reset
            }
        }

        // Add colored icon
        $line .= "\033[{$color}m{$icon}\033[0m ";
        if ($isSelected) {
            $line .= "\033[7m"; // Re-apply reverse video after reset
        }

        // Add message
        $line .= $message;

        // Pad to full width and reset formatting
        $currentLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $line));
        $padding = max(0, $width - $currentLength);
        $line .= str_repeat(' ', $padding);
        $line .= "\033[0m"; // Reset all formatting

        return $line;
    }

    /**
     * Render scrollbar indicator
     */
    protected function renderScrollbar(int $height): string
    {
        if (count($this->activities) <= $height) {
            return '';
        }

        $output = '';
        $scrollbarX = $this->bounds->x + $this->bounds->width - 1;

        // Calculate scrollbar position
        $totalItems = count($this->activities);
        $thumbSize = max(1, intval($height * $height / $totalItems));
        $thumbPosition = intval($this->scrollOffset * ($height - $thumbSize) / max(1, $totalItems - $height));

        for ($i = 0; $i < $height; $i++) {
            $y = $this->bounds->y + $i;
            $char = ($i >= $thumbPosition && $i < $thumbPosition + $thumbSize) ? '█' : '░';
            $output .= "\033[{$y};{$scrollbarX}H\033[90m{$char}\033[0m";
        }

        return $output;
    }
}
