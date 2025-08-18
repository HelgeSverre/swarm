#!/usr/bin/env php
<?php

/**
 * Demo 6: Interactive Elements with Visual Feedback
 * Shows hover effects simulation, focus indicators, keyboard hints
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const REVERSE = "\033[7m";
const UNDERLINE = "\033[4m";
const CLEAR = "\033[2J\033[H";
const HIDE_CURSOR = "\033[?25l";

// Colors
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const CYAN = "\033[36m";
const MAGENTA = "\033[35m";
const WHITE = "\033[37m";
const BRIGHT_CYAN = "\033[96m";
const BRIGHT_BLUE = "\033[94m";

// Backgrounds
const BG_HOVER = "\033[48;5;238m";
const BG_ACTIVE = "\033[48;5;240m";
const BG_SELECTED = "\033[48;5;27m";

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class InteractiveUI
{
    private int $selectedMenuItem = 0;

    private int $selectedButton = 0;

    private bool $dropdownOpen = false;

    private array $checkedItems = [false, true, false];

    private int $sliderValue = 65;

    private string $inputValue = '';

    private int $focusedElement = 0; // 0=menu, 1=buttons, 2=dropdown, 3=checkboxes, 4=slider, 5=input

    private array $menuItems = [
        ['label' => 'Dashboard', 'icon' => '📊', 'shortcut' => 'D'],
        ['label' => 'Projects', 'icon' => '📁', 'shortcut' => 'P'],
        ['label' => 'Tasks', 'icon' => '✓', 'shortcut' => 'T'],
        ['label' => 'Settings', 'icon' => '⚙', 'shortcut' => 'S'],
    ];

    private array $buttons = [
        ['label' => 'Save', 'style' => 'primary', 'shortcut' => 'Ctrl+S'],
        ['label' => 'Cancel', 'style' => 'secondary', 'shortcut' => 'Esc'],
        ['label' => 'Delete', 'style' => 'danger', 'shortcut' => 'Del'],
    ];

    private array $dropdownItems = ['Option 1', 'Option 2', 'Option 3', 'Custom...'];

    public function renderMenu(int $row): void
    {
        moveCursor($row, 2);
        echo BOLD . 'Navigation Menu' . RESET . ' ' . DIM . '(Use ← → or shortcuts)' . RESET;

        moveCursor($row + 2, 2);
        foreach ($this->menuItems as $i => $item) {
            $isSelected = $i === $this->selectedMenuItem;
            $isFocused = $this->focusedElement === 0;

            if ($isSelected && $isFocused) {
                echo BG_SELECTED . WHITE;
            } elseif ($isSelected) {
                echo BG_HOVER;
            }

            echo " {$item['icon']} {$item['label']} ";

            if ($isSelected) {
                echo RESET . ' ';
                if ($isFocused) {
                    echo BRIGHT_CYAN . '▼' . RESET;
                }
            } else {
                echo ' ';
            }

            echo '  ';
        }

        // Show shortcuts
        moveCursor($row + 3, 2);
        echo DIM . 'Shortcuts: ';
        foreach ($this->menuItems as $i => $item) {
            echo UNDERLINE . $item['shortcut'] . RESET . DIM . ' ' . $item['label'];
            if ($i < count($this->menuItems) - 1) {
                echo ' │ ';
            }
        }
        echo RESET;
    }

    public function renderButtons(int $row): void
    {
        moveCursor($row, 2);
        echo BOLD . 'Action Buttons' . RESET . ' ' . DIM . '(Tab to focus, Space to press)' . RESET;

        moveCursor($row + 2, 2);
        foreach ($this->buttons as $i => $button) {
            $isSelected = $i === $this->selectedButton;
            $isFocused = $this->focusedElement === 1;

            // Button styling based on type
            $color = match ($button['style']) {
                'primary' => BRIGHT_BLUE,
                'secondary' => WHITE,
                'danger' => "\033[91m", // Bright red
                default => WHITE
            };

            if ($isSelected && $isFocused) {
                // Focused state - highlighted border
                echo $color . '┌' . str_repeat('─', mb_strlen($button['label']) + 2) . '┐' . RESET;
                moveCursor($row + 3, 2 + ($i * 15));
                echo $color . '│ ' . BOLD . $button['label'] . RESET . $color . ' │' . RESET;
                moveCursor($row + 4, 2 + ($i * 15));
                echo $color . '└' . str_repeat('─', mb_strlen($button['label']) + 2) . '┘' . RESET;
            } else {
                // Normal state
                moveCursor($row + 3, 2 + ($i * 15));
                if ($isSelected) {
                    echo BG_HOVER;
                }
                echo $color . '[ ' . $button['label'] . ' ]' . RESET;
            }
        }

        // Shortcut hints
        moveCursor($row + 5, 2);
        echo DIM;
        foreach ($this->buttons as $i => $button) {
            echo $button['shortcut'] . '  ';
            if ($i < count($this->buttons) - 1) {
                echo '    ';
            }
        }
        echo RESET;
    }

    public function renderDropdown(int $row): void
    {
        moveCursor($row, 2);
        echo BOLD . 'Dropdown Menu' . RESET . ' ' . DIM . '(Enter to toggle)' . RESET;

        moveCursor($row + 2, 2);

        $isFocused = $this->focusedElement === 2;

        // Dropdown header
        if ($isFocused) {
            echo BG_ACTIVE;
        }
        echo '  Select Option  ' . ($this->dropdownOpen ? '▲' : '▼') . ' ';
        echo RESET;

        // Dropdown items (if open)
        if ($this->dropdownOpen) {
            moveCursor($row + 3, 2);
            echo '┌──────────────────┐';

            foreach ($this->dropdownItems as $i => $item) {
                moveCursor($row + 4 + $i, 2);
                echo '│ ';
                if ($i === $this->selectedMenuItem) {
                    echo REVERSE;
                }
                echo mb_str_pad($item, 16);
                echo RESET . ' │';
            }

            moveCursor($row + 4 + count($this->dropdownItems), 2);
            echo '└──────────────────┘';
        }
    }

    public function renderCheckboxes(int $row): void
    {
        moveCursor($row, 2);
        echo BOLD . 'Checkboxes' . RESET . ' ' . DIM . '(Space to toggle)' . RESET;

        $items = ['Enable notifications', 'Auto-save', 'Dark mode'];

        foreach ($items as $i => $label) {
            moveCursor($row + 2 + $i, 2);

            $isFocused = $this->focusedElement === 3;
            $isChecked = $this->checkedItems[$i];

            if ($isFocused && $i === 0) {
                echo BG_HOVER;
            }

            // Checkbox icon
            if ($isChecked) {
                echo GREEN . '☑' . RESET;
            } else {
                echo '☐';
            }

            echo ' ' . $label;

            if ($isFocused && $i === 0) {
                echo RESET;
            }
        }
    }

    public function renderSlider(int $row): void
    {
        moveCursor($row, 2);
        echo BOLD . 'Volume Slider' . RESET . ' ' . DIM . '(← → to adjust)' . RESET;

        moveCursor($row + 2, 2);

        $isFocused = $this->focusedElement === 4;
        $width = 30;
        $position = (int) (($this->sliderValue / 100) * $width);

        echo '0 ';

        if ($isFocused) {
            echo BRIGHT_CYAN;
        }

        for ($i = 0; $i < $width; $i++) {
            if ($i < $position) {
                echo '═';
            } elseif ($i == $position) {
                echo '●';
            } else {
                echo '─';
            }
        }

        echo RESET . ' 100';

        moveCursor($row + 3, 2 + $position + 2);
        echo CYAN . $this->sliderValue . '%' . RESET;
    }

    public function renderInput(int $row): void
    {
        moveCursor($row, 2);
        echo BOLD . 'Text Input' . RESET . ' ' . DIM . '(Type to enter text)' . RESET;

        moveCursor($row + 2, 2);

        $isFocused = $this->focusedElement === 5;

        if ($isFocused) {
            echo BRIGHT_CYAN . '┌' . str_repeat('─', 30) . '┐' . RESET;
            moveCursor($row + 3, 2);
            echo BRIGHT_CYAN . '│' . RESET;
            echo ' ' . mb_str_pad($this->inputValue . ($isFocused ? '_' : ''), 29);
            echo BRIGHT_CYAN . '│' . RESET;
            moveCursor($row + 4, 2);
            echo BRIGHT_CYAN . '└' . str_repeat('─', 30) . '┘' . RESET;
        } else {
            echo '┌' . str_repeat('─', 30) . '┐';
            moveCursor($row + 3, 2);
            echo '│ ' . mb_str_pad($this->inputValue, 29) . '│';
            moveCursor($row + 4, 2);
            echo '└' . str_repeat('─', 30) . '┘';
        }

        if (! empty($this->inputValue)) {
            moveCursor($row + 5, 2);
            echo DIM . 'Current value: ' . RESET . YELLOW . $this->inputValue . RESET;
        }
    }

    public function renderKeyboardHints(): void
    {
        $height = (int) exec('tput lines') ?: 24;

        moveCursor($height - 3, 2);
        echo BG_HOVER . ' Keyboard Controls ' . RESET;

        moveCursor($height - 2, 2);
        echo DIM . 'Tab' . RESET . ' Next element │ ';
        echo DIM . 'Shift+Tab' . RESET . ' Previous │ ';
        echo DIM . 'Space/Enter' . RESET . ' Activate │ ';
        echo DIM . 'Arrow Keys' . RESET . ' Navigate';

        moveCursor($height - 1, 2);
        echo DIM . 'Current Focus: ' . RESET;
        $focusNames = ['Menu', 'Buttons', 'Dropdown', 'Checkboxes', 'Slider', 'Input'];
        echo YELLOW . $focusNames[$this->focusedElement] . RESET;
        echo ' │ ' . DIM . 'Press Ctrl+C to exit' . RESET;
    }

    public function simulate(): void
    {
        // Simulate focus changes
        static $frame = 0;
        $frame++;

        if ($frame % 30 == 0) {
            $this->focusedElement = ($this->focusedElement + 1) % 6;
        }

        if ($frame % 10 == 0) {
            $this->selectedMenuItem = ($this->selectedMenuItem + 1) % count($this->menuItems);
        }

        if ($frame % 15 == 0) {
            $this->sliderValue = min(100, $this->sliderValue + 5);
            if ($this->sliderValue >= 100) {
                $this->sliderValue = 0;
            }
        }

        if ($frame % 40 == 0) {
            $this->dropdownOpen = ! $this->dropdownOpen;
        }

        if ($frame % 25 == 0) {
            $this->checkedItems[rand(0, 2)] = ! $this->checkedItems[rand(0, 2)];
        }
    }
}

// Setup
echo HIDE_CURSOR;
echo CLEAR;

$ui = new InteractiveUI;

// Main loop
while (true) {
    // Title
    moveCursor(1, 2);
    echo BOLD . 'Interactive Elements Demo' . RESET;
    moveCursor(2, 2);
    echo DIM . str_repeat('─', 70) . RESET;

    // Render components
    $ui->renderMenu(4);
    $ui->renderButtons(9);
    $ui->renderDropdown(16);
    $ui->renderCheckboxes(16);
    $ui->renderSlider(21);
    $ui->renderInput(26);

    // Keyboard hints
    $ui->renderKeyboardHints();

    // Simulate interactions
    $ui->simulate();

    usleep(100000); // 100ms refresh
}
