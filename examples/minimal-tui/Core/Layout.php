<?php

declare(strict_types=1);

namespace MinimalTui\Core;

/**
 * Simple layout management for positioning components
 */
class Layout
{
    protected array $areas = [];

    protected int $width;

    protected int $height;

    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Define a layout area
     */
    public function area(string $name, int $x, int $y, int $width, int $height): self
    {
        $this->areas[$name] = [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ];

        return $this;
    }

    /**
     * Get area dimensions
     */
    public function getArea(string $name): ?array
    {
        return $this->areas[$name] ?? null;
    }

    /**
     * Create a simple grid layout
     */
    public static function grid(int $width, int $height, array $config): self
    {
        $layout = new self($width, $height);

        if (isset($config['sidebar_width'])) {
            $sidebarWidth = $config['sidebar_width'];
            $mainWidth = $width - $sidebarWidth - 1; // -1 for divider

            // Main area (left side)
            $layout->area('main', 1, 1, $mainWidth, $height);

            // Sidebar (right side)
            $layout->area('sidebar', $mainWidth + 2, 1, $sidebarWidth, $height);

            // Status bar can span the full width
            if (isset($config['status_height'])) {
                $statusHeight = $config['status_height'];
                $layout->area('status', 1, 1, $width, $statusHeight);

                // Adjust main and sidebar to be below status
                $layout->area('main', 1, $statusHeight + 1, $mainWidth, $height - $statusHeight);
                $layout->area('sidebar', $mainWidth + 2, $statusHeight + 1, $sidebarWidth, $height - $statusHeight);
            }
        }

        return $layout;
    }

    /**
     * Create a vertical split layout
     */
    public static function vsplit(int $width, int $height, int $leftWidth): self
    {
        $layout = new self($width, $height);
        $rightWidth = $width - $leftWidth - 1; // -1 for divider

        $layout->area('left', 1, 1, $leftWidth, $height);
        $layout->area('right', $leftWidth + 2, 1, $rightWidth, $height);

        return $layout;
    }

    /**
     * Create a horizontal split layout
     */
    public static function hsplit(int $width, int $height, int $topHeight): self
    {
        $layout = new self($width, $height);
        $bottomHeight = $height - $topHeight - 1; // -1 for divider

        $layout->area('top', 1, 1, $width, $topHeight);
        $layout->area('bottom', 1, $topHeight + 2, $width, $bottomHeight);

        return $layout;
    }

    /**
     * Subdivide an existing area
     */
    public function subdivide(string $areaName, array $config): self
    {
        $area = $this->getArea($areaName);
        if (! $area) {
            return $this;
        }

        $x = $area['x'];
        $y = $area['y'];
        $width = $area['width'];
        $height = $area['height'];

        if (isset($config['bottom_height'])) {
            $bottomHeight = $config['bottom_height'];
            $topHeight = $height - $bottomHeight - 1; // -1 for spacing

            $this->area($areaName . '_top', $x, $y, $width, $topHeight);
            $this->area($areaName . '_bottom', $x, $y + $topHeight + 1, $width, $bottomHeight);
        }

        if (isset($config['right_width'])) {
            $rightWidth = $config['right_width'];
            $leftWidth = $width - $rightWidth - 1; // -1 for divider

            $this->area($areaName . '_left', $x, $y, $leftWidth, $height);
            $this->area($areaName . '_right', $x + $leftWidth + 1, $y, $rightWidth, $height);
        }

        return $this;
    }

    /**
     * Get all areas
     */
    public function getAreas(): array
    {
        return $this->areas;
    }

    /**
     * Update layout dimensions (for terminal resize)
     */
    public function resize(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }
}
