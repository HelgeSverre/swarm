<?php

namespace HelgeSverre\Swarm\CLI\Layout;

/**
 * Manages the layout calculations for the three-pane UI
 */
class PaneLayout
{
    protected int $terminalWidth;

    protected int $terminalHeight;

    protected int $leftPaneWidth = 20;  // File tree

    protected int $rightPaneWidth = 25; // Context notes

    protected int $headerHeight = 1;

    protected int $footerHeight = 2;

    protected int $inputBoxHeight = 3;

    protected bool $showLeftPane = true;

    protected bool $showRightPane = true;

    public function __construct(int $terminalWidth, int $terminalHeight)
    {
        $this->terminalWidth = $terminalWidth;
        $this->terminalHeight = $terminalHeight;
    }

    /**
     * Get the dimensions for the main content area
     */
    public function getMainAreaDimensions(): array
    {
        $startCol = $this->showLeftPane ? $this->leftPaneWidth + 1 : 1;
        $width = $this->terminalWidth;

        if ($this->showLeftPane) {
            $width -= $this->leftPaneWidth + 1; // +1 for divider
        }

        if ($this->showRightPane) {
            $width -= $this->rightPaneWidth + 1; // +1 for divider
        }

        $startRow = $this->headerHeight + 1;
        $height = $this->terminalHeight - $this->headerHeight - $this->footerHeight - $this->inputBoxHeight;

        return [
            'col' => $startCol,
            'row' => $startRow,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Get the dimensions for the left pane (file tree)
     */
    public function getLeftPaneDimensions(): array
    {
        if (! $this->showLeftPane) {
            return ['col' => 0, 'row' => 0, 'width' => 0, 'height' => 0];
        }

        return [
            'col' => 1,
            'row' => $this->headerHeight + 1,
            'width' => $this->leftPaneWidth,
            'height' => $this->terminalHeight - $this->headerHeight - $this->footerHeight - $this->inputBoxHeight,
        ];
    }

    /**
     * Get the dimensions for the right pane (context)
     */
    public function getRightPaneDimensions(): array
    {
        if (! $this->showRightPane) {
            return ['col' => 0, 'row' => 0, 'width' => 0, 'height' => 0];
        }

        $col = $this->terminalWidth - $this->rightPaneWidth;

        return [
            'col' => $col,
            'row' => $this->headerHeight + 1,
            'width' => $this->rightPaneWidth,
            'height' => $this->terminalHeight - $this->headerHeight - $this->footerHeight - $this->inputBoxHeight,
        ];
    }

    /**
     * Get the column position for the left divider
     */
    public function getLeftDividerCol(): int
    {
        return $this->showLeftPane ? $this->leftPaneWidth + 1 : 0;
    }

    /**
     * Get the column position for the right divider
     */
    public function getRightDividerCol(): int
    {
        return $this->showRightPane ? $this->terminalWidth - $this->rightPaneWidth - 1 : $this->terminalWidth;
    }

    /**
     * Toggle left pane visibility
     */
    public function toggleLeftPane(): void
    {
        $this->showLeftPane = ! $this->showLeftPane;
    }

    /**
     * Toggle right pane visibility
     */
    public function toggleRightPane(): void
    {
        $this->showRightPane = ! $this->showRightPane;
    }

    /**
     * Toggle both sidebars
     */
    public function toggleSidebars(): void
    {
        if ($this->showLeftPane || $this->showRightPane) {
            $this->showLeftPane = false;
            $this->showRightPane = false;
        } else {
            $this->showLeftPane = true;
            $this->showRightPane = true;
        }
    }

    /**
     * Check if sidebars are visible
     */
    public function hasSidebars(): bool
    {
        return $this->showLeftPane || $this->showRightPane;
    }

    /**
     * Update terminal dimensions
     */
    public function updateDimensions(int $width, int $height): void
    {
        $this->terminalWidth = $width;
        $this->terminalHeight = $height;
    }
}
