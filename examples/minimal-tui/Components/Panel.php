<?php

declare(strict_types=1);

namespace MinimalTui\Components;

use MinimalTui\Core\Terminal;

/**
 * Panel component - container with optional border and title
 */
class Panel
{
    protected string $title;

    protected ?object $content = null;

    protected bool $bordered;

    protected int $width = 0;

    protected int $height = 0;

    protected bool $focused = false;

    public function __construct(string $title = '', ?object $content = null, bool $bordered = true)
    {
        $this->title = $title;
        $this->content = $content;
        $this->bordered = $bordered;
    }

    /**
     * Set the content component
     */
    public function setContent(object $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set panel size
     */
    public function setSize(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;

        // Pass size to content, accounting for border
        if ($this->content && method_exists($this->content, 'setSize')) {
            $contentWidth = $this->bordered ? $width - 2 : $width;
            $contentHeight = $this->bordered ? $height - 2 : $height;
            $this->content->setSize($contentWidth, $contentHeight);
        }

        return $this;
    }

    /**
     * Set focused state
     */
    public function setFocused(bool $focused): self
    {
        $this->focused = $focused;

        // Pass focus to content
        if ($this->content && method_exists($this->content, 'setFocused')) {
            $this->content->setFocused($focused);
        }

        return $this;
    }

    /**
     * Handle input - pass to content
     */
    public function handleInput(string $key): mixed
    {
        if ($this->content && method_exists($this->content, 'handleInput')) {
            return $this->content->handleInput($key);
        }

        return null;
    }

    /**
     * Get cursor position from content
     */
    public function getCursorPosition(): ?array
    {
        if ($this->content && method_exists($this->content, 'getCursorPosition')) {
            $pos = $this->content->getCursorPosition();
            if ($pos && $this->bordered) {
                // Adjust for border offset
                return [
                    'x' => $pos['x'] + 1,
                    'y' => $pos['y'] + 1,
                ];
            }

            return $pos;
        }

        return null;
    }

    /**
     * Render the panel
     */
    public function render(): string
    {
        $lines = [];

        if ($this->bordered) {
            // Top border with title
            $topBorder = $this->renderTopBorder();
            $lines[] = $topBorder;

            // Content area
            $contentLines = $this->renderContent();
            foreach ($contentLines as $line) {
                $lines[] = Terminal::BOX_V . $line . Terminal::BOX_V;
            }

            // Pad remaining lines if needed
            $contentHeight = $this->height - 2; // Account for top and bottom borders
            while (count($contentLines) < $contentHeight) {
                $padding = str_repeat(' ', $this->width - 2);
                $lines[] = Terminal::BOX_V . $padding . Terminal::BOX_V;
                $contentLines[] = ''; // Track for loop termination
            }

            // Bottom border
            $bottomBorder = Terminal::BOX_BL . str_repeat(Terminal::BOX_H, $this->width - 2) . Terminal::BOX_BR;
            $lines[] = $bottomBorder;
        } else {
            // No border - just render content
            $lines = $this->renderContent();
        }

        return implode("\n", $lines);
    }

    /**
     * Render the top border with optional title
     */
    protected function renderTopBorder(): string
    {
        $border = Terminal::BOX_TL;

        if ($this->title) {
            $titleLen = mb_strlen($this->title);
            $availableWidth = $this->width - 4; // Space for corners and padding

            if ($titleLen <= $availableWidth) {
                $padding = max(0, ($availableWidth - $titleLen) / 2);
                $leftPad = (int) $padding;
                $rightPad = $availableWidth - $titleLen - $leftPad;

                $border .= str_repeat(Terminal::BOX_H, $leftPad);
                $border .= ' ';

                // Title with focus styling
                if ($this->focused) {
                    $border .= Terminal::BRIGHT_CYAN . Terminal::BOLD . $this->title . Terminal::RESET;
                } else {
                    $border .= Terminal::WHITE . Terminal::BOLD . $this->title . Terminal::RESET;
                }

                $border .= ' ';
                $border .= str_repeat(Terminal::BOX_H, $rightPad);
            } else {
                // Title too long, truncate
                $truncated = Terminal::truncate($this->title, $availableWidth);
                $border .= ' ';

                if ($this->focused) {
                    $border .= Terminal::BRIGHT_CYAN . Terminal::BOLD . $truncated . Terminal::RESET;
                } else {
                    $border .= Terminal::WHITE . Terminal::BOLD . $truncated . Terminal::RESET;
                }

                $border .= ' ';
            }
        } else {
            $border .= str_repeat(Terminal::BOX_H, $this->width - 2);
        }

        $border .= Terminal::BOX_TR;

        return $border;
    }

    /**
     * Render the content area
     */
    protected function renderContent(): array
    {
        if (! $this->content) {
            $contentHeight = $this->bordered ? $this->height - 2 : $this->height;
            $contentWidth = $this->bordered ? $this->width - 2 : $this->width;

            // Empty content - return blank lines
            return array_fill(0, $contentHeight, str_repeat(' ', $contentWidth));
        }

        if (method_exists($this->content, 'render')) {
            $output = $this->content->render();
            $lines = explode("\n", $output);

            $contentWidth = $this->bordered ? $this->width - 2 : $this->width;

            // Ensure each line fits within the content area
            return array_map(function ($line) use ($contentWidth) {
                $stripped = Terminal::stripAnsi($line);
                if (mb_strlen($stripped) > $contentWidth) {
                    return Terminal::truncate($line, $contentWidth);
                }

                return Terminal::pad($line, $contentWidth);
            }, $lines);
        }

        return [];
    }
}
