#!/usr/bin/env php
<?php

/**
 * Demo 11: Animated Transitions
 * Smooth transitions between UI states with easing functions
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";
const HIDE_CURSOR = "\033[?25l";

// Colors
const CYAN = "\033[36m";
const YELLOW = "\033[33m";
const GREEN = "\033[32m";
const MAGENTA = "\033[35m";
const BLUE = "\033[34m";
const RED = "\033[31m";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

// Easing functions for smooth animations
class Easing
{
    public static function linear(float $t): float
    {
        return $t;
    }

    public static function easeInQuad(float $t): float
    {
        return $t * $t;
    }

    public static function easeOutQuad(float $t): float
    {
        return $t * (2 - $t);
    }

    public static function easeInOutQuad(float $t): float
    {
        return $t < 0.5 ? 2 * $t * $t : -1 + (4 - 2 * $t) * $t;
    }

    public static function easeInCubic(float $t): float
    {
        return $t * $t * $t;
    }

    public static function easeOutCubic(float $t): float
    {
        return 1 + (--$t) * $t * $t;
    }

    public static function easeInOutCubic(float $t): float
    {
        return $t < 0.5 ? 4 * $t * $t * $t : 1 + (--$t) * (2 * (--$t)) * (2 * $t);
    }

    public static function easeOutElastic(float $t): float
    {
        if ($t === 0 || $t === 1) {
            return $t;
        }

        $p = 0.3;
        $s = $p / 4;

        return pow(2, -10 * $t) * sin(($t - $s) * (2 * M_PI) / $p) + 1;
    }

    public static function easeOutBounce(float $t): float
    {
        if ($t < 1 / 2.75) {
            return 7.5625 * $t * $t;
        } elseif ($t < 2 / 2.75) {
            $t -= 1.5 / 2.75;

            return 7.5625 * $t * $t + 0.75;
        } elseif ($t < 2.5 / 2.75) {
            $t -= 2.25 / 2.75;

            return 7.5625 * $t * $t + 0.9375;
        }
        $t -= 2.625 / 2.75;

        return 7.5625 * $t * $t + 0.984375;
    }
}

class AnimatedUI
{
    private array $boxes = [];

    private float $time = 0;

    private int $activeBox = 0;

    public function __construct()
    {
        $this->boxes = [
            ['x' => 5, 'y' => 5, 'width' => 30, 'height' => 8, 'color' => CYAN, 'title' => 'Slide In'],
            ['x' => 40, 'y' => 5, 'width' => 30, 'height' => 8, 'color' => YELLOW, 'title' => 'Fade In'],
            ['x' => 75, 'y' => 5, 'width' => 30, 'height' => 8, 'color' => GREEN, 'title' => 'Scale Up'],
            ['x' => 5, 'y' => 15, 'width' => 30, 'height' => 8, 'color' => MAGENTA, 'title' => 'Rotate In'],
            ['x' => 40, 'y' => 15, 'width' => 30, 'height' => 8, 'color' => BLUE, 'title' => 'Bounce'],
            ['x' => 75, 'y' => 15, 'width' => 30, 'height' => 8, 'color' => RED, 'title' => 'Elastic'],
        ];
    }

    public function render(): void
    {
        $this->time += 0.02; // Animation speed

        // Title
        moveCursor(1, 2);
        echo BOLD . 'Animated Transitions Demo' . RESET;
        moveCursor(2, 2);
        echo DIM . 'Watch smooth animations with different easing functions' . RESET;

        // Render each box with its animation
        $this->renderSlideIn(0);
        $this->renderFadeIn(1);
        $this->renderScaleUp(2);
        $this->renderRotateIn(3);
        $this->renderBounce(4);
        $this->renderElastic(5);

        // Progress bar showing animation cycle
        $this->renderProgressIndicator();

        // Animated connecting lines
        $this->renderConnectingLines();

        // Transition state indicators
        $this->renderStateIndicators();
    }

    private function renderSlideIn(int $index): void
    {
        $box = $this->boxes[$index];
        $progress = fmod($this->time, 2) / 2;
        $eased = Easing::easeOutCubic($progress);

        // Slide from left
        $currentX = -$box['width'] + ($box['x'] + $box['width']) * $eased;

        if ($currentX > 0) {
            $this->drawBox(
                $box['y'],
                max(1, (int) $currentX),
                min($box['width'], (int) ($currentX + $box['width'])),
                $box['height'],
                $box['title'],
                $box['color']
            );
        }
    }

    private function renderFadeIn(int $index): void
    {
        $box = $this->boxes[$index];
        $progress = fmod($this->time, 2) / 2;
        $eased = Easing::easeInOutQuad($progress);

        // Simulate fade with characters
        $chars = [' ', '░', '▒', '▓', '█'];
        $charIndex = min(4, (int) ($eased * 5));

        $this->drawBoxWithChar(
            $box['y'],
            $box['x'],
            $box['width'],
            $box['height'],
            $box['title'],
            $box['color'],
            $chars[$charIndex]
        );
    }

    private function renderScaleUp(int $index): void
    {
        $box = $this->boxes[$index];
        $progress = fmod($this->time, 2) / 2;
        $eased = Easing::easeOutQuad($progress);

        // Scale from center
        $currentWidth = max(1, (int) ($box['width'] * $eased));
        $currentHeight = max(1, (int) ($box['height'] * $eased));
        $offsetX = ($box['width'] - $currentWidth) / 2;
        $offsetY = ($box['height'] - $currentHeight) / 2;

        $this->drawBox(
            $box['y'] + (int) $offsetY,
            $box['x'] + (int) $offsetX,
            $currentWidth,
            $currentHeight,
            $eased > 0.5 ? $box['title'] : '',
            $box['color']
        );
    }

    private function renderRotateIn(int $index): void
    {
        $box = $this->boxes[$index];
        $progress = fmod($this->time, 2) / 2;
        $eased = Easing::easeOutCubic($progress);

        // Simulate rotation with changing characters
        $frames = ['|', '/', '─', '\\', '│'];
        $frameIndex = (int) ($eased * count($frames));

        if ($frameIndex >= count($frames) - 1) {
            $this->drawBox($box['y'], $box['x'], $box['width'], $box['height'], $box['title'], $box['color']);
        } else {
            // Draw rotating frame
            moveCursor($box['y'] + $box['height'] / 2, $box['x'] + $box['width'] / 2);
            echo $box['color'] . $frames[$frameIndex] . RESET;
        }
    }

    private function renderBounce(int $index): void
    {
        $box = $this->boxes[$index];
        $progress = fmod($this->time, 2) / 2;
        $eased = Easing::easeOutBounce($progress);

        // Bounce from top
        $currentY = (int) ($box['y'] * $eased);

        $this->drawBox(
            max(1, $currentY),
            $box['x'],
            $box['width'],
            $box['height'],
            $box['title'],
            $box['color']
        );
    }

    private function renderElastic(int $index): void
    {
        $box = $this->boxes[$index];
        $progress = fmod($this->time, 2) / 2;
        $eased = Easing::easeOutElastic($progress);

        // Elastic width
        $currentWidth = max(3, (int) ($box['width'] * $eased));

        $this->drawBox(
            $box['y'],
            $box['x'],
            $currentWidth,
            $box['height'],
            $box['title'],
            $box['color']
        );
    }

    private function renderProgressIndicator(): void
    {
        moveCursor(25, 2);
        echo 'Animation Progress: ';

        $progress = fmod($this->time, 2) / 2;
        $barWidth = 30;
        $filled = (int) ($progress * $barWidth);

        for ($i = 0; $i < $barWidth; $i++) {
            if ($i < $filled) {
                echo CYAN . '█' . RESET;
            } else {
                echo DIM . '░' . RESET;
            }
        }

        echo sprintf(' %3d%%', (int) ($progress * 100));
    }

    private function renderConnectingLines(): void
    {
        $progress = fmod($this->time * 2, 1);

        // Animated dots between boxes
        for ($i = 0; $i < 3; $i++) {
            moveCursor(9, 36 + $i * 35);
            if (abs($progress - ($i * 0.3)) < 0.1) {
                echo YELLOW . '●' . RESET;
            } else {
                echo DIM . '·' . RESET;
            }
        }
    }

    private function renderStateIndicators(): void
    {
        moveCursor(27, 2);
        echo 'Easing Functions: ';

        $functions = ['Cubic', 'Quad', 'Elastic', 'Bounce'];
        $active = (int) (fmod($this->time, 8) / 2);

        foreach ($functions as $i => $func) {
            if ($i === $active) {
                echo CYAN . "[{$func}]" . RESET . ' ';
            } else {
                echo DIM . $func . RESET . ' ';
            }
        }
    }

    private function drawBox(int $row, int $col, int $width, int $height, string $title, string $color): void
    {
        if ($width < 3 || $height < 3) {
            return;
        }

        // Top
        moveCursor($row, $col);
        echo $color . '┌';
        if ($title && $width > mb_strlen($title) + 4) {
            echo '─ ' . $title . ' ';
            echo str_repeat('─', $width - mb_strlen($title) - 5);
        } else {
            echo str_repeat('─', $width - 2);
        }
        echo '┐' . RESET;

        // Sides
        for ($i = 1; $i < $height - 1; $i++) {
            moveCursor($row + $i, $col);
            echo $color . '│' . RESET;
            moveCursor($row + $i, $col + $width - 1);
            echo $color . '│' . RESET;
        }

        // Bottom
        moveCursor($row + $height - 1, $col);
        echo $color . '└' . str_repeat('─', $width - 2) . '┘' . RESET;
    }

    private function drawBoxWithChar(int $row, int $col, int $width, int $height, string $title, string $color, string $char): void
    {
        for ($y = 0; $y < $height; $y++) {
            moveCursor($row + $y, $col);
            echo $color . str_repeat($char, $width) . RESET;
        }

        if ($char === '█' && $title) {
            moveCursor($row + 1, $col + 2);
            echo "\033[30m" . $title . RESET; // Black text on colored background
        }
    }
}

// Main execution
echo CLEAR . HIDE_CURSOR;

$ui = new AnimatedUI;

while (true) {
    $ui->render();
    usleep(20000); // 20ms for smooth animation
}
