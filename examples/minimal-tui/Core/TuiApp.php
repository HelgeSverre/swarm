<?php

declare(strict_types=1);

namespace MinimalTui\Core;

/**
 * Main TUI application framework
 */
class TuiApp
{
    protected Terminal $terminal;

    protected Layout $layout;

    protected array $components = [];

    protected ?string $focusedComponent = null;

    protected array $focusOrder = [];

    protected bool $running = false;

    protected bool $needsRedraw = true;

    protected int $width;

    protected int $height;

    public function __construct()
    {
        $this->terminal = Terminal::getInstance();
        $size = $this->terminal->getSize();
        $this->width = $size['width'];
        $this->height = $size['height'];
        $this->layout = new Layout($this->width, $this->height);
    }

    /**
     * Set the layout configuration
     */
    public function setLayout(Layout $layout): self
    {
        $this->layout = $layout;

        return $this;
    }

    /**
     * Add a component to the application
     */
    public function addComponent(string $name, object $component, string $area): self
    {
        $this->components[$name] = [
            'component' => $component,
            'area' => $area,
        ];

        // Add to focus order if component supports focus
        if (method_exists($component, 'setFocused')) {
            $this->focusOrder[] = $name;
            if ($this->focusedComponent === null) {
                $this->focusedComponent = $name;
                $component->setFocused(true);
            }
        }

        $this->needsRedraw = true;

        return $this;
    }

    /**
     * Remove a component
     */
    public function removeComponent(string $name): self
    {
        unset($this->components[$name]);
        $this->focusOrder = array_values(array_filter($this->focusOrder, fn ($n) => $n !== $name));

        if ($this->focusedComponent === $name) {
            $this->focusedComponent = $this->focusOrder[0] ?? null;
            if ($this->focusedComponent) {
                $this->components[$this->focusedComponent]['component']->setFocused(true);
            }
        }

        $this->needsRedraw = true;

        return $this;
    }

    /**
     * Get a component by name
     */
    public function getComponent(string $name): ?object
    {
        return $this->components[$name]['component'] ?? null;
    }

    /**
     * Set focus to a specific component
     */
    public function setFocus(string $name): self
    {
        if (! isset($this->components[$name])) {
            return $this;
        }

        // Remove focus from current component
        if ($this->focusedComponent && isset($this->components[$this->focusedComponent])) {
            $current = $this->components[$this->focusedComponent]['component'];
            if (method_exists($current, 'setFocused')) {
                $current->setFocused(false);
            }
        }

        // Set focus to new component
        $this->focusedComponent = $name;
        $component = $this->components[$name]['component'];
        if (method_exists($component, 'setFocused')) {
            $component->setFocused(true);
        }

        $this->needsRedraw = true;

        return $this;
    }

    /**
     * Cycle focus to next component
     */
    public function cycleFocus(): self
    {
        if (empty($this->focusOrder)) {
            return $this;
        }

        $currentIndex = array_search($this->focusedComponent, $this->focusOrder);
        $nextIndex = ($currentIndex + 1) % count($this->focusOrder);
        $this->setFocus($this->focusOrder[$nextIndex]);

        return $this;
    }

    /**
     * Run the application main loop
     */
    public function run(): void
    {
        $this->terminal->initialize();
        $this->running = true;

        try {
            while ($this->running) {
                $this->handleResize();
                $this->render();

                if (! $this->handleInput()) {
                    break;
                }

                usleep(16667); // ~60 FPS
            }
        } finally {
            $this->terminal->cleanup();
        }
    }

    /**
     * Stop the application
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Force a redraw
     */
    public function redraw(): void
    {
        $this->needsRedraw = true;
    }

    /**
     * Handle terminal resize
     */
    protected function handleResize(): void
    {
        $size = $this->terminal->getSize();
        if ($size['width'] !== $this->width || $size['height'] !== $this->height) {
            $this->width = $size['width'];
            $this->height = $size['height'];
            $this->layout->resize($this->width, $this->height);
            $this->needsRedraw = true;
        }
    }

    /**
     * Handle keyboard input
     */
    protected function handleInput(): bool
    {
        $key = $this->terminal->readKey();
        if ($key === null) {
            return true; // Continue running
        }

        // Global shortcuts
        if ($key === 'ALT+Q') {
            return false; // Exit
        }

        if ($key === 'TAB') {
            $this->cycleFocus();

            return true;
        }

        // Pass input to focused component
        if ($this->focusedComponent && isset($this->components[$this->focusedComponent])) {
            $component = $this->components[$this->focusedComponent]['component'];
            if (method_exists($component, 'handleInput')) {
                $result = $component->handleInput($key);
                if ($result !== null) {
                    $this->needsRedraw = true;

                    // If component returns a value, it might be a command or result
                    if (is_string($result) && method_exists($this, 'onCommand')) {
                        $this->onCommand($result);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Render all components
     */
    protected function render(): void
    {
        if (! $this->needsRedraw) {
            return;
        }

        $this->terminal->clearScreen();
        $this->terminal->hideCursor();

        // Render each component in its designated area
        foreach ($this->components as $name => $data) {
            $component = $data['component'];
            $areaName = $data['area'];
            $area = $this->layout->getArea($areaName);

            if (! $area || ! method_exists($component, 'render')) {
                continue;
            }

            // Set component size if it supports it
            if (method_exists($component, 'setSize')) {
                $component->setSize($area['width'], $area['height']);
            }

            // Render component and get output
            $output = $component->render();
            if (! $output) {
                continue;
            }

            // Position and output the rendered content
            $lines = explode("\n", $output);
            for ($i = 0; $i < min(count($lines), $area['height']); $i++) {
                $this->terminal->moveCursor($area['y'] + $i, $area['x']);
                echo $lines[$i];
            }
        }

        // Draw dividers between areas
        $this->drawDividers();

        // Show cursor if focused component wants it
        if ($this->focusedComponent && isset($this->components[$this->focusedComponent])) {
            $component = $this->components[$this->focusedComponent]['component'];
            if (method_exists($component, 'getCursorPosition')) {
                $cursorPos = $component->getCursorPosition();
                if ($cursorPos) {
                    $area = $this->layout->getArea($this->components[$this->focusedComponent]['area']);
                    if ($area) {
                        $this->terminal->moveCursor(
                            $area['y'] + $cursorPos['y'],
                            $area['x'] + $cursorPos['x']
                        );
                        $this->terminal->showCursor();
                    }
                }
            }
        }

        $this->needsRedraw = false;
    }

    /**
     * Draw dividers between layout areas
     */
    protected function drawDividers(): void
    {
        $areas = $this->layout->getAreas();

        // Simple vertical divider detection - if we have main and sidebar
        if (isset($areas['main'], $areas['sidebar'])) {
            $main = $areas['main'];
            $sidebar = $areas['sidebar'];
            $dividerCol = $main['x'] + $main['width'] + 1;

            for ($row = 1; $row <= $this->height; $row++) {
                $this->terminal->moveCursor($row, $dividerCol);
                echo Terminal::DIM . Terminal::BOX_V . Terminal::RESET;
            }
        }
    }

    /**
     * Override this method to handle commands from components
     */
    protected function onCommand(string $command): void
    {
        // Override in subclasses
    }
}
