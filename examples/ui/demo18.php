#!/usr/bin/env php
<?php

/**
 * Demo 18: Terminal Animations
 * Various animation techniques and effects
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";
const HIDE_CURSOR = "\033[?25l";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class AnimationShowcase
{
    private int $frame = 0;

    private array $particles = [];

    private array $fireworks = [];

    public function __construct()
    {
        $this->initializeParticles();
    }

    public function render(): void
    {
        echo CLEAR;

        // Title
        $this->renderTitle();

        // Different animation zones
        $this->renderLoadingAnimations(5, 2);
        $this->renderWaveAnimation(12, 2);
        $this->renderParticleSystem(5, 60);
        $this->renderTextAnimations(20, 2);
        $this->renderFireworks(25, 60);
        $this->renderMatrixRain(20, 60);

        $this->frame++;
    }

    private function initializeParticles(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->particles[] = [
                'x' => rand(5, 115),
                'y' => rand(5, 35),
                'vx' => (rand(-10, 10) / 10),
                'vy' => (rand(-10, 10) / 10),
                'char' => ['✦', '✧', '⋆', '•', '·'][rand(0, 4)],
                'color' => rand(1, 7),
            ];
        }
    }

    private function renderTitle(): void
    {
        moveCursor(1, 45);
        echo BOLD . 'TERMINAL ANIMATIONS' . RESET;

        moveCursor(2, 30);
        echo DIM . 'Various animation techniques and visual effects' . RESET;
    }

    private function renderLoadingAnimations(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BOLD . 'Loading Animations:' . RESET;

        // Spinner variations
        $spinners = [
            ['chars' => ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'], 'name' => 'Dots'],
            ['chars' => ['◐', '◓', '◑', '◒'], 'name' => 'Circle'],
            ['chars' => ['▖', '▘', '▝', '▗'], 'name' => 'Quadrants'],
            ['chars' => ['┤', '┘', '┴', '└', '├', '┌', '┬', '┐'], 'name' => 'Box'],
            ['chars' => ['←', '↖', '↑', '↗', '→', '↘', '↓', '↙'], 'name' => 'Arrows'],
        ];

        foreach ($spinners as $i => $spinner) {
            moveCursor($row + 2 + $i, $col);
            $charIndex = $this->frame % count($spinner['chars']);
            echo "\033[38;5;" . (117 + $i * 10) . 'm';
            echo $spinner['chars'][$charIndex];
            echo RESET . ' ' . $spinner['name'];
        }

        // Progress bar animation
        moveCursor($row + 8, $col);
        echo 'Progress: ';
        $progress = ($this->frame % 100) / 100;
        $barWidth = 20;
        $filled = (int) ($progress * $barWidth);

        for ($i = 0; $i < $barWidth; $i++) {
            if ($i < $filled) {
                echo "\033[38;5;" . (46 + $i) . 'm█' . RESET;
            } else {
                echo DIM . '░' . RESET;
            }
        }
        echo sprintf(' %3d%%', (int) ($progress * 100));
    }

    private function renderWaveAnimation(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BOLD . 'Wave Animation:' . RESET;

        $waveChars = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█', '▇', '▆', '▅', '▄', '▃', '▂', '▁'];
        $width = 50;

        for ($waveRow = 0; $waveRow < 3; $waveRow++) {
            moveCursor($row + 2 + $waveRow, $col);

            for ($x = 0; $x < $width; $x++) {
                $offset = ($this->frame + $x * 2 + $waveRow * 10) / 5;
                $waveIndex = (int) (sin($offset) * 7 + 7);
                $waveIndex = max(0, min(14, $waveIndex));

                $color = 21 + (int) ((sin($offset) + 1) * 20);
                echo "\033[38;5;{$color}m" . $waveChars[$waveIndex] . RESET;
            }
        }
    }

    private function renderParticleSystem(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BOLD . 'Particle System:' . RESET;

        // Boundary box
        $boxWidth = 50;
        $boxHeight = 12;

        // Draw box
        moveCursor($row + 1, $col);
        echo DIM . '┌' . str_repeat('─', $boxWidth) . '┐' . RESET;
        for ($i = 0; $i < $boxHeight; $i++) {
            moveCursor($row + 2 + $i, $col);
            echo DIM . '│' . RESET;
            moveCursor($row + 2 + $i, $col + $boxWidth + 1);
            echo DIM . '│' . RESET;
        }
        moveCursor($row + $boxHeight + 2, $col);
        echo DIM . '└' . str_repeat('─', $boxWidth) . '┘' . RESET;

        // Update and render particles
        foreach ($this->particles as &$particle) {
            // Clear old position (simplified)

            // Update physics
            $particle['x'] += $particle['vx'];
            $particle['y'] += $particle['vy'];

            // Bounce off boundaries
            if ($particle['x'] < $col + 1 || $particle['x'] > $col + $boxWidth) {
                $particle['vx'] *= -0.9;
                $particle['x'] = max($col + 1, min($col + $boxWidth, $particle['x']));
            }
            if ($particle['y'] < $row + 2 || $particle['y'] > $row + $boxHeight + 1) {
                $particle['vy'] *= -0.9;
                $particle['y'] = max($row + 2, min($row + $boxHeight + 1, $particle['y']));
            }

            // Gravity
            $particle['vy'] += 0.02;

            // Render if in bounds
            if ($particle['x'] > $col && $particle['x'] < $col + $boxWidth + 1 &&
                $particle['y'] > $row + 1 && $particle['y'] < $row + $boxHeight + 2) {
                moveCursor((int) $particle['y'], (int) $particle['x']);
                $color = 51 + $particle['color'] * 36;
                echo "\033[38;5;{$color}m" . $particle['char'] . RESET;
            }
        }
    }

    private function renderTextAnimations(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BOLD . 'Text Effects:' . RESET;

        // Typewriter effect
        $text1 = 'Typewriter effect animation...';
        $visibleChars = min(mb_strlen($text1), (int) ($this->frame / 2) % (mb_strlen($text1) + 10));
        moveCursor($row + 2, $col);
        echo mb_substr($text1, 0, $visibleChars);
        if ($visibleChars < mb_strlen($text1)) {
            echo DIM . '│' . RESET;
        }

        // Rainbow text
        $text2 = 'RAINBOW COLOR CYCLE';
        moveCursor($row + 4, $col);
        for ($i = 0; $i < mb_strlen($text2); $i++) {
            $color = 196 + (($i + (int) ($this->frame / 3)) % 36);
            echo "\033[38;5;{$color}m" . $text2[$i] . RESET;
        }

        // Glitch effect
        $text3 = 'GLITCH EFFECT';
        moveCursor($row + 6, $col);
        for ($i = 0; $i < mb_strlen($text3); $i++) {
            if (rand(0, 100) < 10) {
                // Glitch character
                $glitchChars = ['▓', '▒', '░', '█', '▄', '▀', '▌', '▐'];
                echo "\033[38;5;" . rand(1, 255) . 'm' . $glitchChars[rand(0, 7)] . RESET;
            } else {
                echo $text3[$i];
            }
        }
    }

    private function renderFireworks(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BOLD . 'Fireworks:' . RESET;

        // Launch new firework occasionally
        if ($this->frame % 30 === 0) {
            $this->fireworks[] = [
                'x' => $col + rand(5, 45),
                'y' => $row + 10,
                'age' => 0,
                'color' => rand(1, 7),
                'particles' => [],
            ];
        }

        // Update and render fireworks
        foreach ($this->fireworks as &$fw) {
            $fw['age']++;

            if ($fw['age'] < 10) {
                // Rising
                $fw['y']--;
                moveCursor($fw['y'], $fw['x']);
                echo "\033[38;5;221m│" . RESET;
            } elseif ($fw['age'] === 10) {
                // Explode
                for ($i = 0; $i < 8; $i++) {
                    $angle = $i * M_PI / 4;
                    $fw['particles'][] = [
                        'x' => $fw['x'],
                        'y' => $fw['y'],
                        'vx' => cos($angle) * 2,
                        'vy' => sin($angle),
                    ];
                }
            } else {
                // Particle explosion
                foreach ($fw['particles'] as &$p) {
                    $p['x'] += $p['vx'];
                    $p['y'] += $p['vy'];
                    $p['vy'] += 0.1; // Gravity

                    if ($p['y'] < $row + 12 && $p['x'] > $col && $p['x'] < $col + 50) {
                        moveCursor((int) $p['y'], (int) $p['x']);
                        $brightness = max(0, 255 - ($fw['age'] - 10) * 10);
                        if ($brightness > 0) {
                            $color = 196 + $fw['color'] * 6;
                            echo "\033[38;5;{$color}m*" . RESET;
                        }
                    }
                }
            }
        }

        // Remove old fireworks
        $this->fireworks = array_filter($this->fireworks, fn ($fw) => $fw['age'] < 30);
    }

    private function renderMatrixRain(int $row, int $col): void
    {
        moveCursor($row, $col);
        echo BOLD . 'Matrix Rain:' . RESET;

        $width = 50;
        $height = 10;
        $chars = '01アイウエオカキクケコサシスセソ';

        for ($x = 0; $x < $width; $x++) {
            $dropSpeed = 1 + ($x % 3);
            $dropPosition = ($this->frame * $dropSpeed + $x * 5) % ($height + 10);

            for ($y = 0; $y < $height; $y++) {
                moveCursor($row + 2 + $y, $col + $x);

                if ($y === $dropPosition) {
                    // Head of the drop - bright
                    echo "\033[38;5;46m" . mb_substr($chars, rand(0, mb_strlen($chars) - 1), 1) . RESET;
                } elseif ($y > $dropPosition - 5 && $y < $dropPosition) {
                    // Trail - fading
                    $brightness = 22 + (5 - ($dropPosition - $y)) * 8;
                    echo "\033[38;5;{$brightness}m" . mb_substr($chars, rand(0, mb_strlen($chars) - 1), 1) . RESET;
                } else {
                    echo ' ';
                }
            }
        }
    }
}

// Main execution
echo HIDE_CURSOR;

$showcase = new AnimationShowcase;

while (true) {
    $showcase->render();
    usleep(50000); // 50ms for smooth animations
}
