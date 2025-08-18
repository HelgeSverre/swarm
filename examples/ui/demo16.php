#!/usr/bin/env php
<?php

/**
 * Demo 16: Smooth Scrolling
 * Virtual viewport with smooth scrolling animations
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";
const HIDE_CURSOR = "\033[?25l";

// Colors
const C_HEADER = "\033[38;5;117m";
const C_TEXT = "\033[38;5;250m";
const C_LINE_NUM = "\033[38;5;240m";
const C_HIGHLIGHT = "\033[48;5;238m";
const C_SCROLLBAR = "\033[38;5;245m";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class SmoothScrollingUI
{
    private array $content = [];

    private float $scrollPosition = 0;

    private float $targetScrollPosition = 0;

    private int $viewportHeight = 20;

    private int $viewportWidth = 80;

    private int $contentHeight;

    private float $scrollVelocity = 0;

    private int $frame = 0;

    private string $scrollMode = 'smooth'; // smooth, page, instant

    public function __construct()
    {
        $this->generateContent();
        $this->contentHeight = count($this->content);
    }

    public function render(): void
    {
        echo CLEAR;

        // Update scroll physics
        $this->updateScrollPhysics();

        // Render header
        $this->renderHeader();

        // Render viewport with content
        $this->renderViewport();

        // Render scrollbar
        $this->renderScrollbar();

        // Render controls
        $this->renderControls();

        // Simulate scrolling
        $this->simulateScrolling();

        $this->frame++;
    }

    private function generateContent(): void
    {
        // Generate sample content
        $this->content[] = ['type' => 'header', 'text' => 'SMOOTH SCROLLING DEMONSTRATION'];
        $this->content[] = ['type' => 'empty'];

        // Add Lorem Ipsum paragraphs
        $paragraphs = [
            'Welcome to the smooth scrolling demo. This interface demonstrates various scrolling techniques including smooth animations, momentum scrolling, and elastic boundaries.',
            'The viewport can handle different types of content including headers, code blocks, and regular text. Scrolling is interpolated for a fluid experience.',
            'Notice how the scrollbar indicates your position within the document. The line numbers help track your location as you navigate through the content.',
        ];

        foreach ($paragraphs as $para) {
            $lines = wordwrap($para, 75, "\n", true);
            foreach (explode("\n", $lines) as $line) {
                $this->content[] = ['type' => 'text', 'text' => $line];
            }
            $this->content[] = ['type' => 'empty'];
        }

        // Add code block
        $this->content[] = ['type' => 'header', 'text' => 'CODE EXAMPLE'];
        $code = <<<'CODE'
class ScrollController {
    private float $position = 0;
    private float $velocity = 0;
    private float $friction = 0.92;
    
    public function update(): void {
        $this->position += $this->velocity;
        $this->velocity *= $this->friction;
        
        // Apply elastic boundaries
        if ($this->position < 0) {
            $this->position *= 0.8;
            $this->velocity = 0;
        }
    }
    
    public function scrollTo(float $target): void {
        $this->velocity = ($target - $this->position) * 0.2;
    }
}
CODE;

        foreach (explode("\n", $code) as $line) {
            $this->content[] = ['type' => 'code', 'text' => $line];
        }

        $this->content[] = ['type' => 'empty'];

        // Add more content for scrolling
        for ($i = 1; $i <= 50; $i++) {
            $this->content[] = [
                'type' => 'text',
                'text' => "Line {$i}: This is sample content to demonstrate smooth scrolling. " .
                         'The text flows naturally as you scroll through the document.',
            ];

            if ($i % 10 === 0) {
                $this->content[] = ['type' => 'header', 'text' => 'SECTION ' . ($i / 10)];
            }
        }
    }

    private function updateScrollPhysics(): void
    {
        // Smooth scrolling with momentum
        if ($this->scrollMode === 'smooth') {
            $diff = $this->targetScrollPosition - $this->scrollPosition;
            $this->scrollVelocity = $diff * 0.15; // Damping factor
            $this->scrollPosition += $this->scrollVelocity;

            // Apply friction
            $this->scrollVelocity *= 0.9;

            // Elastic boundaries
            if ($this->scrollPosition < 0) {
                $this->scrollPosition *= 0.8;
                $this->scrollVelocity = 0;
            } elseif ($this->scrollPosition > $this->contentHeight - $this->viewportHeight) {
                $overscroll = $this->scrollPosition - ($this->contentHeight - $this->viewportHeight);
                $this->scrollPosition -= $overscroll * 0.2;
                $this->scrollVelocity *= 0.5;
            }
        } else {
            // Instant scrolling
            $this->scrollPosition = $this->targetScrollPosition;
        }
    }

    private function renderHeader(): void
    {
        moveCursor(1, 1);
        echo C_HEADER . BOLD . '╔' . str_repeat('═', 78) . '╗' . RESET;
        moveCursor(2, 1);
        echo C_HEADER . BOLD . '║ ' . mb_str_pad('SMOOTH SCROLLING VIEWPORT', 76, ' ', STR_PAD_BOTH) . ' ║' . RESET;
        moveCursor(3, 1);
        echo C_HEADER . BOLD . '╚' . str_repeat('═', 78) . '╝' . RESET;
    }

    private function renderViewport(): void
    {
        $startRow = 5;
        $startLine = (int) floor($this->scrollPosition);
        $subPixelOffset = $this->scrollPosition - $startLine;

        for ($row = 0; $row < $this->viewportHeight; $row++) {
            moveCursor($startRow + $row, 1);

            // Line number
            $lineNum = $startLine + $row + 1;
            if ($lineNum <= $this->contentHeight) {
                echo C_LINE_NUM . sprintf('%4d ', $lineNum) . RESET;
            } else {
                echo '     ';
            }

            // Content with sub-pixel rendering simulation
            $contentIndex = $startLine + $row;

            if ($contentIndex < $this->contentHeight) {
                $line = $this->content[$contentIndex];

                // Apply fade effect for smooth scrolling
                $opacity = 1.0;
                if ($row === 0 && $subPixelOffset > 0.5) {
                    $opacity = 1.5 - $subPixelOffset;
                } elseif ($row === $this->viewportHeight - 1 && $subPixelOffset > 0.5) {
                    $opacity = $subPixelOffset;
                }

                $this->renderLine($line, $opacity);
            } else {
                echo str_repeat(' ', 75);
            }
        }
    }

    private function renderLine(array $line, float $opacity = 1.0): void
    {
        // Simulate opacity with color brightness
        $brightness = (int) (240 + $opacity * 15);
        $color = "\033[38;5;{$brightness}m";

        switch ($line['type']) {
            case 'header':
                echo C_HEADER . BOLD . '═══ ' . $line['text'] . ' ' .
                     str_repeat('═', max(0, 70 - mb_strlen($line['text']))) . RESET;
                break;
            case 'code':
                echo C_HIGHLIGHT . $color . $this->syntaxHighlight($line['text']) . RESET;
                break;
            case 'text':
                echo $color . mb_substr($line['text'], 0, 75) . RESET;
                break;
            case 'empty':
                echo str_repeat(' ', 75);
                break;
        }
    }

    private function syntaxHighlight(string $code): string
    {
        // Simple syntax highlighting
        $keywords = ['class', 'private', 'public', 'function', 'if', 'return', 'float', 'void'];

        foreach ($keywords as $keyword) {
            $code = preg_replace(
                '/\b(' . $keyword . ')\b/',
                "\033[38;5;141m$1\033[38;5;250m",
                $code
            );
        }

        // Highlight strings
        $code = preg_replace('/"([^"]*)"/', "\033[38;5;221m\"$1\"\033[38;5;250m", $code);

        // Highlight numbers
        $code = preg_replace('/\b(\d+\.?\d*)\b/', "\033[38;5;117m$1\033[38;5;250m", $code);

        return mb_str_pad($code, 75);
    }

    private function renderScrollbar(): void
    {
        $scrollbarHeight = $this->viewportHeight;
        $scrollbarStart = 5;

        // Calculate thumb size and position
        $thumbSize = max(1, (int) (($this->viewportHeight / $this->contentHeight) * $scrollbarHeight));
        $thumbPosition = (int) (($this->scrollPosition / ($this->contentHeight - $this->viewportHeight)) * ($scrollbarHeight - $thumbSize));

        for ($i = 0; $i < $scrollbarHeight; $i++) {
            moveCursor($scrollbarStart + $i, 82);

            if ($i >= $thumbPosition && $i < $thumbPosition + $thumbSize) {
                echo C_SCROLLBAR . '█' . RESET;
            } else {
                echo DIM . '│' . RESET;
            }
        }

        // Scroll indicators
        moveCursor($scrollbarStart - 1, 82);
        echo ($this->scrollPosition > 0) ? C_SCROLLBAR . '▲' . RESET : ' ';

        moveCursor($scrollbarStart + $scrollbarHeight, 82);
        $canScrollDown = $this->scrollPosition < $this->contentHeight - $this->viewportHeight;
        echo $canScrollDown ? C_SCROLLBAR . '▼' . RESET : ' ';
    }

    private function renderControls(): void
    {
        $row = 27;

        moveCursor($row, 1);
        echo DIM . str_repeat('─', 80) . RESET;

        moveCursor($row + 1, 2);
        echo 'Controls: ';
        echo C_HEADER . '[↑↓]' . RESET . ' Smooth scroll  ';
        echo C_HEADER . '[PgUp/PgDn]' . RESET . ' Page scroll  ';
        echo C_HEADER . '[Home/End]' . RESET . ' Jump  ';
        echo C_HEADER . '[S]' . RESET . ' Toggle mode';

        moveCursor($row + 2, 2);
        echo 'Mode: ' . C_HEADER . BOLD . ucfirst($this->scrollMode) . RESET;
        echo ' │ Position: ' . sprintf('%.1f / %d', $this->scrollPosition, $this->contentHeight);
        echo ' │ Velocity: ' . sprintf('%.2f', abs($this->scrollVelocity));
    }

    private function simulateScrolling(): void
    {
        // Auto-scroll for demo
        if ($this->frame % 50 === 0) {
            // Scroll down
            $this->targetScrollPosition = min(
                $this->contentHeight - $this->viewportHeight,
                $this->targetScrollPosition + 5
            );
        } elseif ($this->frame % 100 === 0) {
            // Scroll up
            $this->targetScrollPosition = max(0, $this->targetScrollPosition - 8);
        }

        // Occasional page jumps
        if ($this->frame % 200 === 0) {
            $this->targetScrollPosition = rand(0, $this->contentHeight - $this->viewportHeight);
        }

        // Toggle scroll mode
        if ($this->frame % 300 === 0) {
            $modes = ['smooth', 'instant'];
            $this->scrollMode = $modes[array_rand($modes)];
        }
    }
}

// Main execution
echo HIDE_CURSOR;

$ui = new SmoothScrollingUI;

while (true) {
    $ui->render();
    usleep(50000); // 50ms for smooth animation
}
