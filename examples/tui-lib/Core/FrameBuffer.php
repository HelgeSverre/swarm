<?php

declare(strict_types=1);

namespace Examples\TuiLib\Core;

/**
 * Represents a change between two buffer states
 */
readonly class BufferChange
{
    public function __construct(
        public int $x,
        public int $y,
        public Cell $oldCell,
        public Cell $newCell,
    ) {}
}

/**
 * Double-buffered frame management for efficient terminal rendering
 *
 * Provides delta rendering by tracking changes between frames and only
 * outputting the differences to minimize terminal I/O and eliminate flicker.
 */
class FrameBuffer
{
    protected Canvas $frontBuffer;

    protected Canvas $backBuffer;

    protected array $changes = [];

    protected bool $fullRedrawPending = true;

    protected int $frameNumber = 0;

    protected Size $size;

    public function __construct(Size $size)
    {
        $this->size = $size;
        $this->frontBuffer = new Canvas($size);
        $this->backBuffer = new Canvas($size);

        Logger::debug('FrameBuffer created', [
            'width' => $size->width,
            'height' => $size->height,
        ]);
    }

    /**
     * Get the back buffer for drawing the next frame
     */
    public function getBackBuffer(): Canvas
    {
        return $this->backBuffer;
    }

    /**
     * Get the current front buffer (what's displayed)
     */
    public function getFrontBuffer(): Canvas
    {
        return $this->frontBuffer;
    }

    /**
     * Force a full redraw on the next frame
     */
    public function invalidate(): void
    {
        $this->fullRedrawPending = true;
        Logger::debug('FrameBuffer invalidated - full redraw pending');
    }

    /**
     * Resize the buffers
     */
    public function resize(Size $newSize): void
    {
        if ($newSize->width === $this->size->width && $newSize->height === $this->size->height) {
            return;
        }

        Logger::debug('FrameBuffer resizing', [
            'from' => ['width' => $this->size->width, 'height' => $this->size->height],
            'to' => ['width' => $newSize->width, 'height' => $newSize->height],
        ]);

        $this->size = $newSize;
        $this->frontBuffer = new Canvas($newSize);
        $this->backBuffer = new Canvas($newSize);
        $this->invalidate();
    }

    /**
     * Swap buffers and generate terminal output for changes
     */
    public function present(): string
    {
        Logger::startTimerStatic('frame_present');
        $this->frameNumber++;

        $output = '';

        // For now, always do full redraws to avoid massive character-by-character output
        Logger::debug('Performing full screen redraw');
        $output .= "\033[H"; // Move to home without clearing (Canvas handles content)
        $output .= $this->backBuffer->render();

        // Swap buffers
        $temp = $this->frontBuffer;
        $this->frontBuffer = $this->backBuffer;
        $this->backBuffer = $temp;

        // Clear the new back buffer for next frame
        $this->backBuffer->clear();

        $presentTime = Logger::endTimerStatic('frame_present');

        Logger::logFrameStatic($this->frameNumber, [
            'full_redraw' => true,
            'output_bytes' => mb_strlen($output),
            'present_time_ms' => round($presentTime * 1000, 2),
        ]);

        return $output;
    }

    /**
     * Get current frame number
     */
    public function getFrameNumber(): int
    {
        return $this->frameNumber;
    }

    /**
     * Get buffer size
     */
    public function getSize(): Size
    {
        return $this->size;
    }

    /**
     * Check if a full redraw is pending
     */
    public function isFullRedrawPending(): bool
    {
        return $this->fullRedrawPending;
    }

    /**
     * Get memory usage statistics
     */
    public function getMemoryStats(): array
    {
        $frontMemory = $this->frontBuffer->getMemoryUsage();
        $backMemory = $this->backBuffer->getMemoryUsage();

        return [
            'front_buffer_bytes' => $frontMemory,
            'back_buffer_bytes' => $backMemory,
            'total_bytes' => $frontMemory + $backMemory,
            'cells_total' => $this->size->width * $this->size->height * 2, // Front + back
        ];
    }

    /**
     * Calculate changes between front and back buffers
     */
    protected function calculateChanges(): array
    {
        Logger::startTimerStatic('calculate_changes');

        $changes = [];
        $changeCount = 0;

        for ($y = 0; $y < $this->size->height; $y++) {
            for ($x = 0; $x < $this->size->width; $x++) {
                $frontCell = $this->frontBuffer->getCell($x, $y);
                $backCell = $this->backBuffer->getCell($x, $y);

                // Compare cells - if they're different, we need to update
                if ($frontCell->char !== $backCell->char || $frontCell->style !== $backCell->style) {
                    $changes[] = new BufferChange($x, $y, $frontCell, $backCell);
                    $changeCount++;
                }
            }
        }

        Logger::endTimerStatic('calculate_changes');
        Logger::logBufferChangeStatic('calculated_delta', [
            'total_changes' => $changeCount,
            'full_redraw' => $this->fullRedrawPending,
        ]);

        return $changes;
    }
}
