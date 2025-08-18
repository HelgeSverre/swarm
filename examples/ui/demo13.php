#!/usr/bin/env php
<?php

/**
 * Demo 13: Terminal Notifications
 * Toast notifications, alerts, and notification center
 */
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const CLEAR = "\033[2J\033[H";
const HIDE_CURSOR = "\033[?25l";

// Notification types
const N_SUCCESS = ['bg' => "\033[48;5;22m", 'fg' => "\033[38;5;120m", 'icon' => '✓'];
const N_ERROR = ['bg' => "\033[48;5;52m", 'fg' => "\033[38;5;203m", 'icon' => '✗'];
const N_WARNING = ['bg' => "\033[48;5;58m", 'fg' => "\033[38;5;221m", 'icon' => '⚠'];
const N_INFO = ['bg' => "\033[48;5;24m", 'fg' => "\033[38;5;117m", 'icon' => 'ℹ'];

function moveCursor(int $row, int $col): void
{
    echo "\033[{$row};{$col}H";
}

class NotificationSystem
{
    private array $toasts = [];

    private array $notifications = [];

    private int $maxToasts = 5;

    private int $frame = 0;

    private bool $centerOpen = false;

    public function __construct()
    {
        $this->initializeNotifications();
    }

    public function render(): void
    {
        echo CLEAR;

        // Main UI
        $this->renderMainUI();

        // Toast notifications (top-right corner)
        $this->renderToasts();

        // Notification center (slide-in panel)
        if ($this->centerOpen) {
            $this->renderNotificationCenter();
        }

        // Status bar with notification count
        $this->renderStatusBar();

        // Simulate new notifications
        $this->simulateNotifications();

        $this->frame++;
    }

    private function initializeNotifications(): void
    {
        $this->notifications = [
            ['type' => 'success', 'title' => 'Build Complete', 'message' => 'Project built successfully', 'time' => time() - 30],
            ['type' => 'info', 'title' => 'Update Available', 'message' => 'Version 2.1.0 is ready', 'time' => time() - 120],
            ['type' => 'warning', 'title' => 'High Memory Usage', 'message' => 'Memory usage at 85%', 'time' => time() - 300],
            ['type' => 'error', 'title' => 'Connection Failed', 'message' => 'Unable to reach server', 'time' => time() - 600],
            ['type' => 'success', 'title' => 'File Saved', 'message' => 'Document saved to disk', 'time' => time() - 900],
        ];
    }

    private function renderMainUI(): void
    {
        moveCursor(2, 2);
        echo BOLD . 'Terminal Notifications Demo' . RESET;

        moveCursor(4, 2);
        echo 'Features:';
        moveCursor(5, 4);
        echo '• Toast notifications with animations';
        moveCursor(6, 4);
        echo '• Notification center with history';
        moveCursor(7, 4);
        echo '• Different notification types';
        moveCursor(8, 4);
        echo '• Auto-dismiss with progress';
        moveCursor(9, 4);
        echo '• Sound indicators (simulated)';

        moveCursor(11, 2);
        echo DIM . "Press 'n' to toggle notification center" . RESET;

        // Trigger buttons
        $this->renderTriggerButtons();
    }

    private function renderTriggerButtons(): void
    {
        $buttons = [
            ['type' => 'success', 'label' => '[S]uccess'],
            ['type' => 'error', 'label' => '[E]rror'],
            ['type' => 'warning', 'label' => '[W]arning'],
            ['type' => 'info', 'label' => '[I]nfo'],
        ];

        moveCursor(13, 2);
        echo 'Trigger notification: ';

        foreach ($buttons as $i => $btn) {
            $style = constant('N_' . mb_strtoupper($btn['type']));
            echo $style['bg'] . $style['fg'] . ' ' . $btn['label'] . ' ' . RESET . ' ';
        }
    }

    private function renderToasts(): void
    {
        $width = (int) exec('tput cols') ?: 80;
        $startCol = $width - 35;
        $startRow = 2;

        foreach ($this->toasts as $i => $toast) {
            if ($i >= $this->maxToasts) {
                break;
            }

            $row = $startRow + ($i * 4);
            $this->renderToast($toast, $row, $startCol);
        }
    }

    private function renderToast(array $toast, int $row, int $col): void
    {
        $style = constant('N_' . mb_strtoupper($toast['type']));
        $age = time() - $toast['time'];
        $maxAge = 5; // 5 seconds before auto-dismiss

        // Slide-in animation
        $slideOffset = 0;
        if ($age < 0.3) {
            $slideOffset = (int) ((1 - $age / 0.3) * 10);
        }

        // Fade-out animation
        $opacity = 1;
        if ($age > $maxAge - 1) {
            $opacity = ($maxAge - $age);
        }

        if ($opacity <= 0) {
            return;
        }

        $actualCol = $col + $slideOffset;

        // Shadow
        moveCursor($row + 1, $actualCol + 1);
        echo "\033[38;5;238m" . str_repeat('░', 30) . RESET;

        // Toast body
        moveCursor($row, $actualCol);
        echo $style['bg'] . '╭' . str_repeat('─', 30) . '╮' . RESET;

        moveCursor($row + 1, $actualCol);
        echo $style['bg'] . '│ ' . $style['fg'] . $style['icon'] . ' ' . BOLD . mb_str_pad($toast['title'], 26) . RESET . $style['bg'] . ' │' . RESET;

        moveCursor($row + 2, $actualCol);
        echo $style['bg'] . '│ ' . "\033[38;5;250m" . mb_str_pad(mb_substr($toast['message'], 0, 27), 28) . RESET . $style['bg'] . ' │' . RESET;

        // Progress bar
        moveCursor($row + 3, $actualCol);
        echo $style['bg'] . '╰';

        $progress = 1 - ($age / $maxAge);
        $barWidth = 30;
        $filled = (int) ($progress * $barWidth);

        for ($i = 0; $i < $barWidth; $i++) {
            if ($i < $filled) {
                echo $style['fg'] . '━' . RESET . $style['bg'];
            } else {
                echo '─';
            }
        }

        echo '╯' . RESET;

        // Close button
        moveCursor($row + 1, $actualCol + 29);
        echo $style['bg'] . "\033[38;5;240m" . '×' . RESET;

        // Sound indicator
        if ($age < 0.5) {
            moveCursor($row, $actualCol - 2);
            echo $style['fg'] . '♪' . RESET;
        }
    }

    private function renderNotificationCenter(): void
    {
        $width = (int) exec('tput cols') ?: 80;
        $height = (int) exec('tput lines') ?: 24;

        $panelWidth = 40;
        $panelHeight = $height - 4;
        $startCol = $width - $panelWidth - 2;

        // Semi-transparent overlay effect
        for ($row = 2; $row <= $panelHeight + 2; $row++) {
            moveCursor($row, $startCol - 2);
            echo "\033[38;5;238m" . '░░' . RESET;
        }

        // Panel background
        for ($row = 2; $row <= $panelHeight + 2; $row++) {
            moveCursor($row, $startCol);
            echo "\033[48;5;235m" . str_repeat(' ', $panelWidth) . RESET;
        }

        // Header
        moveCursor(2, $startCol);
        echo "\033[48;5;237m" . str_repeat(' ', $panelWidth) . RESET;
        moveCursor(2, $startCol + 2);
        echo "\033[48;5;237m" . BOLD . "\033[38;5;250m" . '🔔 Notifications' . RESET;
        moveCursor(2, $startCol + $panelWidth - 10);
        echo "\033[48;5;237m" . DIM . "\033[38;5;245m" . 'Clear All' . RESET;

        // Separator
        moveCursor(3, $startCol);
        echo "\033[38;5;240m" . str_repeat('─', $panelWidth) . RESET;

        // Notification list
        $row = 4;
        foreach ($this->notifications as $notif) {
            if ($row > $panelHeight) {
                break;
            }

            $this->renderNotificationItem($notif, $row, $startCol + 2, $panelWidth - 4);
            $row += 3;
        }

        // Footer
        moveCursor($panelHeight + 2, $startCol);
        echo "\033[48;5;237m" . str_repeat(' ', $panelWidth) . RESET;
        moveCursor($panelHeight + 2, $startCol + 2);
        echo "\033[48;5;237m" . DIM . count($this->notifications) . ' notifications' . RESET;
    }

    private function renderNotificationItem(array $notif, int $row, int $col, int $width): void
    {
        $style = constant('N_' . mb_strtoupper($notif['type']));

        moveCursor($row, $col);
        echo $style['fg'] . $style['icon'] . RESET . ' ';
        echo BOLD . "\033[38;5;250m" . $notif['title'] . RESET;

        moveCursor($row, $col + $width - 10);
        echo DIM . $this->getRelativeTime($notif['time']) . RESET;

        moveCursor($row + 1, $col + 2);
        echo "\033[38;5;245m" . mb_substr($notif['message'], 0, $width - 2) . RESET;
    }

    private function renderStatusBar(): void
    {
        $height = (int) exec('tput lines') ?: 24;
        $width = (int) exec('tput cols') ?: 80;

        moveCursor($height - 1, 1);
        echo "\033[48;5;236m" . str_repeat(' ', $width) . RESET;

        moveCursor($height - 1, 2);
        echo "\033[48;5;236m";

        // Notification indicator
        $unreadCount = count(array_filter($this->notifications, fn ($n) => time() - $n['time'] < 300));
        if ($unreadCount > 0) {
            echo "\033[38;5;221m" . '🔔 ' . $unreadCount . ' new' . RESET . "\033[48;5;236m │ ";
        }

        // Status
        echo "\033[38;5;250m" . 'Status: ' . "\033[38;5;120m" . '● Online' . RESET . "\033[48;5;236m │ ";

        // Time
        echo "\033[38;5;245m" . date('H:i:s') . RESET;

        // Right side - controls
        moveCursor($height - 1, $width - 30);
        echo "\033[48;5;236m" . DIM . '[N] Center │ [C] Clear │ [Q] Quit' . RESET;
    }

    private function simulateNotifications(): void
    {
        // Add new toast every 3 seconds
        if ($this->frame % 30 === 0) {
            $types = ['success', 'error', 'warning', 'info'];
            $messages = [
                'success' => ['Task completed', 'File uploaded', 'Changes saved'],
                'error' => ['Operation failed', 'Access denied', 'Network error'],
                'warning' => ['Low disk space', 'Rate limit reached', 'Session expiring'],
                'info' => ['New message', 'Update ready', 'Sync complete'],
            ];

            $type = $types[array_rand($types)];
            $messageList = $messages[$type];

            array_unshift($this->toasts, [
                'type' => $type,
                'title' => $messageList[array_rand($messageList)],
                'message' => 'Additional details here...',
                'time' => time(),
            ]);

            // Also add to notification center
            array_unshift($this->notifications, [
                'type' => $type,
                'title' => $messageList[array_rand($messageList)],
                'message' => 'Additional details about the notification',
                'time' => time(),
            ]);
        }

        // Remove old toasts
        $this->toasts = array_filter($this->toasts, fn ($t) => time() - $t['time'] < 6);

        // Toggle notification center periodically for demo
        if ($this->frame % 100 === 50) {
            $this->centerOpen = ! $this->centerOpen;
        }
    }

    private function getRelativeTime(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }

        return floor($diff / 86400) . 'd ago';
    }
}

// Main execution
echo HIDE_CURSOR;

$notificationSystem = new NotificationSystem;

while (true) {
    $notificationSystem->render();
    usleep(100000); // 100ms refresh
}
