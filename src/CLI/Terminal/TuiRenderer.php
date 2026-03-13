<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Terminal;

class TuiRenderer
{
    protected int $mainAreaWidth;

    protected int $sidebarWidth;

    public function __construct(
        protected TerminalDriver $driver,
        protected TuiViewModel $viewModel,
    ) {}

    public function render(): void
    {
        if (! $this->driver->isInitialized()) {
            return;
        }

        $this->doRender();
    }

    protected function doRender(): void
    {
        $this->driver->updateTerminalSize();
        $this->sidebarWidth = max(30, (int) ($this->driver->getWidth() * 0.25));
        $this->mainAreaWidth = $this->driver->getWidth() - $this->sidebarWidth - 1;

        $this->driver->clearScreen();

        if ($this->viewModel->isShowTaskOverlay()) {
            $this->renderMainView();
            $this->renderTaskOverlay();
        } elseif ($this->viewModel->isShowHelp()) {
            $this->renderMainView();
            $this->renderHelpOverlay();
        } else {
            $this->renderMainView();
        }
    }

    protected function renderMainView(): void
    {
        echo "\033[?25l";

        for ($row = 1; $row <= $this->driver->getHeight(); $row++) {
            $this->driver->moveCursor($row, $this->mainAreaWidth + 1);
            echo Ansi::DIM . Ansi::BOX_V_HEAVY . Ansi::RESET;
        }

        $this->renderSidebar();
        $this->renderMainArea();
    }

    protected function renderMainArea(): void
    {
        $row = 1;
        $isActive = $this->viewModel->getCurrentFocus() === TuiViewModel::FOCUS_MAIN;

        $this->driver->moveCursor($row++, 1);
        $this->renderStatusBar();

        $history = $this->viewModel->getHistory();
        $activityFeed = $this->viewModel->getActivityFeed();

        if (! empty($history) || ! empty($activityFeed)) {
            $row++;

            $availableLines = $this->driver->getHeight() - $row - 6;

            $allEntries = [];

            foreach ($activityFeed as $activity) {
                if (method_exists($activity, 'getMessage')) {
                    $allEntries[] = [
                        'time' => $activity->timestamp ?? time(),
                        'type' => 'tool_activity',
                        'content' => $activity->getMessage(),
                        'activity_object' => $activity,
                    ];
                }
            }

            foreach ($history as $entry) {
                if ($entry['type'] !== 'tool') {
                    $allEntries[] = $entry;
                }
            }

            usort($allEntries, fn ($a, $b) => ($a['time'] ?? 0) - ($b['time'] ?? 0));
            $totalEntries = count($allEntries);
            $startIndex = max(0, $totalEntries - $availableLines);
            $recentEntries = array_slice($allEntries, $startIndex, $availableLines);

            foreach ($recentEntries as $entry) {
                if ($row >= $this->driver->getHeight() - 5) {
                    break;
                }
                $rowsUsed = $this->renderHistoryEntry($entry, $this->mainAreaWidth - 2, $row);
                $row += $rowsUsed;
            }
        }

        $this->driver->moveCursor($this->driver->getHeight() - 2, 1);
        echo Ansi::DIM . str_repeat(Ansi::BOX_H, $this->mainAreaWidth) . Ansi::BOX_R . Ansi::RESET;

        if ($this->viewModel->isShowReasoning() && $this->viewModel->getCurrentReasoning()) {
            $this->renderReasoningDisplay($row);
        }

        $modSymbol = $this->driver->getModSymbol();
        $this->driver->moveCursor($this->driver->getHeight() - 1, 2);
        $footerText = "{$modSymbol}T: tasks  {$modSymbol}H: help  Tab: switch pane  {$modSymbol}Q: quit";
        if ($this->viewModel->getCurrentReasoning()) {
            $footerText .= '  R: reasoning';
        }
        echo Ansi::DIM . $footerText . Ansi::RESET;

        $input = $this->viewModel->getInput();
        $this->driver->moveCursor($this->driver->getHeight(), 2);
        if ($isActive) {
            echo Ansi::BLUE . 'swarm >' . Ansi::RESET . ' ' . $input;
            $this->driver->moveCursor($this->driver->getHeight(), 10 + mb_strlen($input));
            echo "\033[?25h";
        } else {
            echo Ansi::DIM . 'swarm >' . Ansi::RESET . ' ' . Ansi::DIM . $input . Ansi::RESET;
        }
    }

    protected function renderReasoningDisplay(int &$row): void
    {
        $reasoning = $this->viewModel->getCurrentReasoning();
        if (! $reasoning) {
            return;
        }

        $maxLines = 5;
        $availableWidth = $this->mainAreaWidth - 4;

        $reasoningLines = $this->wrapText($reasoning, $availableWidth);
        $displayLines = array_slice($reasoningLines, 0, $maxLines);

        $startRow = max(3, min($row, $this->driver->getHeight() - 8));

        $this->driver->moveCursor($startRow, 2);
        echo Ansi::CYAN . '╭─ Thinking ';
        echo str_repeat('─', max(0, $availableWidth - 11));
        echo '╮' . Ansi::RESET;

        $currentRow = $startRow + 1;
        foreach ($displayLines as $line) {
            if ($currentRow >= $this->driver->getHeight() - 3) {
                break;
            }

            $this->driver->moveCursor($currentRow, 2);
            echo Ansi::CYAN . '│' . Ansi::RESET;
            echo ' ' . Ansi::DIM . $line . Ansi::RESET;

            $lineLength = mb_strlen($line);
            if ($lineLength < $availableWidth) {
                echo str_repeat(' ', $availableWidth - $lineLength);
            }
            echo Ansi::CYAN . '│' . Ansi::RESET;

            $currentRow++;
        }

        if (count($reasoningLines) > $maxLines) {
            $this->driver->moveCursor($currentRow, 2);
            echo Ansi::CYAN . '│' . Ansi::RESET;
            echo ' ' . Ansi::DIM . '... (truncated)' . Ansi::RESET;

            $truncText = ' ... (truncated)';
            $padding = max(0, $availableWidth - mb_strlen($truncText));
            echo str_repeat(' ', $padding);
            echo Ansi::CYAN . '│' . Ansi::RESET;
            $currentRow++;
        }

        $this->driver->moveCursor($currentRow, 2);
        echo Ansi::CYAN . '╰';
        echo str_repeat('─', $availableWidth);
        echo '╯' . Ansi::RESET;

        $row = $currentRow + 2;
    }

    protected function renderStatusBar(): void
    {
        echo Ansi::BG_DARK;

        $status = $this->viewModel->getStatus();
        $totalSteps = $this->viewModel->getTotalSteps();
        $currentStep = $this->viewModel->getCurrentStep();

        $statusContent = ' 💮 swarm ';
        $statusContent .= Ansi::DIM . Ansi::BOX_V . ' ' . Ansi::RESET . Ansi::BG_DARK;
        $statusContent .= Ansi::YELLOW . $status . Ansi::RESET . Ansi::BG_DARK;

        if ($totalSteps > 0) {
            $statusContent .= " ({$currentStep}/{$totalSteps})";
        }

        echo $statusContent;

        $contentLength = mb_strlen(Ansi::stripAnsi($statusContent));
        $remainingWidth = max(0, $this->driver->getWidth() - $contentLength);
        echo str_repeat(' ', $remainingWidth);

        echo Ansi::RESET;
    }

    protected function renderSidebar(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 4;

        $tasks = $this->viewModel->getTasks();
        $tasksActive = $this->viewModel->getCurrentFocus() === TuiViewModel::FOCUS_TASKS;
        $this->driver->moveCursor($row++, $col);
        echo Ansi::BOLD . Ansi::UNDERLINE . 'Task Queue' . Ansi::RESET;
        if ($tasksActive) {
            echo Ansi::BRIGHT_CYAN . ' [ACTIVE]' . Ansi::RESET;
        }

        $running = count(array_filter($tasks, fn ($t) => ($t['status'] ?? '') === 'running'));
        $pending = count(array_filter($tasks, fn ($t) => ($t['status'] ?? '') === 'pending'));

        $this->driver->moveCursor($row++, $col);
        echo Ansi::GREEN . $running . ' running' . Ansi::RESET . ', ' . Ansi::DIM . $pending . ' pending' . Ansi::RESET;

        $row++;

        $maxTasks = min(5, (int) (($this->driver->getHeight() / 2) - 4));
        $taskDisplay = array_slice($tasks, 0, $maxTasks);

        foreach ($taskDisplay as $i => $task) {
            $this->driver->moveCursor($row++, $col);
            $isSelected = $tasksActive && $i === $this->viewModel->getSelectedTaskIndex();
            if ($isSelected) {
                echo Ansi::REVERSE;
            }
            $this->renderCompactTaskLine($task, $i + 1, $this->sidebarWidth - 4);
            if ($isSelected) {
                echo Ansi::RESET;
            }
        }

        if (count($tasks) > $maxTasks) {
            $this->driver->moveCursor($row++, $col);
            echo Ansi::DIM . '... +' . (count($tasks) - $maxTasks) . ' more' . Ansi::RESET;
        }

        $row += 1;
        $this->driver->moveCursor($row++, $this->mainAreaWidth + 2);
        echo Ansi::DIM . str_repeat(Ansi::BOX_H, $this->sidebarWidth - 1) . Ansi::RESET;

        $context = $this->viewModel->getContext();
        $contextActive = $this->viewModel->getCurrentFocus() === TuiViewModel::FOCUS_CONTEXT;
        $this->driver->moveCursor($row++, $col);
        echo Ansi::BOLD . Ansi::UNDERLINE . 'Context' . Ansi::RESET;
        if ($contextActive) {
            echo Ansi::BRIGHT_CYAN . ' [ACTIVE]' . Ansi::RESET;
        }
        $row++;

        $contextLine = 0;

        if (! empty($context['directory'])) {
            $this->driver->moveCursor($row++, $col);
            $isSelected = $contextActive && $this->viewModel->getSelectedContextLine() === $contextLine++;
            echo ($isSelected ? Ansi::REVERSE : '') . Ansi::CYAN . 'Dir:' . Ansi::RESET;
            $this->driver->moveCursor($row++, $col);
            echo '  ' . $this->truncate($context['directory'], $this->sidebarWidth - 5);
            $row++;
        }

        if (! empty($context['files'])) {
            $this->driver->moveCursor($row++, $col);
            $isSelected = $contextActive && $this->viewModel->getSelectedContextLine() === $contextLine++;
            echo ($isSelected ? Ansi::REVERSE : '') . Ansi::YELLOW . 'Files:' . Ansi::RESET;
            foreach ($context['files'] as $file) {
                if ($row >= $this->driver->getHeight() - 8) {
                    break;
                }
                $this->driver->moveCursor($row++, $col);
                $isSelected = $contextActive && $this->viewModel->getSelectedContextLine() === $contextLine++;
                echo ($isSelected ? Ansi::REVERSE : '') . '  ' . $this->truncate($file, $this->sidebarWidth - 5) . Ansi::RESET;
            }
        }

        if ($row < $this->driver->getHeight() - 6) {
            $row++;
            $this->driver->moveCursor($row++, $col);
            $isSelected = $contextActive && $this->viewModel->getSelectedContextLine() === $contextLine++;
            echo ($isSelected ? Ansi::REVERSE : '') . Ansi::MAGENTA . 'Notes:' . Ansi::RESET;

            foreach ($context['notes'] as $i => $note) {
                if ($row >= $this->driver->getHeight() - 4) {
                    break;
                }
                $this->driver->moveCursor($row++, $col);
                $isSelected = $contextActive && $this->viewModel->getSelectedContextLine() === $contextLine++;
                echo ($isSelected ? Ansi::REVERSE : '') . '  • ' . $this->truncate($note, $this->sidebarWidth - 6) . Ansi::RESET;
            }

            if ($contextActive && $row < $this->driver->getHeight() - 2) {
                $contextInput = $this->viewModel->getContextInput();
                $this->driver->moveCursor($row++, $col);
                echo '  + ' . $contextInput;
                $this->driver->moveCursor($row - 1, $col + 4 + mb_strlen($contextInput));
                echo "\033[?25h";
            }
        }
    }

    protected function renderHistoryEntry(array $entry, int $maxWidth, int $currentRow): int
    {
        $time = date('H:i:s', $entry['time'] ?? time());
        $prefix = Ansi::DIM . "[{$time}]" . Ansi::RESET . ' ';
        $prefixLen = 11;

        $typeIndicatorLen = 2;
        $contentWidth = $maxWidth - $prefixLen - $typeIndicatorLen - 2;

        if ($contentWidth < 10) {
            $contentWidth = 10;
        }

        $rowsUsed = 0;

        $typeIndicator = '';
        $content = $entry['content'] ?? '';
        $formatting = '';
        $formattingEnd = '';

        switch ($entry['type']) {
            case 'command':
                $typeIndicator = Ansi::BLUE . '$' . Ansi::RESET . ' ';
                break;
            case 'status':
                $typeIndicator = Ansi::GREEN . '✓' . Ansi::RESET . ' ';
                break;
            case 'tool_activity':
                $typeIndicator = Ansi::CYAN . '🔧' . Ansi::RESET . ' ';
                break;
            case 'activity':
                $typeIndicator = Ansi::CYAN . '⚡' . Ansi::RESET . ' ';
                break;
            case 'tool':
                $typeIndicator = Ansi::CYAN . '>' . Ansi::RESET . ' ';
                $content = "{$entry['tool']} {$entry['params']}";
                break;
            case 'system':
                $typeIndicator = Ansi::YELLOW . '!' . Ansi::RESET . ' ';
                $formatting = Ansi::DIM;
                $formattingEnd = Ansi::RESET;
                break;
            case 'assistant':
                $typeIndicator = Ansi::GREEN . '●' . Ansi::RESET . ' ';
                break;
            case 'error':
                $typeIndicator = Ansi::RED . '✗' . Ansi::RESET . ' ';
                $formatting = Ansi::RED;
                $formattingEnd = Ansi::RESET;
                break;
            default:
                $typeIndicator = '• ';
        }

        $wrappedLines = $this->wrapText($content, $contentWidth);

        if (! empty($wrappedLines)) {
            $this->driver->moveCursor($currentRow, 2);

            $firstLine = $prefix . $typeIndicator . $formatting . $wrappedLines[0] . $formattingEnd;

            if (mb_strlen(Ansi::stripAnsi($firstLine)) > $maxWidth) {
                $firstLine = mb_substr($firstLine, 0, $maxWidth - 3) . '...';
            }

            echo $firstLine;
            $rowsUsed = 1;

            $indentSpace = str_repeat(' ', $prefixLen + $typeIndicatorLen);
            for ($i = 1; $i < count($wrappedLines); $i++) {
                $this->driver->moveCursor($currentRow + $rowsUsed, 2);

                $continuationLine = $indentSpace . $formatting . $wrappedLines[$i] . $formattingEnd;

                if (mb_strlen(Ansi::stripAnsi($continuationLine)) > $maxWidth) {
                    $continuationLine = mb_substr($continuationLine, 0, $maxWidth - 3) . '...';
                }

                echo $continuationLine;
                $rowsUsed++;
            }
        } else {
            $this->driver->moveCursor($currentRow, 2);
            echo $prefix . $typeIndicator;
            $rowsUsed = 1;
        }

        if ($entry['type'] === 'assistant' && isset($entry['thought']) && ! empty($entry['thought'])) {
            $expandedThoughts = $this->viewModel->getExpandedThoughts();
            $modSymbol = $this->driver->getModSymbol();
            $thoughtId = md5($entry['time'] . $entry['thought']);
            $isExpanded = in_array($thoughtId, $expandedThoughts);
            $thoughtLines = $this->wrapText($entry['thought'], $contentWidth - 2);

            $thoughtIndent = str_repeat(' ', $prefixLen + $typeIndicatorLen);

            if (count($thoughtLines) > 4 && ! $isExpanded) {
                for ($i = 0; $i < min(3, count($thoughtLines)); $i++) {
                    $this->driver->moveCursor($currentRow + $rowsUsed, 2);
                    $thoughtLine = $thoughtIndent . Ansi::DIM . Ansi::ITALIC . $thoughtLines[$i] . Ansi::RESET;

                    if (mb_strlen(Ansi::stripAnsi($thoughtLine)) > $maxWidth) {
                        $thoughtLine = mb_substr($thoughtLine, 0, $maxWidth - 3) . '...';
                    }

                    echo $thoughtLine;
                    $rowsUsed++;
                }

                $this->driver->moveCursor($currentRow + $rowsUsed, 2);
                $remainingLines = count($thoughtLines) - 3;
                $expandLine = $thoughtIndent . Ansi::DIM . "... +{$remainingLines} more lines ({$modSymbol}R to expand)" . Ansi::RESET;

                if (mb_strlen(Ansi::stripAnsi($expandLine)) > $maxWidth) {
                    $expandLine = mb_substr($expandLine, 0, $maxWidth - 3) . '...';
                }

                echo $expandLine;
                $rowsUsed++;
            } else {
                foreach ($thoughtLines as $line) {
                    $this->driver->moveCursor($currentRow + $rowsUsed, 2);
                    $thoughtLine = $thoughtIndent . Ansi::DIM . Ansi::ITALIC . $line . Ansi::RESET;

                    if (mb_strlen(Ansi::stripAnsi($thoughtLine)) > $maxWidth) {
                        $thoughtLine = mb_substr($thoughtLine, 0, $maxWidth - 3) . '...';
                    }

                    echo $thoughtLine;
                    $rowsUsed++;
                }

                if (count($thoughtLines) > 4) {
                    $this->driver->moveCursor($currentRow + $rowsUsed, 2);
                    $collapseLine = $thoughtIndent . Ansi::DIM . "({$modSymbol}R to collapse)" . Ansi::RESET;

                    if (mb_strlen(Ansi::stripAnsi($collapseLine)) > $maxWidth) {
                        $collapseLine = mb_substr($collapseLine, 0, $maxWidth - 3) . '...';
                    }

                    echo $collapseLine;
                    $rowsUsed++;
                }
            }
        }

        return $rowsUsed;
    }

    protected function renderCompactTaskLine(array $task, int $number, int $maxWidth): void
    {
        $status = $task['status'] ?? 'pending';
        $icon = match ($status) {
            'completed' => Ansi::GREEN . '✓',
            'running' => Ansi::YELLOW . '▶',
            'pending' => Ansi::DIM . '○',
            default => ' '
        };

        $num = mb_str_pad($number . '.', 3);
        $desc = $this->truncate($task['description'] ?? '', $maxWidth - 6);

        echo "{$num} {$icon} " . Ansi::RESET . $desc;

        if ($status === 'running' && ($task['steps'] ?? 0) > 0) {
            $percent = round((($task['completed_steps'] ?? 0) / $task['steps']) * 100);
            echo ' ' . Ansi::DIM . "{$percent}%" . Ansi::RESET;
        }
    }

    protected function renderTaskOverlay(): void
    {
        $maxWidth = $this->mainAreaWidth - 4;
        $width = min(70, $maxWidth);
        $height = min(20, $this->driver->getHeight() - 4);

        $startCol = (int) (($this->mainAreaWidth - $width) / 2) + 1;
        $startRow = (int) (($this->driver->getHeight() - $height) / 2);

        $this->drawBox($startRow, $startCol, $width, $height, 'Full Task List');

        $tasks = $this->viewModel->getTasks();
        $taskScrollOffset = $this->viewModel->getTaskScrollOffset();
        $selectedTaskIndex = $this->viewModel->getSelectedTaskIndex();
        $modSymbol = $this->driver->getModSymbol();

        $visibleHeight = $height - 4;
        $visibleTasks = array_slice($tasks, $taskScrollOffset, $visibleHeight);

        foreach ($visibleTasks as $i => $task) {
            $taskIndex = $i + $taskScrollOffset;
            $row = $startRow + 2 + $i;

            $this->driver->moveCursor($row, $startCol + 2);

            if ($taskIndex === $selectedTaskIndex) {
                echo Ansi::REVERSE;
            }

            $num = mb_str_pad((string) ($taskIndex + 1), 2);
            $status = $task['status'] ?? 'pending';
            $icon = match ($status) {
                'completed' => Ansi::GREEN . '✓',
                'running' => Ansi::YELLOW . '▶',
                'pending' => Ansi::DIM . '○',
                default => ' '
            };

            $desc = mb_substr($task['description'] ?? '', 0, $width - 20);
            $statusText = mb_str_pad("[{$status}]", 12);

            echo "{$num}. {$icon} " . mb_str_pad($desc, $width - 20) . " {$statusText}";

            if ($taskIndex === $selectedTaskIndex) {
                echo Ansi::RESET;
            }
        }

        if ($taskScrollOffset > 0) {
            $this->driver->moveCursor($startRow + 2, $startCol + $width - 3);
            echo Ansi::DIM . '▲' . Ansi::RESET;
        }

        if ($taskScrollOffset + $visibleHeight < count($tasks)) {
            $this->driver->moveCursor($startRow + $height - 2, $startCol + $width - 3);
            echo Ansi::DIM . '▼' . Ansi::RESET;
        }

        $this->driver->moveCursor($startRow + $height - 1, $startCol + 2);
        echo Ansi::DIM . "↑↓/jk: Navigate  Enter: Select  ESC/{$modSymbol}T: Close" . Ansi::RESET;
    }

    protected function renderHelpOverlay(): void
    {
        $maxWidth = $this->mainAreaWidth - 4;
        $width = min(60, $maxWidth);
        $height = 20;

        $startCol = (int) (($this->mainAreaWidth - $width) / 2) + 1;
        $startRow = (int) (($this->driver->getHeight() - $height) / 2);

        $this->drawBox($startRow, $startCol, $width, $height, 'Help');

        $modKey = $this->driver->getModKey();
        $modSymbol = $this->driver->getModSymbol();

        $help = [
            ['heading' => "Global Shortcuts ({$modKey} + key):"],
            ['key' => "{$modSymbol}T", 'desc' => 'Toggle full task list'],
            ['key' => "{$modSymbol}H", 'desc' => 'Show this help'],
            ['key' => "{$modSymbol}C", 'desc' => 'Clear history (main pane only)'],
            ['key' => "{$modSymbol}R", 'desc' => 'Refresh display/toggle thoughts'],
            ['key' => "{$modSymbol}Q", 'desc' => 'Quit application'],
            ['key' => "{$modSymbol}1/2/3", 'desc' => 'Jump to pane (main/tasks/context)'],
            ['', ''],
            ['heading' => 'Navigation:'],
            ['key' => 'Tab', 'desc' => 'Cycle through panes'],
            ['key' => '↑↓/jk', 'desc' => 'Navigate in lists'],
            ['key' => 'Enter', 'desc' => 'Select/confirm'],
            ['key' => 'ESC', 'desc' => 'Cancel/return to main'],
            ['', ''],
            ['heading' => 'Context Pane:'],
            ['key' => 'Type', 'desc' => 'Add new note'],
            ['key' => 'Backspace', 'desc' => 'Delete selected note'],
        ];

        $row = $startRow + 2;
        foreach ($help as $item) {
            if ($row >= $startRow + $height - 2) {
                break;
            }

            $this->driver->moveCursor($row++, $startCol + 2);
            if (isset($item['heading'])) {
                echo Ansi::BOLD . Ansi::UNDERLINE . $item['heading'] . Ansi::RESET;
            } elseif (! empty($item['key'])) {
                echo Ansi::CYAN . mb_str_pad($item['key'], 15) . Ansi::RESET . $item['desc'];
            }
        }

        $this->driver->moveCursor($startRow + $height - 1, $startCol + 2);
        echo Ansi::DIM . 'Press any key to close' . Ansi::RESET;
    }

    protected function drawBox(int $row, int $col, int $width, int $height, string $title = ''): void
    {
        $this->driver->moveCursor($row, $col);
        echo Ansi::GRAY . Ansi::BOX_TL;
        if ($title) {
            $titleLen = mb_strlen($title);
            $padding = (int) (($width - $titleLen - 4) / 2);
            echo str_repeat(Ansi::BOX_H, $padding) . ' ' . Ansi::WHITE . Ansi::BOLD . $title . Ansi::RESET . Ansi::GRAY . ' ';
            echo str_repeat(Ansi::BOX_H, $width - $padding - $titleLen - 4);
        } else {
            echo str_repeat(Ansi::BOX_H, $width - 2);
        }
        echo Ansi::BOX_TR . Ansi::RESET;

        for ($i = 1; $i < $height - 1; $i++) {
            $this->driver->moveCursor($row + $i, $col);
            echo Ansi::GRAY . Ansi::BOX_V . Ansi::RESET . str_repeat(' ', $width - 2) . Ansi::GRAY . Ansi::BOX_V . Ansi::RESET;
        }

        $this->driver->moveCursor($row + $height - 1, $col);
        echo Ansi::GRAY . Ansi::BOX_BL . str_repeat(Ansi::BOX_H, $width - 2) . Ansi::BOX_BR . Ansi::RESET;
    }

    protected function wrapText(string $text, int $maxWidth): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            if (mb_strlen($currentLine . ' ' . $word) <= $maxWidth) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    protected function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }
}
