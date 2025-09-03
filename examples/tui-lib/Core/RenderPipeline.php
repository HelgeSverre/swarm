<?php

declare(strict_types=1);

namespace Examples\TuiLib\Core;

use Throwable;

/**
 * Represents a region that needs to be redrawn
 */
readonly class DirtyRegion
{
    public function __construct(
        public Rect $rect,
        public int $timestamp,
    ) {}

    public function intersects(Rect $other): bool
    {
        return $this->rect->intersects($other);
    }

    public function merge(DirtyRegion $other): DirtyRegion
    {
        $x = min($this->rect->x, $other->rect->x);
        $y = min($this->rect->y, $other->rect->y);
        $right = max($this->rect->right(), $other->rect->right());
        $bottom = max($this->rect->bottom(), $other->rect->bottom());

        return new DirtyRegion(
            new Rect($x, $y, $right - $x, $bottom - $y),
            max($this->timestamp, $other->timestamp)
        );
    }
}

/**
 * Represents the three phases of the render pipeline
 */
enum RenderPhase: string
{
    case Build = 'build';
    case Layout = 'layout';
    case Paint = 'paint';
}

/**
 * Main rendering pipeline for the terminal UI framework
 *
 * Manages the complete widget tree rendering process through three phases:
 * 1. Build - Construct the widget tree
 * 2. Layout - Calculate positions and sizes
 * 3. Paint - Draw widgets to the canvas
 *
 * Features:
 * - Dirty region tracking for efficient updates
 * - Terminal size change handling
 * - Canvas flushing to terminal output
 * - Performance metrics tracking
 */
class RenderPipeline
{
    protected Canvas $canvas;

    protected Size $terminalSize;

    protected array $dirtyRegions = [];

    protected bool $fullRedrawNeeded = true;

    protected int $frameCount = 0;

    protected float $lastRenderTime = 0.0;

    protected array $renderMetrics = [];

    public function __construct(
        ?Size $initialSize = null,
    ) {
        $this->terminalSize = $initialSize ?? $this->detectTerminalSize();
        $this->canvas = new Canvas($this->terminalSize);
    }

    /**
     * Execute the complete render pipeline for a widget tree
     */
    public function render(object $rootWidget): string
    {
        $startTime = microtime(true);

        try {
            // Phase 1: Build - Ensure widget tree is up to date
            $this->executeBuildPhase($rootWidget);

            // Phase 2: Layout - Calculate positions and sizes
            $this->executeLayoutPhase($rootWidget);

            // Phase 3: Paint - Draw widgets to canvas
            $this->executePaintPhase($rootWidget);

            // Flush canvas to string output
            $output = $this->flushCanvas();

            $this->recordRenderMetrics($startTime);
            $this->frameCount++;

            return $output;
        } catch (Throwable $e) {
            // Fallback to full redraw on error
            $this->markFullRedraw();
            throw $e;
        }
    }

    /**
     * Mark a region as dirty for the next render
     */
    public function markDirty(Rect $rect): void
    {
        $timestamp = hrtime(true);
        $newRegion = new DirtyRegion($rect, $timestamp);

        // Try to merge with existing dirty regions
        $merged = false;
        foreach ($this->dirtyRegions as $index => $existing) {
            if ($existing->intersects($rect)) {
                $this->dirtyRegions[$index] = $existing->merge($newRegion);
                $merged = true;
                break;
            }
        }

        if (! $merged) {
            $this->dirtyRegions[] = $newRegion;
        }

        // Limit number of dirty regions to prevent fragmentation
        if (count($this->dirtyRegions) > 10) {
            $this->markFullRedraw();
        }
    }

    /**
     * Mark the entire canvas for redraw
     */
    public function markFullRedraw(): void
    {
        $this->fullRedrawNeeded = true;
        $this->dirtyRegions = [];
    }

    /**
     * Handle terminal size changes
     */
    public function handleTerminalResize(?Size $newSize = null): bool
    {
        $newSize = $newSize ?? $this->detectTerminalSize();

        if ($newSize->width === $this->terminalSize->width &&
            $newSize->height === $this->terminalSize->height) {
            return false;
        }

        $this->terminalSize = $newSize;
        $this->canvas = new Canvas($this->terminalSize);
        $this->markFullRedraw();

        return true;
    }

    /**
     * Get the current canvas
     */
    public function getCanvas(): Canvas
    {
        return $this->canvas;
    }

    /**
     * Get the current terminal size
     */
    public function getTerminalSize(): Size
    {
        return $this->terminalSize;
    }

    /**
     * Check if a full redraw is needed
     */
    public function needsFullRedraw(): bool
    {
        return $this->fullRedrawNeeded;
    }

    /**
     * Get current dirty regions
     */
    public function getDirtyRegions(): array
    {
        return $this->dirtyRegions;
    }

    /**
     * Get rendering performance metrics
     */
    public function getRenderMetrics(): array
    {
        return $this->renderMetrics;
    }

    /**
     * Get current frame count
     */
    public function getFrameCount(): int
    {
        return $this->frameCount;
    }

    /**
     * Clear all dirty regions
     */
    public function clearDirtyRegions(): void
    {
        $this->dirtyRegions = [];
        $this->fullRedrawNeeded = false;
    }

    /**
     * Execute the build phase
     */
    protected function executeBuildPhase(object $rootWidget): void
    {
        // Build phase would typically:
        // 1. Update widget states
        // 2. Handle any pending state changes
        // 3. Rebuild any widgets that need rebuilding

        if (method_exists($rootWidget, 'build')) {
            $rootWidget->build();
        }
    }

    /**
     * Execute the layout phase
     */
    protected function executeLayoutPhase(object $rootWidget): void
    {
        // Layout phase would typically:
        // 1. Calculate constraints from parent to child
        // 2. Calculate sizes from child to parent
        // 3. Position widgets based on layout rules

        $constraints = new Constraints(
            maxWidth: $this->terminalSize->width,
            maxHeight: $this->terminalSize->height,
        );

        if (method_exists($rootWidget, 'layout')) {
            $rootWidget->layout($constraints);
        }
    }

    /**
     * Execute the paint phase
     */
    protected function executePaintPhase(object $rootWidget): void
    {
        // Clear canvas if full redraw is needed
        if ($this->fullRedrawNeeded) {
            $this->canvas->clear();
        } else {
            // Clear only dirty regions
            foreach ($this->dirtyRegions as $region) {
                $this->canvas->fillRect($region->rect);
            }
        }

        // Paint the widget tree
        if (method_exists($rootWidget, 'paint')) {
            $rootWidget->paint($this->canvas);
        }
    }

    /**
     * Flush the canvas to string output
     */
    protected function flushCanvas(): string
    {
        $output = $this->canvas->render();
        $this->clearDirtyRegions();

        return $output;
    }

    /**
     * Detect current terminal size
     */
    protected function detectTerminalSize(): Size
    {
        // Try to get terminal size from environment
        $width = (int) ($_ENV['COLUMNS'] ?? 0);
        $height = (int) ($_ENV['LINES'] ?? 0);

        // Fallback to stty if available
        if ($width === 0 || $height === 0) {
            $sttyOutput = shell_exec('stty size 2>/dev/null');
            if ($sttyOutput && preg_match('/(\d+) (\d+)/', trim($sttyOutput), $matches)) {
                $height = (int) $matches[1];
                $width = (int) $matches[2];
            }
        }

        // Fallback to tput if available
        if ($width === 0 || $height === 0) {
            $width = $width ?: (int) shell_exec('tput cols 2>/dev/null');
            $height = $height ?: (int) shell_exec('tput lines 2>/dev/null');
        }

        // Final fallback to reasonable defaults
        return new Size(
            width: max($width, 80),
            height: max($height, 24),
        );
    }

    /**
     * Record performance metrics for this render
     */
    protected function recordRenderMetrics(float $startTime): void
    {
        $renderTime = microtime(true) - $startTime;
        $this->lastRenderTime = $renderTime;

        $this->renderMetrics[] = [
            'frame' => $this->frameCount,
            'renderTime' => $renderTime,
            'dirtyRegions' => count($this->dirtyRegions),
            'fullRedraw' => $this->fullRedrawNeeded,
            'timestamp' => microtime(true),
        ];

        // Keep only last 100 metrics
        if (count($this->renderMetrics) > 100) {
            $this->renderMetrics = array_slice($this->renderMetrics, -100);
        }
    }
}
