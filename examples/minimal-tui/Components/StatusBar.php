<?php

declare(strict_types=1);

namespace MinimalTui\Components;

use MinimalTui\Core\Terminal;

/**
 * Status bar component for displaying status information
 */
class StatusBar
{
    protected array $sections = [];

    protected int $width = 80;

    protected int $height = 1;

    protected string $separator = ' │ ';

    public function __construct(array $sections = [])
    {
        $this->sections = $sections;
    }

    /**
     * Set status bar size
     */
    public function setSize(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Set a section value
     */
    public function setSection(string $name, string $value, string $color = ''): self
    {
        $this->sections[$name] = [
            'value' => $value,
            'color' => $color,
        ];

        return $this;
    }

    /**
     * Remove a section
     */
    public function removeSection(string $name): self
    {
        unset($this->sections[$name]);

        return $this;
    }

    /**
     * Set multiple sections at once
     */
    public function setSections(array $sections): self
    {
        foreach ($sections as $name => $data) {
            if (is_string($data)) {
                $this->setSection($name, $data);
            } elseif (is_array($data)) {
                $this->setSection(
                    $name,
                    $data['value'] ?? '',
                    $data['color'] ?? ''
                );
            }
        }

        return $this;
    }

    /**
     * Set the separator between sections
     */
    public function setSeparator(string $separator): self
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * Get all sections
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Render the status bar
     */
    public function render(): string
    {
        if (empty($this->sections)) {
            return str_repeat(' ', $this->width);
        }

        // Build status content
        $parts = [];
        foreach ($this->sections as $name => $data) {
            $value = $data['value'] ?? '';
            $color = $data['color'] ?? '';

            if ($color) {
                $parts[] = $color . $value . Terminal::RESET;
            } else {
                $parts[] = $value;
            }
        }

        $content = implode($this->separator, $parts);

        // Apply background and ensure full width
        $bg = Terminal::BG_DARK;
        $contentLength = mb_strlen(Terminal::stripAnsi($content));

        // Build the status bar line
        $line = $bg . ' ' . $content;

        // Fill remaining width with background
        $remainingWidth = max(0, $this->width - $contentLength - 2); // -2 for padding
        $line .= str_repeat(' ', $remainingWidth);
        $line .= Terminal::RESET;

        return $line;
    }

    /**
     * Create a common status bar for applications
     */
    public static function create(string $appName = '', string $status = 'Ready'): self
    {
        $statusBar = new self;

        if ($appName) {
            $statusBar->setSection('app', '💮 ' . $appName, Terminal::WHITE . Terminal::BOLD);
        }

        $statusBar->setSection('status', $status, Terminal::YELLOW);

        return $statusBar;
    }

    /**
     * Create a progress status bar
     */
    public static function createProgress(string $operation = '', int $current = 0, int $total = 0): self
    {
        $statusBar = new self;

        if ($operation) {
            $statusBar->setSection('operation', $operation, Terminal::CYAN);
        }

        if ($total > 0) {
            $percent = round(($current / $total) * 100);
            $progress = sprintf('(%d/%d) %d%%', $current, $total, $percent);
            $statusBar->setSection('progress', $progress, Terminal::GREEN);
        }

        return $statusBar;
    }

    /**
     * Update status section (shorthand)
     */
    public function status(string $text, string $color = Terminal::YELLOW): self
    {
        return $this->setSection('status', $text, $color);
    }

    /**
     * Update progress section (shorthand)
     */
    public function progress(int $current, int $total): self
    {
        if ($total > 0) {
            $percent = round(($current / $total) * 100);
            $progress = sprintf('(%d/%d) %d%%', $current, $total, $percent);
            $this->setSection('progress', $progress, Terminal::GREEN);
        }

        return $this;
    }

    /**
     * Set time section (shorthand)
     */
    public function time(string $format = 'H:i:s'): self
    {
        $this->setSection('time', date($format), Terminal::DIM);

        return $this;
    }
}
