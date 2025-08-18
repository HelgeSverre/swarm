#!/usr/bin/env php
<?php

/**
 * Demo 14: ASCII Art Integration
 * Beautiful ASCII art headers, logos, and decorative elements
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";

// Gradient colors for ASCII art
const GRADIENT = [
    "\033[38;5;21m",  // Deep blue
    "\033[38;5;27m",  // Blue
    "\033[38;5;33m",  // Light blue
    "\033[38;5;39m",  // Cyan-blue
    "\033[38;5;45m",  // Cyan
    "\033[38;5;51m",  // Bright cyan
    "\033[38;5;87m",  // Light cyan
    "\033[38;5;123m", // Pale cyan
];

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class ASCIIArtUI
{
    private int $frame = 0;

    private array $particlePositions = [];

    public function __construct()
    {
        // Initialize particle positions for background effect
        for ($i = 0; $i < 20; $i++) {
            $this->particlePositions[] = [
                'x' => rand(1, 100),
                'y' => rand(1, 30),
                'char' => ['·', '•', '*', '✦', '✧', '⋆'][rand(0, 5)],
                'speed' => rand(1, 3) / 10,
            ];
        }
    }

    public function render(): void
    {
        echo CLEAR;

        // Animated particles background
        $this->renderParticles();

        // Main logo with gradient
        $this->renderLogo();

        // Decorative dividers
        $this->renderDividers();

        // ASCII art panels
        $this->renderArtPanels();

        // Animated wave footer
        $this->renderWaveFooter();

        $this->frame++;
    }

    private function renderLogo(): void
    {
        $logo = [
            '   ███████╗██╗    ██╗ █████╗ ██████╗ ███╗   ███╗',
            '   ██╔════╝██║    ██║██╔══██╗██╔══██╗████╗ ████║',
            '   ███████╗██║ █╗ ██║███████║██████╔╝██╔████╔██║',
            '   ╚════██║██║███╗██║██╔══██║██╔══██╗██║╚██╔╝██║',
            '   ███████║╚███╔███╔╝██║  ██║██║  ██║██║ ╚═╝ ██║',
            '   ╚══════╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝     ╚═╝',
        ];

        $startRow = 3;
        $startCol = 35;

        foreach ($logo as $i => $line) {
            moveCursor($startRow + $i, $startCol);

            // Apply gradient color
            $colorIndex = ($i + (int) ($this->frame / 5)) % count(GRADIENT);
            echo GRADIENT[$colorIndex];

            // Animate characters
            for ($j = 0; $j < mb_strlen($line); $j++) {
                $char = mb_substr($line, $j, 1);

                // Shimmer effect
                if (rand(0, 100) < 5) {
                    echo BOLD . $char . RESET . GRADIENT[(int) $colorIndex];
                } else {
                    echo $char;
                }
            }
            echo RESET;
        }

        // Subtitle with typewriter effect
        moveCursor($startRow + 7, $startCol + 10);
        $subtitle = 'Advanced Terminal UI Framework';
        $visibleChars = min(mb_strlen($subtitle), $this->frame % 40);
        echo DIM . mb_substr($subtitle, 0, $visibleChars) . RESET;
    }

    private function renderDividers(): void
    {
        // Top ornamental divider
        moveCursor(11, 10);
        $this->renderOrnamentalDivider(100);

        // Section dividers
        moveCursor(20, 10);
        $this->renderGeometricDivider(100);
    }

    private function renderOrnamentalDivider(int $width): void
    {
        $pattern = '◆◇◆';
        $line = '═';

        echo "\033[38;5;240m";
        echo '╔' . str_repeat($line, 10);

        for ($i = 0; $i < 3; $i++) {
            echo $pattern;
            echo str_repeat($line, 10);
        }

        echo '╗';
        echo RESET;
    }

    private function renderGeometricDivider(int $width): void
    {
        $patterns = [
            '▲▼▲▼',
            '◢◣◥◤',
            '⬡⬢⬡⬢',
            '◈◇◈◇',
        ];

        $selected = $patterns[(int) ($this->frame / 20) % count($patterns)];

        echo "\033[38;5;245m";
        for ($i = 0; $i < $width / 4; $i++) {
            echo $selected;
        }
        echo RESET;
    }

    private function renderArtPanels(): void
    {
        // Left panel - Circuit pattern
        $this->renderCircuitPanel(5, 22);

        // Center panel - Matrix rain
        $this->renderMatrixPanel(45, 22);

        // Right panel - Geometric shapes
        $this->renderGeometricPanel(85, 22);
    }

    private function renderCircuitPanel(int $col, int $row): void
    {
        $circuit = [
            '┌─•─┬─•─┐',
            '│ ╱ │ ╲ │',
            '•─┤ └─• │',
            '│ └─┬─┘ │',
            '└─•─┴─•─┘',
        ];

        moveCursor($row - 1, $col);
        echo "\033[38;5;82m" . '[ CIRCUIT ]' . RESET;

        foreach ($circuit as $i => $line) {
            moveCursor($row + $i, $col);

            for ($j = 0; $j < mb_strlen($line); $j++) {
                $char = mb_substr($line, $j, 1);

                // Pulse effect
                if ($char === '•') {
                    $brightness = 160 + (int) (sin($this->frame / 10 + $i + $j) * 40);
                    echo "\033[38;5;{$brightness}m" . $char . RESET;
                } else {
                    echo "\033[38;5;33m" . $char . RESET;
                }
            }
        }
    }

    private function renderMatrixPanel(int $col, int $row): void
    {
        moveCursor($row - 1, $col);
        echo "\033[38;5;46m" . '[ MATRIX ]' . RESET;

        $chars = '01アイウエオカキクケコサシスセソタチツテトナニヌネノ';
        $width = 20;
        $height = 5;

        for ($y = 0; $y < $height; $y++) {
            moveCursor($row + $y, $col);

            for ($x = 0; $x < $width; $x++) {
                $charIndex = ($this->frame + $x * 3 + $y * 7) % mb_strlen($chars);
                $char = mb_substr($chars, $charIndex, 1);

                // Falling effect with gradient
                $brightness = 22 + (int) ((sin($this->frame / 5 - $y) + 1) * 20);
                echo "\033[38;5;{$brightness}m" . $char . RESET;
            }
        }
    }

    private function renderGeometricPanel(int $col, int $row): void
    {
        moveCursor($row - 1, $col);
        echo "\033[38;5;171m" . '[ GEOMETRIC ]' . RESET;

        $shapes = [
            '    △    ',
            '   ╱ ╲   ',
            '  ╱   ╲  ',
            ' ◇─────◇ ',
            '  ╲   ╱  ',
            '   ╲ ╱   ',
            '    ▽    ',
        ];

        // Rotate through shapes
        $rotation = (int) ($this->frame / 15) % count($shapes);

        for ($i = 0; $i < 5; $i++) {
            $lineIndex = ($i + (int) $rotation) % count($shapes);
            moveCursor($row + $i, $col);

            $line = $shapes[$lineIndex];
            $color = GRADIENT[$i % count(GRADIENT)];
            echo $color . $line . RESET;
        }
    }

    private function renderWaveFooter(): void
    {
        $height = (int) exec('tput lines') ?: 40;
        $width = (int) exec('tput cols') ?: 120;

        // Wave patterns
        $waves = [
            ['～', '∼', '〜', '〰', '～', '∼'],
            ['▁', '▂', '▃', '▄', '▃', '▂'],
            ['◠', '◡', '◠', '◡', '◠', '◡'],
        ];

        for ($waveRow = 0; $waveRow < 3; $waveRow++) {
            moveCursor($height - 3 + $waveRow, 1);

            $wave = $waves[$waveRow];
            $offset = $this->frame / 3;

            for ($x = 0; $x < $width; $x++) {
                $waveIndex = (int) (($x + $offset) / 3) % count($wave);
                $char = $wave[$waveIndex];

                // Color gradient
                $colorIndex = (int) (($x + $offset) / 10) % count(GRADIENT);
                echo GRADIENT[$colorIndex] . $char . RESET;
            }
        }

        // Footer text
        moveCursor($height, 2);
        echo DIM . 'ASCII Art Integration │ Frame: ' . $this->frame . ' │ FPS: 10' . RESET;
    }

    private function renderParticles(): void
    {
        foreach ($this->particlePositions as &$particle) {
            // Update position
            $particle['y'] += $particle['speed'];

            // Wrap around
            if ($particle['y'] > 30) {
                $particle['y'] = 1;
                $particle['x'] = rand(1, 100);
            }

            // Render
            moveCursor((int) $particle['y'], (int) $particle['x']);
            echo "\033[38;5;238m" . $particle['char'] . RESET;
        }
    }
}

// Main execution
$ui = new ASCIIArtUI;

while (true) {
    $ui->render();
    usleep(100000); // 100ms refresh
}
