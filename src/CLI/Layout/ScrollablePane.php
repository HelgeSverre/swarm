<?php

namespace HelgeSverre\Swarm\CLI\Layout;

/**
 * Manages scrollable content within a pane
 */
class ScrollablePane
{
    protected int $scrollOffset = 0;

    protected int $viewportHeight;

    protected int $viewportWidth;

    protected array $content = [];

    protected int $contentHeight = 0;

    public function __construct(int $viewportWidth, int $viewportHeight)
    {
        $this->viewportWidth = $viewportWidth;
        $this->viewportHeight = $viewportHeight;
    }

    /**
     * Set the content lines
     */
    public function setContent(array $lines): void
    {
        $this->content = $lines;
        $this->contentHeight = count($lines);

        // Adjust scroll offset if content shrunk
        if ($this->scrollOffset >= $this->contentHeight) {
            $this->scrollToBottom();
        }
    }

    /**
     * Get visible lines based on current scroll position
     */
    public function getVisibleLines(): array
    {
        if (empty($this->content)) {
            return [];
        }

        $startLine = $this->scrollOffset;
        $endLine = min($startLine + $this->viewportHeight, $this->contentHeight);

        return array_slice($this->content, $startLine, $endLine - $startLine);
    }

    /**
     * Scroll up by n lines
     */
    public function scrollUp(int $lines = 1): bool
    {
        if ($this->scrollOffset <= 0) {
            return false;
        }

        $this->scrollOffset = max(0, $this->scrollOffset - $lines);

        return true;
    }

    /**
     * Scroll down by n lines
     */
    public function scrollDown(int $lines = 1): bool
    {
        $maxScroll = max(0, $this->contentHeight - $this->viewportHeight);

        if ($this->scrollOffset >= $maxScroll) {
            return false;
        }

        $this->scrollOffset = min($maxScroll, $this->scrollOffset + $lines);

        return true;
    }

    /**
     * Scroll to top
     */
    public function scrollToTop(): void
    {
        $this->scrollOffset = 0;
    }

    /**
     * Scroll to bottom
     */
    public function scrollToBottom(): void
    {
        $this->scrollOffset = max(0, $this->contentHeight - $this->viewportHeight);
    }

    /**
     * Page up (scroll by viewport height)
     */
    public function pageUp(): bool
    {
        return $this->scrollUp($this->viewportHeight);
    }

    /**
     * Page down (scroll by viewport height)
     */
    public function pageDown(): bool
    {
        return $this->scrollDown($this->viewportHeight);
    }

    /**
     * Check if scrolled to top
     */
    public function isAtTop(): bool
    {
        return $this->scrollOffset === 0;
    }

    /**
     * Check if scrolled to bottom
     */
    public function isAtBottom(): bool
    {
        $maxScroll = max(0, $this->contentHeight - $this->viewportHeight);

        return $this->scrollOffset >= $maxScroll;
    }

    /**
     * Get scroll position info for display
     */
    public function getScrollInfo(): array
    {
        $maxScroll = max(0, $this->contentHeight - $this->viewportHeight);
        $percentage = $maxScroll > 0 ? round(($this->scrollOffset / $maxScroll) * 100) : 100;

        return [
            'offset' => $this->scrollOffset,
            'total' => $this->contentHeight,
            'visible' => $this->viewportHeight,
            'percentage' => $percentage,
            'canScrollUp' => ! $this->isAtTop(),
            'canScrollDown' => ! $this->isAtBottom(),
        ];
    }

    /**
     * Update viewport dimensions
     */
    public function updateViewport(int $width, int $height): void
    {
        $this->viewportWidth = $width;
        $this->viewportHeight = $height;

        // Adjust scroll if needed
        if ($this->scrollOffset > 0) {
            $maxScroll = max(0, $this->contentHeight - $this->viewportHeight);
            $this->scrollOffset = min($this->scrollOffset, $maxScroll);
        }
    }

    /**
     * Add line to content and optionally auto-scroll
     */
    public function addLine(string $line, bool $autoScroll = true): void
    {
        $this->content[] = $line;
        $this->contentHeight++;

        if ($autoScroll && $this->isAtBottom()) {
            $this->scrollToBottom();
        }
    }
}
