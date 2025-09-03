<?php

declare(strict_types=1);

namespace Examples\TuiLib\SwarmMock;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Icons;
use Examples\TuiLib\Core\Logger;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\TextMeasurement;
use Examples\TuiLib\Core\Theme;
use Examples\TuiLib\Core\Widget;

/**
 * SwarmHeader widget - Exact replica of FullTerminalUI renderStatusBar()
 *
 * Renders the header bar with format: "💮 swarm | status | (step/total)"
 */
class SwarmHeader extends Widget
{
    protected string $status = 'Ready';

    protected int $currentStep = 0;

    protected int $totalSteps = 0;

    protected string $currentFocus = '';

    protected Theme $theme;

    public function __construct(string $id = 'swarm_header')
    {
        parent::__construct($id);
        $this->theme = Theme::swarm();
    }

    /**
     * Set the current status message
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->markNeedsRepaint();
    }

    /**
     * Set progress information
     */
    public function setProgress(int $currentStep, int $totalSteps): void
    {
        $this->currentStep = $currentStep;
        $this->totalSteps = $totalSteps;
        $this->markNeedsRepaint();
    }

    /**
     * Set current focus area for display
     */
    public function setCurrentFocus(string $focus): void
    {
        $this->currentFocus = $focus;
        $this->markNeedsRepaint();
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        // Header is always full width and 1 row tall
        return new Size($constraints->maxWidth, 1);
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

        Logger::logWidgetStatic($this->id, 'paint_start', [
            'bounds' => [
                'x' => $this->bounds->x,
                'y' => $this->bounds->y,
                'width' => $this->bounds->width,
                'height' => $this->bounds->height,
            ],
            'status' => $this->status,
            'progress' => "{$this->currentStep}/{$this->totalSteps}",
        ]);

        $this->renderToCanvas($context->getCanvas());

        Logger::logWidgetStatic($this->id, 'paint_complete');

        $this->clearRepaintFlag();

        return '';
    }

    /**
     * Handle key events (header typically doesn't handle keys directly)
     */
    public function handleKeyEvent(string $key): bool
    {
        return false;
    }

    /**
     * Render header to canvas instead of direct terminal output
     */
    protected function renderToCanvas(?\Examples\TuiLib\Core\Canvas $canvas): void
    {
        if (! $canvas || ! $this->bounds) {
            return;
        }

        Logger::debug('SwarmHeader rendering to canvas', [
            'bounds' => [
                'x' => $this->bounds->x,
                'y' => $this->bounds->y,
                'width' => $this->bounds->width,
                'height' => $this->bounds->height,
            ],
        ]);

        // Build the status content components
        $parts = [];

        // App name with icon
        $parts[] = new \Examples\TuiLib\Core\StyledText(' 💮 swarm ', $this->theme->get('header.app'));

        // Separator
        $parts[] = new \Examples\TuiLib\Core\StyledText(Icons::get('separator_v') . ' ', $this->theme->get('header.separator'));

        // Status message
        $parts[] = new \Examples\TuiLib\Core\StyledText($this->status, $this->theme->get('header.status'));

        // Add progress if we have steps
        if ($this->totalSteps > 0) {
            $parts[] = new \Examples\TuiLib\Core\StyledText(" ({$this->currentStep}/{$this->totalSteps})", $this->theme->get('header.progress'));
        }

        // Calculate total content width
        $contentWidth = 0;
        foreach ($parts as $part) {
            $contentWidth += TextMeasurement::width($part->getText());
        }

        // Write each part to canvas
        $currentX = $this->bounds->x;
        foreach ($parts as $part) {
            $canvas->drawText($currentX, $this->bounds->y, $part->render());
            $currentX += TextMeasurement::width($part->getText());
        }

        // Fill remaining space with background color
        $remainingWidth = max(0, $this->bounds->width - $contentWidth);
        if ($remainingWidth > 0) {
            $padding = new \Examples\TuiLib\Core\StyledText(str_repeat(' ', $remainingWidth), $this->theme->get('header.background'));
            $canvas->drawText($currentX, $this->bounds->y, $padding->render());
        }
    }
}
