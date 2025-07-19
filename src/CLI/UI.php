<?php

/** @noinspection PhpUnused */

namespace HelgeSverre\Swarm\CLI;

use HelgeSverre\Swarm\Agent\AgentResponse;
use HelgeSverre\Swarm\CLI\Activity\ActivityEntry;
use HelgeSverre\Swarm\CLI\Activity\ConversationEntry;
use HelgeSverre\Swarm\CLI\Activity\NotificationEntry;
use HelgeSverre\Swarm\CLI\Activity\ToolCallEntry;
use HelgeSverre\Swarm\Core\ToolResponse;
use HelgeSverre\Swarm\Enums\CLI\AnsiColor;
use HelgeSverre\Swarm\Enums\CLI\BoxCharacter;
use HelgeSverre\Swarm\Enums\CLI\NotificationType;
use HelgeSverre\Swarm\Enums\CLI\StatusIcon;
use HelgeSverre\Swarm\Enums\CLI\ThemeColor;

/**
 * Rich Terminal User Interface for Swarm CLI
 *
 * Uses ANSI escape codes for colors, positioning, and formatting
 * Provides a real-time updating interface similar to modern CLI tools
 */
class UI
{
    protected int $terminalWidth;

    protected int $terminalHeight;

    /** @var ActivityEntry[] */
    protected array $history = [];

    protected int $maxHistoryLines = 20;

    protected int $currentLine = 1;

    protected bool $isProcessing = false;

    protected float $processingStartTime = 0;

    protected int $animationFrame = 0;

    protected string $processingMessage = '';

    protected string $currentOperation = '';

    public function __construct()
    {
        $this->detectTerminalSize();
        $this->enableRawMode();

        // Register cleanup on exit
        register_shutdown_function([$this, 'cleanup']);
    }

    public function showWelcome(): void
    {
        $this->clearScreen();

        // Center the welcome box
        $boxWidth = 45;
        $leftPadding = str_repeat(' ', (int) (($this->terminalWidth - $boxWidth) / 2));

        echo "\n\n";
        echo $leftPadding . $this->colorize(BoxCharacter::TopLeft->getChar() . str_repeat(BoxCharacter::Horizontal->getChar(), $boxWidth - 2) . BoxCharacter::TopRight->getChar(), ThemeColor::Border) . "\n";
        echo $leftPadding . $this->colorize(BoxCharacter::Vertical->getChar(), ThemeColor::Border) .
            $this->colorize(mb_str_pad('Swarm CLI v1.0', $boxWidth - 2, ' ', STR_PAD_BOTH), ThemeColor::Header, 'bold') .
            $this->colorize(BoxCharacter::Vertical->getChar(), ThemeColor::Border) . "\n";
        echo $leftPadding . $this->colorize(BoxCharacter::Vertical->getChar(), ThemeColor::Border) .
            $this->colorize(mb_str_pad('AI-Powered Coding Assistant', $boxWidth - 2, ' ', STR_PAD_BOTH), ThemeColor::Muted) .
            $this->colorize(BoxCharacter::Vertical->getChar(), ThemeColor::Border) . "\n";
        echo $leftPadding . $this->colorize(BoxCharacter::BottomLeft->getChar() . str_repeat(BoxCharacter::Horizontal->getChar(), $boxWidth - 2) . BoxCharacter::BottomRight->getChar(), ThemeColor::Border) . "\n";
        echo "\n";
        echo $this->colorize('  🤖 Ready to help with your coding tasks!', ThemeColor::Success) . "\n";
        echo $this->colorize('  💡 Try: "Create a Laravel migration for users"', ThemeColor::Warning) . "\n";
        echo $this->colorize("  📝 Type 'help' for commands or 'exit' to quit", ThemeColor::Muted) . "\n";
        echo "\n";

        $this->waitForKeypress();
    }

    public function prompt(string $message): string
    {
        // Draw input box at the bottom
        $inputStartRow = $this->terminalHeight - 3;
        $this->moveCursor($inputStartRow, 1);

        // Draw input box
        echo $this->drawBoxTop('', ThemeColor::Border);
        echo $this->drawBoxLine('', ThemeColor::Border); // Empty line for input
        echo $this->drawBoxBottom(ThemeColor::Border);

        // Position cursor inside the box for the prompt
        $this->moveCursor($inputStartRow + 1, 3);

        // Restore normal terminal mode for input
        $this->restoreNormalMode();

        // Use protected input handler that prevents erasing the prompt
        $promptWithSpace = $message . ' ';
        $input = InputHandler::readLine($promptWithSpace, ThemeColor::Accent->toEscapeCode());

        // Add to command history if not empty
        if (trim($input) !== '') {
            InputHandler::addHistory(trim($input));
        }

        // Don't move to next line - stay in the input box
        // The next refresh will clear the screen anyway

        return trim($input);
    }

    public function displayResponse(AgentResponse $response): void
    {
        $entry = new ConversationEntry('assistant', $response->getMessage(), time());

        $this->addToHistory($entry);
        // Don't show notification as it disrupts the UI flow
        // The response will be shown in the next refresh cycle
    }

    public function startProcessing(): void
    {
        $this->isProcessing = true;
        $this->processingStartTime = microtime(true);
        $this->animationFrame = 0;

        // Select a random message for this processing session
        $messages = ['Processing', 'Thinking', 'Analyzing', 'Working on it'];
        $this->processingMessage = $messages[array_rand($messages)];

        // Show initial frame
        $this->showProcessing();
    }

    public function stopProcessing(): void
    {
        $this->isProcessing = false;
        $this->currentOperation = ''; // Clear current operation

        // Clear the processing line
        $inputStartRow = $this->terminalHeight - 2;
        $this->moveCursor($inputStartRow, 3);
        echo "\033[K"; // Clear line
    }

    public function showProcessing(): void
    {
        if (! $this->isProcessing) {
            return;
        }

        // Show a processing indicator in the input area
        $inputStartRow = $this->terminalHeight - 2;
        $this->moveCursor($inputStartRow, 3);

        // Clear the line first
        echo "\033[K";

        // Show processing message with animated spinner
        $spinnerChars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

        // Use the stored message and current animation frame
        $spinner = $spinnerChars[$this->animationFrame % count($spinnerChars)];
        $elapsed = number_format(microtime(true) - $this->processingStartTime, 1, '.', '');

        // Show current operation if set
        $message = $this->currentOperation ?: $this->processingMessage;

        echo $this->colorize("{$spinner}  {$message}... ({$elapsed}s)", ThemeColor::Warning);

        // Flush output immediately for real-time updates
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();

        // Increment animation frame for next call
        $this->animationFrame++;
    }

    public function updateProcessingMessage(string $message): void
    {
        $this->currentOperation = $message;
        // Immediately show the update
        $this->showProcessing();
    }

    public function showNotification(string $message, string $type = 'info'): void
    {
        $notificationType = NotificationType::fromString($type);
        $icon = $notificationType->getIcon();

        // Create notification entry with icon included in message
        $entry = new NotificationEntry("{$icon} {$message}", $notificationType, time());
        $this->addToHistory($entry);
        // Immediately refresh to show the notification
        $this->refresh([]);
    }

    public function displayError(string $error): void
    {
        // Clean the error message of newlines for history
        $cleanError = str_replace(["\r\n", "\r", "\n"], ' ', $error);

        // Create an error conversation entry
        $entry = new ConversationEntry('error', $cleanError, time());
        $this->addToHistory($entry);

        // Also show as notification
        $this->showNotification('Error: ' . mb_substr($cleanError, 0, 100) . '...', 'error');
    }

    public function displayToolCall(string $tool, array $params, $result): void
    {
        // Convert result to ToolResponse if it's an array
        $toolResponse = null;
        if ($result instanceof ToolResponse) {
            $toolResponse = $result;
        } elseif (is_array($result) && isset($result['success'])) {
            $toolResponse = $result['success']
                ? ToolResponse::success($result['data'] ?? [])
                : ToolResponse::error($result['error'] ?? 'Unknown error');
        }

        $entry = new ToolCallEntry($tool, $params, $toolResponse, time());
        $this->addToHistory($entry);
        $this->refresh([]); // Quick refresh to show the tool call
    }

    public function refresh(array $status): void
    {
        $this->clearScreen();
        $this->drawHeader();
        $this->drawTaskStatus($status);
        echo $this->drawBoxLine('', ThemeColor::Border); // Add spacing between sections with border
        $this->drawRecentActivity($status);
        $this->drawFooter();

        // Leave space for input box at the bottom
        $remainingLines = $this->terminalHeight - $this->getCurrentLine() - 5; // Adjusted for extra newline
        if ($remainingLines > 0) {
            echo str_repeat("\n", $remainingLines);
        }
    }

    public function updateTaskProgress(array $tasks): void
    {
        // This would be called during task execution to update the display
        $this->refresh(['tasks' => $tasks]);
    }

    public function cleanup(): void
    {
        // Make sure processing is stopped
        $this->stopProcessing();

        // Ensure terminal is in normal mode
        $this->restoreNormalMode();
        echo AnsiColor::Reset->toEscapeCode();

        // Clear screen and show cursor
        $this->clearScreen();
        $this->moveCursor(1, 1);
        echo "\033[?25h"; // Show cursor
    }

    // Additional utility methods for specific use cases

    public function showSpinner(string $message, callable $task): mixed
    {
        $spinnerChars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $i = 0;

        // Run task in background simulation (for demo purposes)
        echo $this->colorize("  {$spinnerChars[$i]} {$message}", 'yellow');

        $result = $task();

        echo "\r" . $this->colorize("  ✓ {$message}", 'green') . "\n";

        return $result;
    }

    public function showProgressBar(int $current, int $total, string $message = ''): void
    {
        $percent = round(($current / $total) * 100);
        $barLength = 30;
        $filledLength = (int) round(($percent / 100) * $barLength);

        $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);

        echo "\r" . $this->colorize("  [{$bar}] {$percent}% {$message}", 'cyan');

        if ($current >= $total) {
            echo "\n";
        }
    }

    public function clearScreen(): void
    {
        // Clear screen and scrollback buffer, then move cursor to top-left
        echo "\033[2J\033[3J\033[H";
    }

    protected function detectTerminalSize(): void
    {
        // Try to get terminal size
        $output = [];
        exec('stty size 2>/dev/null', $output);

        if (! empty($output[0])) {
            [$height, $width] = explode(' ', $output[0]);
            $this->terminalHeight = (int) $height;
            $this->terminalWidth = (int) $width;
        } else {
            // Try tput as fallback
            $height = exec('tput lines 2>/dev/null');
            $width = exec('tput cols 2>/dev/null');

            if ($height && $width) {
                $this->terminalHeight = (int) $height;
                $this->terminalWidth = (int) $width;
            } else {
                // Final fallback defaults
                $this->terminalHeight = 24;
                $this->terminalWidth = 80;
            }
        }
    }

    protected function enableRawMode(): void
    {
        // We no longer use persistent raw mode
        // Terminal modes are managed per-operation
    }

    protected function colorize(string $text, string|AnsiColor|ThemeColor $color, ?string $style = null): string
    {
        $codes = [];

        if ($style) {
            $styleColor = AnsiColor::tryFrom($style);
            if ($styleColor) {
                $codes[] = $styleColor->toEscapeCode();
            }
        }

        if ($color instanceof ThemeColor) {
            $codes[] = $color->toEscapeCode();
        } elseif ($color instanceof AnsiColor) {
            $codes[] = $color->toEscapeCode();
        } else {
            // String fallback for backwards compatibility
            $ansiColor = AnsiColor::tryFrom($color);
            if ($ansiColor) {
                $codes[] = $ansiColor->toEscapeCode();
            }
        }

        return implode('', $codes) . $text . AnsiColor::Reset->toEscapeCode();
    }

    protected function waitForKeypress(): void
    {
        echo $this->colorize("\n  Press any key to continue...", 'gray');
        // Temporarily enable raw mode just for single keypress
        if (function_exists('system')) {
            system('stty -icanon min 1 time 0 2>/dev/null');
        }
        fgetc(STDIN);
        // Restore normal mode
        $this->restoreNormalMode();
    }

    protected function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    protected function addToHistory(ActivityEntry $entry): void
    {
        $this->history[] = $entry;

        // Keep history manageable
        if (count($this->history) > $this->maxHistoryLines) {
            array_shift($this->history);
        }
    }

    protected function formatParams(array $params): string
    {
        return implode(', ', array_map(
            fn ($key, $value) => sprintf(
                '%s: %s',
                $key,
                json_encode(
                    is_string($value) && mb_strlen($value) > 50
                        ? mb_substr($value, 0, 47) . '...'
                        : $value
                )
            ),
            array_keys($params),
            array_values($params)
        ));
    }

    protected function summarizeResult(mixed $result): string
    {
        if (! is_array($result)) {
            return 'completed';
        }

        $data = $result['data'] ?? $result;

        return match (true) {
            isset($data['files']) => count($data['files']) . ' files found',
            isset($data['stdout']) => 'exit: ' . ($data['return_code'] ?? '?'),
            isset($data['bytes_written']) => $data['bytes_written'] . ' bytes written',
            default => 'completed'
        };
    }

    protected function drawHeader(): void
    {
        $title = 'Swarm - AI Coding Assistant';
        $time = date('H:i:s');

        // Use the new helper with theme colors
        $leftPart = $this->colorize(BoxCharacter::TopLeft->getChar() . BoxCharacter::Horizontal->getChar() . ' ', ThemeColor::Border);
        $titlePart = $this->colorize($title, ThemeColor::Header, 'bold');
        $timePart = $this->colorize($time, ThemeColor::Muted);
        $rightPart = $this->colorize(' ' . BoxCharacter::TopRight->getChar(), ThemeColor::Border);

        $titleLength = mb_strlen($title);
        $timeLength = mb_strlen($time);
        $remainingWidth = $this->terminalWidth - $titleLength - $timeLength - 6;

        echo $leftPart . $titlePart .
            $this->colorize(str_repeat(' ', $remainingWidth), ThemeColor::Border) .
            $timePart . $rightPart . "\n";
    }

    protected function drawTaskStatus(array $status): void
    {
        $tasks = $status['tasks'] ?? [];
        $currentTask = $status['current_task'] ?? null;
        $operation = $status['operation'] ?? '';

        if (empty($tasks) && ! $currentTask) {
            if ($operation) {
                echo $this->drawBoxLine('⚡ ' . ucfirst($operation), ThemeColor::Border, ThemeColor::Accent);
            } else {
                echo $this->drawBoxLine('🎯  No active tasks', ThemeColor::Border, ThemeColor::Muted);
            }

            return;
        }

        if ($currentTask) {
            $currentDescription = $currentTask['description'] ?? '';
            $content = '🎯  Current: ' . $currentDescription;
            echo $this->drawBoxLine($content, ThemeColor::Border, ThemeColor::Accent);

            // Show plan if available
            if (! empty($currentTask['plan'])) {
                $planLines = explode("\n", $currentTask['plan']);
                $firstLine = $this->truncate($planLines[0], $this->terminalWidth - 6);
                echo $this->drawBoxLine('    📋 ' . $firstLine, ThemeColor::Border, ThemeColor::Muted);
            }

            // Show steps if available
            if (! empty($currentTask['steps']) && is_array($currentTask['steps'])) {
                $stepCount = count($currentTask['steps']);
                $stepText = $stepCount === 1 ? '1 step' : $stepCount . ' steps';
                echo $this->drawBoxLine('    📝 ' . $stepText . ' planned', ThemeColor::Border, ThemeColor::Muted);
            }
        }

        // Draw task list
        $maxTasks = min(5, count($tasks));
        for ($i = 0; $i < $maxTasks; $i++) {
            $task = $tasks[$i];
            $icon = StatusIcon::forTaskStatus($task['status'])->getIcon();
            $color = $this->getStatusColor($task['status']);
            $description = $task['description'] ?? '';

            $content = "  {$icon}  {$description}";
            echo $this->drawBoxLine($content, ThemeColor::Border, $color);
        }

        if (count($tasks) > $maxTasks) {
            $moreText = '  ... and ' . (count($tasks) - $maxTasks) . ' more';
            echo $this->drawBoxLine($moreText, ThemeColor::Border, ThemeColor::Muted);
        }
    }

    protected function truncate(?string $text, int $length): string
    {
        if ($text === null) {
            return '';
        }

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 3) . '...' : $text;
    }

    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'bright_green',
            'running', 'executing' => 'bright_yellow',
            'failed' => 'bright_red',
            'pending' => 'gray',
            default => 'white'
        };
    }

    protected function drawRecentActivity(array $status = []): void
    {
        echo $this->drawBoxSeparator('Recent Activity', ThemeColor::Border);

        // Combine conversation history and tool log from synced state
        /** @var ActivityEntry[] $activity */
        $activity = [];

        // Add conversation history
        if (isset($status['conversation_history'])) {
            foreach ($status['conversation_history'] as $entry) {
                $role = $entry['role'] ?? 'assistant';
                $content = $entry['content'] ?? '';
                $timestamp = $entry['timestamp'] ?? time();

                $activity[] = new ConversationEntry($role, $content, $timestamp);
            }
        }

        // Add tool executions
        if (isset($status['tool_log'])) {
            foreach ($status['tool_log'] as $log) {
                if ($log['status'] === 'completed' && isset($log['tool'])) {
                    $toolResponse = null;
                    if (isset($log['response']) && is_array($log['response'])) {
                        $toolResponse = ToolResponse::success($log['response']);
                    }

                    $activity[] = new ToolCallEntry(
                        $log['tool'],
                        $log['params'] ?? [],
                        $toolResponse,
                        $log['timestamp'] ?? time()
                    );
                }
            }
        }

        // Sort by timestamp and get recent items
        usort($activity, fn ($a, $b) => $a->timestamp - $b->timestamp);
        $recentActivity = array_slice($activity, -8);

        // Fallback to internal history if no synced data
        if (empty($recentActivity)) {
            $recentActivity = array_slice($this->history, -8);
        }

        if (empty($recentActivity)) {
            echo $this->drawBoxLine('No recent activity', ThemeColor::Border, ThemeColor::Muted);
        } else {
            $availableWidth = $this->terminalWidth - 4; // Account for borders and padding

            foreach ($recentActivity as $entry) {
                $message = $entry->getMessage();
                $icon = $entry->getIcon();
                $color = $entry->getColor();

                // Split message by newlines first to preserve formatting
                $messageLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $message));
                $wrappedLines = [];

                // Calculate effective width accounting for icon on first line
                $hasIcon = $entry->hasIcon() && ! empty($icon);
                $iconWidth = $hasIcon ? 4 : 0; // icon + 2 spaces

                // Wrap each line individually
                foreach ($messageLines as $index => $line) {
                    if (empty($line)) {
                        $wrappedLines[] = '';  // Preserve empty lines
                    } else {
                        // First line needs to account for icon width
                        $lineWidth = ($index === 0) ? $availableWidth - $iconWidth : $availableWidth - 4; // Subsequent lines have indent
                        $wrapped = $this->wrapText($line, $lineWidth);
                        $wrappedLines = array_merge($wrappedLines, $wrapped);
                    }
                }

                // Display first line with icon
                if (! empty($wrappedLines)) {
                    // Check if entry has icon (notifications have icons in their message)
                    if ($entry->hasIcon() && ! empty($icon)) {
                        $firstLine = "{$icon}  " . $wrappedLines[0];
                    } else {
                        $firstLine = $wrappedLines[0];
                    }
                    echo $this->drawBoxLine($firstLine, ThemeColor::Border, $color);

                    // Display subsequent lines with indentation
                    for ($i = 1; $i < count($wrappedLines); $i++) {
                        $indentSpaces = $entry->hasIcon() ? '    ' : '  '; // Less indent for no icon
                        $continuationLine = $indentSpaces . $wrappedLines[$i];
                        echo $this->drawBoxLine($continuationLine, ThemeColor::Border, $color);
                    }
                }
            }
        }
    }

    protected function drawFooter(): void
    {
        echo $this->drawBoxBottom(ThemeColor::Border);
        echo $this->colorize('💡  Commands: help | exit | clear', ThemeColor::Muted) . "\n";
    }

    protected function restoreNormalMode(): void
    {
        // Restore normal terminal mode with echo and canonical mode
        if (function_exists('system')) {
            system('stty echo icanon 2>/dev/null');
        }
    }

    // Helper methods for drawing UI elements

    protected function drawBoxTop(string $title = '', string|AnsiColor|ThemeColor $borderColor = 'gray'): string
    {
        $leftCorner = $this->colorize(BoxCharacter::TopLeft->getChar(), $borderColor);
        $rightCorner = $this->colorize(BoxCharacter::TopRight->getChar(), $borderColor);
        $horizontal = BoxCharacter::Horizontal->getChar();

        if ($title) {
            $titleFormatted = $this->colorize(" {$title} ", ThemeColor::Accent);
            $titleLength = mb_strlen($title) + 2;
            $remainingWidth = $this->terminalWidth - $titleLength - 4;
            $line = $leftCorner . $this->colorize($horizontal, $borderColor) . $titleFormatted .
                $this->colorize(str_repeat($horizontal, $remainingWidth), $borderColor) . $rightCorner;
        } else {
            $line = $leftCorner . $this->colorize(str_repeat($horizontal, $this->terminalWidth - 2), $borderColor) . $rightCorner;
        }

        return $line . "\n";
    }

    protected function drawBoxBottom(string|AnsiColor|ThemeColor $borderColor = 'gray'): string
    {
        $leftCorner = $this->colorize(BoxCharacter::BottomLeft->getChar(), $borderColor);
        $rightCorner = $this->colorize(BoxCharacter::BottomRight->getChar(), $borderColor);
        $horizontal = $this->colorize(str_repeat(BoxCharacter::Horizontal->getChar(), $this->terminalWidth - 2), $borderColor);

        return $leftCorner . $horizontal . $rightCorner . "\n";
    }

    protected function drawBoxLine(string $content, string|AnsiColor|ThemeColor $borderColor = 'gray', string|AnsiColor|ThemeColor $contentColor = 'white'): string
    {
        $leftBorder = $this->colorize(BoxCharacter::Vertical->getChar() . ' ', $borderColor);
        $rightBorder = $this->colorize(' ' . BoxCharacter::Vertical->getChar(), $borderColor);

        // Calculate available width for content (accounting for borders and padding)
        $availableWidth = $this->terminalWidth - 4;

        // Truncate content if it's too long to prevent breaking the border
        $contentLength = mb_strlen($content);
        if ($contentLength > $availableWidth) {
            $content = mb_substr($content, 0, $availableWidth);
            $contentLength = $availableWidth;
        }

        // Pad with spaces to fill the line
        $padding = str_repeat(' ', max(0, $availableWidth - $contentLength));

        return $leftBorder . $this->colorize($content, $contentColor) . $padding . $rightBorder . "\n";
    }

    protected function drawBoxSeparator(string $title = '', string|AnsiColor|ThemeColor $borderColor = 'gray'): string
    {
        $leftTee = $this->colorize(BoxCharacter::TeeRight->getChar(), $borderColor);
        $rightTee = $this->colorize(BoxCharacter::TeeLeft->getChar(), $borderColor);
        $horizontal = BoxCharacter::Horizontal->getChar();

        if ($title) {
            $titleFormatted = $this->colorize(" {$title} ", ThemeColor::Accent);
            $titleLength = mb_strlen($title) + 2;
            $remainingWidth = $this->terminalWidth - $titleLength - 4;
            $line = $leftTee . $this->colorize($horizontal, $borderColor) . $titleFormatted .
                $this->colorize(str_repeat($horizontal, $remainingWidth), $borderColor) . $rightTee;
        } else {
            $line = $leftTee . $this->colorize(str_repeat($horizontal, $this->terminalWidth - 2), $borderColor) . $rightTee;
        }

        return $line . "\n";
    }

    protected function padLine(string $content, int $width): string
    {
        $contentLength = mb_strlen($content);
        if ($contentLength >= $width) {
            return mb_substr($content, 0, $width);
        }

        return $content . str_repeat(' ', $width - $contentLength);
    }

    protected function getCurrentLine(): int
    {
        // Count lines used by current UI elements
        $lines = 1; // Header
        $lines += 5; // Task status (minimum)
        $lines += 1; // Separator

        // Count actual lines used by recent activity (including wrapped lines)
        $recentHistory = array_slice($this->history, -8);
        $availableWidth = $this->terminalWidth - 4;
        $activityLines = 0;

        foreach ($recentHistory as $entry) {
            $message = $entry->getMessage();
            // Split by newlines first to preserve formatting
            $messageLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $message));
            foreach ($messageLines as $line) {
                if (empty($line)) {
                    $activityLines++; // Count empty lines
                } else {
                    $wrappedLines = $this->wrapText($line, $availableWidth);
                    $activityLines += count($wrappedLines);
                }
            }
        }

        $lines += max(1, $activityLines); // At least 1 line for "No recent activity"
        $lines += 2; // Footer

        return $lines;
    }

    protected function wrapText(string $text, int $maxWidth): array
    {
        // Use full available width for wrapping
        $effectiveWidth = $maxWidth;

        if (mb_strlen($text) <= $effectiveWidth) {
            return [$text];
        }

        $lines = [];
        $words = explode(' ', $text);
        $currentLine = '';

        foreach ($words as $word) {
            // If adding this word would exceed the width
            if (mb_strlen($currentLine . ' ' . $word) > $effectiveWidth) {
                if ($currentLine !== '') {
                    $lines[] = trim($currentLine);
                    $currentLine = $word;
                } else {
                    // Single word is too long, need to break it
                    while (mb_strlen($word) > $effectiveWidth) {
                        $lines[] = mb_substr($word, 0, $effectiveWidth);
                        $word = mb_substr($word, $effectiveWidth);
                    }
                    $currentLine = $word;
                }
            } else {
                $currentLine .= ($currentLine === '' ? '' : ' ') . $word;
            }
        }

        if ($currentLine !== '') {
            $lines[] = trim($currentLine);
        }

        return $lines;
    }
}
