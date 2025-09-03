<?php

declare(strict_types=1);

namespace Examples\TuiLib\SwarmMock;

use Closure;
use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Icons;
use Examples\TuiLib\Core\Logger;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\StyledText;
use Examples\TuiLib\Core\Theme;
use Examples\TuiLib\Core\Widget;

/**
 * SwarmInput widget - Exact replica of FullTerminalUI input handling
 *
 * This component replicates the exact input rendering and handling from FullTerminalUI
 * including the footer and prompt area.
 */
class SwarmInput extends Widget
{
    protected string $input = '';

    protected bool $isFocused = false;

    protected string $modSymbol = '⌥'; // macOS default

    protected Closure $onSubmit;

    protected Theme $theme;

    public function __construct(string $id = 'swarm_input')
    {
        parent::__construct($id);
        $this->theme = Theme::swarm();
        $this->onSubmit = function (string $input) {
            // Default no-op
        };
    }

    /**
     * Set the input text
     */
    public function setInput(string $input): void
    {
        $this->input = $input;
        $this->markNeedsRepaint();
    }

    /**
     * Get the current input text
     */
    public function getInput(): string
    {
        return $this->input;
    }

    /**
     * Set focus state
     */
    public function setFocused(bool $focused): void
    {
        $this->isFocused = $focused;
        $this->markNeedsRepaint();
    }

    /**
     * Set submit callback
     */
    public function setOnSubmit(Closure $callback): void
    {
        $this->onSubmit = $callback;
    }

    /**
     * Set modifier symbol (⌥ for macOS, Alt+ for others)
     */
    public function setModSymbol(string $symbol): void
    {
        $this->modSymbol = $symbol;
        $this->markNeedsRepaint();
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        // Input area is 2 rows tall (footer + prompt)
        return new Size($constraints->maxWidth, 2);
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
            'focused' => $this->isFocused,
        ]);

        $this->renderToCanvas($context->getCanvas());

        Logger::logWidgetStatic($this->id, 'paint_complete');

        return '';
    }

    /**
     * Handle key events for input
     */
    public function handleKeyEvent(string $key): bool
    {
        if (! $this->isFocused) {
            return false;
        }

        // Handle Enter key - submit input
        if ($key === "\n") {
            if (! empty($this->input)) {
                $submittedInput = $this->input;
                $this->input = '';
                $this->markNeedsRepaint();
                call_user_func($this->onSubmit, $submittedInput);

                return true;
            }

            return false;
        }

        // Handle Backspace
        if ($key === "\177" || $key === "\010") {
            if (mb_strlen($this->input) > 0) {
                $this->input = mb_substr($this->input, 0, -1);
                $this->markNeedsRepaint();

                return true;
            }

            return false;
        }

        // Handle regular text input
        if (mb_strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
            $this->input .= $key;
            $this->markNeedsRepaint();

            return true;
        }

        // Handle special keys that should not be processed
        if (in_array($key, ['TAB', 'UP', 'DOWN', 'LEFT', 'RIGHT', 'ESC'])) {
            return false; // Let parent handle these
        }

        // Handle Alt combinations - don't process them here
        if (str_starts_with($key, 'ALT+')) {
            return false; // Let parent handle these
        }

        return false;
    }

    /**
     * Clear the input
     */
    public function clearInput(): void
    {
        $this->input = '';
        $this->markNeedsRepaint();
    }

    /**
     * Append text to current input
     */
    public function appendToInput(string $text): void
    {
        $this->input .= $text;
        $this->markNeedsRepaint();
    }

    /**
     * Set input to specific text (useful for history navigation)
     */
    public function replaceInput(string $text): void
    {
        $this->input = $text;
        $this->markNeedsRepaint();
    }

    /**
     * Render to canvas instead of direct terminal output
     */
    protected function renderToCanvas(?\Examples\TuiLib\Core\Canvas $canvas): void
    {
        if (! $canvas || ! $this->bounds) {
            return;
        }

        Logger::debug('SwarmInput rendering to canvas', [
            'bounds' => [
                'x' => $this->bounds->x,
                'y' => $this->bounds->y,
                'width' => $this->bounds->width,
                'height' => $this->bounds->height,
            ],
        ]);

        // Footer separator (row 0 of our bounds)
        $separator = str_repeat(Icons::get('horizontal'), $this->bounds->width - 1) . Icons::get('separator_right');
        $separatorStyled = new StyledText($separator, $this->theme->get('ui.border'));
        $canvas->drawText($this->bounds->x, $this->bounds->y, $separatorStyled->render());

        // Footer hints (row 1 of our bounds)
        $hints = "{$this->modSymbol}T: tasks  {$this->modSymbol}H: help  Tab: switch pane  {$this->modSymbol}Q: quit";
        $hintsStyled = new StyledText($hints, $this->theme->get('ui.muted'));
        $canvas->drawText($this->bounds->x + 1, $this->bounds->y + 1, $hintsStyled->render());

        // Prompt (row 2 of our bounds)
        if ($this->isFocused) {
            $promptStyled = new StyledText('swarm >', $this->theme->get('prompt.active'));
            $canvas->drawText($this->bounds->x + 1, $this->bounds->y + 2, $promptStyled->render());

            // Input text
            if (! empty($this->input)) {
                $inputStyled = new StyledText($this->input, null);
                $canvas->drawText($this->bounds->x + 9, $this->bounds->y + 2, $inputStyled->render());
            }

            // TODO: Handle cursor positioning - for now we'll skip it since Canvas doesn't directly support cursor
        } else {
            $promptStyled = new StyledText('swarm >', $this->theme->get('prompt.inactive'));
            $canvas->drawText($this->bounds->x + 1, $this->bounds->y + 2, $promptStyled->render());

            if (! empty($this->input)) {
                $inputStyled = new StyledText($this->input, $this->theme->get('input.inactive'));
                $canvas->drawText($this->bounds->x + 9, $this->bounds->y + 2, $inputStyled->render());
            }
        }
    }
}
