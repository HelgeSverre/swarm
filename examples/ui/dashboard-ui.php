#!/usr/bin/env php
<?php

/**
 * Professional Dashboard UI - Metrics-focused Terminal Interface
 *
 * Features:
 * - Split dashboard layout with metrics
 * - Tool execution statistics
 * - Performance monitoring
 * - Static progress bars
 * - Monochrome theme with accent colors
 */

// ANSI constants
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";
const ITALIC = "\033[3m";
const UNDERLINE = "\033[4m";
const BLINK = "\033[5m";
const REVERSE = "\033[7m";

// Cursor & screen
const HIDE_CURSOR = "\033[?25l";
const SHOW_CURSOR = "\033[?25h";
const CLEAR = "\033[2J";
const HOME = "\033[H";
const SAVE_CURSOR = "\033[s";
const RESTORE_CURSOR = "\033[u";
const ALT_SCREEN_ON = "\033[?1049h";
const ALT_SCREEN_OFF = "\033[?1049l";

// Monochrome with accents
const BLACK = "\033[30m";
const WHITE = "\033[97m";
const GRAY = "\033[37m";
const DARK_GRAY = "\033[90m";
const BRIGHT_WHITE = "\033[97m";

// Accent colors (used sparingly)
const RED = "\033[91m";     // Errors only
const GREEN = "\033[92m";   // Success only
const YELLOW = "\033[93m";  // Warnings only
const BLUE = "\033[94m";    // Primary accent
const CYAN = "\033[96m";    // Secondary accent

// Backgrounds
const BG_BLACK = "\033[40m";
const BG_GRAY = "\033[47m";
const BG_DARK = "\033[100m";
const BG_WHITE = "\033[107m";

// Unicode blocks for progress bars
const BLOCK_FULL = '█';
const BLOCK_SEVEN_EIGHTHS = '▉';
const BLOCK_THREE_QUARTERS = '▊';
const BLOCK_FIVE_EIGHTHS = '▋';
const BLOCK_HALF = '▌';
const BLOCK_THREE_EIGHTHS = '▍';
const BLOCK_QUARTER = '▎';
const BLOCK_EIGHTH = '▏';
const BLOCK_EMPTY = '░';

// Box drawing
const LINE_H = '━';
const LINE_V = '┃';
const CORNER_TL = '┏';
const CORNER_TR = '┓';
const CORNER_BL = '┗';
const CORNER_BR = '┛';
const T_DOWN = '┳';
const T_UP = '┻';
const T_RIGHT = '┣';
const T_LEFT = '┫';
const CROSS = '╋';
const LINE_THIN_H = '─';
const LINE_THIN_V = '│';

class DashboardUI
{
    private int $width;

    private int $height;

    private int $sidebarWidth = 40;

    private int $headerHeight = 3;

    private int $footerHeight = 2;

    // Dashboard data
    private array $metrics = [
        'operations_per_minute' => 0,
        'success_rate' => 0.0,
        'avg_response_ms' => 0,
        'active_agents' => 0,
        'queued_tasks' => 0,
        'completed_today' => 0,
    ];

    private array $toolStats = [];

    private array $recentOperations = [];

    private array $taskQueue = [];

    private array $agentStatus = [];

    // Performance history (for mini charts)
    private array $performanceHistory = [];

    private int $maxHistoryPoints = 20;

    // UI state
    private bool $running = false;

    private int $frame = 0;

    private int $selectedPanel = 0; // 0=main, 1=tasks, 2=agents, 3=tools

    private int $scrollOffset = 0;

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->initializeData();
        $this->setupSignalHandlers();
    }

    public function run(): void
    {
        $this->enterDashboardMode();
        $this->running = true;

        $lastUpdate = microtime(true);
        $updateInterval = 0.1; // 100ms updates

        while ($this->running) {
            $now = microtime(true);

            // Handle input
            $this->processInput();

            // Update data periodically
            if ($now - $lastUpdate >= $updateInterval) {
                $this->updateMetrics();
                $this->render();
                $lastUpdate = $now;
                $this->frame++;
            }

            pcntl_signal_dispatch();
            usleep(10000); // 10ms sleep
        }

        $this->exitDashboardMode();
    }

    private function enterDashboardMode(): void
    {
        echo ALT_SCREEN_ON;
        echo HIDE_CURSOR;
        echo CLEAR . HOME;
        system('stty -echo -icanon min 1 time 0 2>/dev/null');
        stream_set_blocking(STDIN, false);
    }

    private function exitDashboardMode(): void
    {
        echo SHOW_CURSOR;
        echo ALT_SCREEN_OFF;
        echo RESET;
        system('stty sane');
    }

    private function updateTerminalSize(): void
    {
        $this->width = (int) exec('tput cols') ?: 120;
        $this->height = (int) exec('tput lines') ?: 40;
    }

    private function setupSignalHandlers(): void
    {
        pcntl_signal(SIGINT, function () {
            $this->running = false;
        });

        pcntl_signal(SIGWINCH, function () {
            $this->updateTerminalSize();
            $this->render();
        });
    }

    private function processInput(): void
    {
        $input = fread(STDIN, 1024);
        if (! $input) {
            return;
        }

        // Simple input handling
        foreach (mb_str_split($input) as $char) {
            switch ($char) {
                case 'q':
                case 'Q':
                case "\x03": // Ctrl+C
                    $this->running = false;
                    break;
                case '1':
                    $this->selectedPanel = 0;
                    break;
                case '2':
                    $this->selectedPanel = 1;
                    break;
                case '3':
                    $this->selectedPanel = 2;
                    break;
                case '4':
                    $this->selectedPanel = 3;
                    break;
                case 'r':
                case 'R':
                    $this->addRandomOperation();
                    break;
            }
        }
    }

    private function render(): void
    {
        echo CLEAR . HOME;

        $this->renderHeader();
        $this->renderMainPanel();
        $this->renderSidebar();
        $this->renderFooter();
    }

    private function renderHeader(): void
    {
        // Title bar with system status
        echo $this->moveTo(1, 1);
        echo BG_DARK . BRIGHT_WHITE;

        $title = ' ⚡ AGENT DASHBOARD ';
        $timestamp = date('Y-m-d H:i:s');
        $systemStatus = $this->getSystemStatus();

        echo $title;

        // Center system status
        $centerPos = ($this->width - mb_strlen($systemStatus)) / 2;
        echo $this->moveTo(1, (int) $centerPos);
        echo $systemStatus;

        // Right-align timestamp
        echo $this->moveTo(1, $this->width - mb_strlen($timestamp) - 1);
        echo $timestamp . ' ';

        echo RESET;

        // Separator
        echo $this->moveTo(2, 1);
        echo DARK_GRAY . str_repeat(LINE_THIN_H, $this->width) . RESET;
    }

    private function renderMainPanel(): void
    {
        $mainWidth = $this->width - $this->sidebarWidth - 1;
        $mainHeight = $this->height - $this->headerHeight - $this->footerHeight;

        // Metrics cards at top
        $this->renderMetricsCards(3, 1, $mainWidth);

        // Performance chart
        $this->renderPerformanceChart(8, 1, $mainWidth, 8);

        // Recent operations list
        $this->renderRecentOperations(17, 1, $mainWidth, $mainHeight - 14);
    }

    private function renderMetricsCards(int $row, int $col, int $width): void
    {
        $cardWidth = (int) (($width - 2) / 3);

        // Card 1: Operations/min
        $this->renderMetricCard(
            $row, $col,
            'OPS/MIN',
            (string) $this->metrics['operations_per_minute'],
            $this->getMetricTrend('operations_per_minute'),
            $cardWidth
        );

        // Card 2: Success Rate
        $this->renderMetricCard(
            $row, $col + $cardWidth + 1,
            'SUCCESS',
            sprintf('%.1f%%', $this->metrics['success_rate']),
            $this->getMetricTrend('success_rate'),
            $cardWidth
        );

        // Card 3: Response Time
        $this->renderMetricCard(
            $row, $col + ($cardWidth + 1) * 2,
            'AVG MS',
            (string) $this->metrics['avg_response_ms'],
            $this->getMetricTrend('avg_response_ms', true), // Lower is better
            $cardWidth
        );
    }

    private function renderMetricCard(int $row, int $col, string $label, string $value, string $trend, int $width): void
    {
        // Card border
        echo $this->moveTo($row, $col);
        echo GRAY . CORNER_TL . str_repeat(LINE_H, $width - 2) . CORNER_TR . RESET;

        echo $this->moveTo($row + 1, $col);
        echo GRAY . LINE_V . RESET;
        echo $this->moveTo($row + 1, $col + 1);
        echo DARK_GRAY . $label . RESET;
        echo $this->moveTo($row + 1, $col + $width - 1);
        echo GRAY . LINE_V . RESET;

        echo $this->moveTo($row + 2, $col);
        echo GRAY . LINE_V . RESET;
        echo $this->moveTo($row + 2, $col + 2);
        echo BOLD . WHITE . $value . RESET;
        echo ' ' . $trend;
        echo $this->moveTo($row + 2, $col + $width - 1);
        echo GRAY . LINE_V . RESET;

        echo $this->moveTo($row + 3, $col);
        echo GRAY . CORNER_BL . str_repeat(LINE_H, $width - 2) . CORNER_BR . RESET;
    }

    private function renderPerformanceChart(int $row, int $col, int $width, int $height): void
    {
        echo $this->moveTo($row, $col);
        echo BOLD . 'Performance History' . RESET;
        echo DARK_GRAY . ' (last ' . count($this->performanceHistory) . ' samples)' . RESET;

        // Chart area
        $chartRow = $row + 1;
        $chartHeight = $height - 2;
        $chartWidth = $width - 4;

        // Y-axis labels
        for ($i = 0; $i <= $chartHeight; $i++) {
            echo $this->moveTo($chartRow + $i, $col);
            if ($i == 0) {
                echo DARK_GRAY . '100%' . RESET;
            } elseif ($i == $chartHeight) {
                echo DARK_GRAY . '  0%' . RESET;
            }
        }

        // Draw chart
        if (! empty($this->performanceHistory)) {
            $barWidth = max(1, (int) ($chartWidth / count($this->performanceHistory)));

            foreach ($this->performanceHistory as $index => $value) {
                $barHeight = (int) ($value / 100 * $chartHeight);
                $barCol = $col + 5 + ($index * $barWidth);

                // Draw bar from bottom up
                for ($h = 0; $h < $barHeight; $h++) {
                    echo $this->moveTo($chartRow + $chartHeight - $h, $barCol);

                    // Color based on value
                    if ($value >= 90) {
                        echo GREEN;
                    } elseif ($value >= 70) {
                        echo YELLOW;
                    } else {
                        echo RED;
                    }

                    echo BLOCK_FULL . RESET;
                }
            }
        }

        // X-axis line
        echo $this->moveTo($chartRow + $chartHeight + 1, $col + 4);
        echo DARK_GRAY . str_repeat(LINE_THIN_H, $chartWidth + 1) . RESET;
    }

    private function renderRecentOperations(int $row, int $col, int $width, int $height): void
    {
        echo $this->moveTo($row, $col);
        echo BOLD . 'Recent Operations' . RESET;

        $listRow = $row + 1;
        $visibleOps = array_slice($this->recentOperations, $this->scrollOffset, $height - 2);

        foreach ($visibleOps as $i => $op) {
            echo $this->moveTo($listRow + $i, $col);

            // Time
            $time = date('H:i:s', $op['timestamp']);
            echo DARK_GRAY . "[{$time}]" . RESET . ' ';

            // Status icon
            $statusIcon = match ($op['status']) {
                'success' => GREEN . '●',
                'warning' => YELLOW . '▲',
                'error' => RED . '✗',
                default => GRAY . '○'
            };
            echo $statusIcon . RESET . ' ';

            // Agent
            echo CYAN . mb_str_pad($op['agent'], 10) . RESET . ' ';

            // Operation
            $opText = $op['operation'];
            $maxOpLen = $width - 30;
            if (mb_strlen($opText) > $maxOpLen) {
                $opText = mb_substr($opText, 0, $maxOpLen - 3) . '...';
            }
            echo WHITE . $opText . RESET;

            // Duration
            if (isset($op['duration'])) {
                $durStr = sprintf('%dms', $op['duration']);
                echo $this->moveTo($listRow + $i, $col + $width - mb_strlen($durStr) - 1);
                echo DARK_GRAY . $durStr . RESET;
            }
        }
    }

    private function renderSidebar(): void
    {
        $sidebarCol = $this->width - $this->sidebarWidth + 1;

        // Vertical separator
        for ($row = 3; $row <= $this->height - $this->footerHeight; $row++) {
            echo $this->moveTo($row, $sidebarCol - 1);
            echo DARK_GRAY . LINE_THIN_V . RESET;
        }

        $currentRow = 3;

        // Agent Status Section
        $this->renderAgentStatus($currentRow, $sidebarCol);
        $currentRow += count($this->agentStatus) + 3;

        // Task Queue Section
        $this->renderTaskQueue($currentRow, $sidebarCol);
        $currentRow += min(8, count($this->taskQueue)) + 3;

        // Tool Statistics Section
        $this->renderToolStats($currentRow, $sidebarCol);
    }

    private function renderAgentStatus(int $row, int $col): void
    {
        echo $this->moveTo($row, $col);
        echo BOLD . 'Agent Status' . RESET;

        $statusRow = $row + 1;
        foreach ($this->agentStatus as $agent) {
            echo $this->moveTo($statusRow++, $col);

            // Status indicator
            $indicator = match ($agent['status']) {
                'active' => GREEN . '●',
                'idle' => YELLOW . '○',
                'error' => RED . '✗',
                default => GRAY . '·'
            };

            echo $indicator . RESET . ' ';
            echo mb_str_pad($agent['name'], 12);

            // CPU usage bar
            $this->renderMiniBar($agent['cpu'], 10);

            echo DARK_GRAY . sprintf(' %3d%%', $agent['cpu']) . RESET;
        }
    }

    private function renderTaskQueue(int $row, int $col): void
    {
        echo $this->moveTo($row, $col);
        echo BOLD . 'Task Queue' . RESET;
        echo DARK_GRAY . sprintf(' (%d)', count($this->taskQueue)) . RESET;

        $taskRow = $row + 1;
        $maxTasks = 6;

        foreach (array_slice($this->taskQueue, 0, $maxTasks) as $i => $task) {
            echo $this->moveTo($taskRow++, $col);

            $priority = match ($task['priority']) {
                'high' => RED . '!',
                'normal' => YELLOW . '·',
                'low' => GRAY . '·',
                default => ' '
            };

            echo $priority . RESET . ' ';

            $title = $task['title'];
            if (mb_strlen($title) > $this->sidebarWidth - 8) {
                $title = mb_substr($title, 0, $this->sidebarWidth - 11) . '...';
            }

            echo $title;

            // Progress for running tasks
            if ($task['status'] === 'running') {
                echo $this->moveTo($taskRow - 1, $col + $this->sidebarWidth - 8);
                echo CYAN . sprintf('%3d%%', $task['progress'] ?? 0) . RESET;
            }
        }

        if (count($this->taskQueue) > $maxTasks) {
            echo $this->moveTo($taskRow, $col);
            echo DARK_GRAY . '  ... +' . (count($this->taskQueue) - $maxTasks) . ' more' . RESET;
        }
    }

    private function renderToolStats(int $row, int $col): void
    {
        echo $this->moveTo($row, $col);
        echo BOLD . 'Tool Usage' . RESET;

        $toolRow = $row + 1;

        // Sort tools by usage
        arsort($this->toolStats);
        $topTools = array_slice($this->toolStats, 0, 5, true);

        foreach ($topTools as $tool => $stats) {
            echo $this->moveTo($toolRow++, $col);

            echo DARK_GRAY . mb_str_pad($tool, 15) . RESET;

            // Usage bar
            $percentage = ($stats['count'] / max(1, $this->metrics['completed_today'])) * 100;
            $this->renderMiniBar((int) $percentage, 8);

            echo DARK_GRAY . sprintf(' %3d', $stats['count']) . RESET;
        }
    }

    private function renderFooter(): void
    {
        echo $this->moveTo($this->height - 1, 1);
        echo BG_DARK . BRIGHT_WHITE;

        // Panel indicators
        $panels = [
            '1' => 'Main',
            '2' => 'Tasks',
            '3' => 'Agents',
            '4' => 'Tools',
        ];

        foreach ($panels as $key => $label) {
            $isSelected = ($key - 1) == $this->selectedPanel;

            if ($isSelected) {
                echo REVERSE;
            }

            echo " [{$key}] {$label} ";

            if ($isSelected) {
                echo RESET . BG_DARK . BRIGHT_WHITE;
            }
        }

        // Right side controls
        $controls = '[R]efresh  [Q]uit';
        echo $this->moveTo($this->height - 1, $this->width - mb_strlen($controls) - 1);
        echo $controls . ' ';

        echo RESET;
    }

    private function renderMiniBar(int $percentage, int $width): void
    {
        $filled = (int) (($percentage / 100) * $width);

        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                echo CYAN . BLOCK_FULL;
            } else {
                echo DARK_GRAY . BLOCK_EMPTY;
            }
        }
        echo RESET;
    }

    private function moveTo(int $row, int $col): string
    {
        return "\033[{$row};{$col}H";
    }

    private function getSystemStatus(): string
    {
        $status = 'OPERATIONAL';
        $color = GREEN;

        if ($this->metrics['success_rate'] < 50) {
            $status = 'DEGRADED';
            $color = YELLOW;
        }

        if ($this->metrics['success_rate'] < 25) {
            $status = 'CRITICAL';
            $color = RED;
        }

        return $color . "● {$status}" . RESET;
    }

    private function getMetricTrend(string $metric, bool $inverse = false): string
    {
        // Simulate trend with random for demo
        $trend = rand(-1, 1);

        if ($inverse) {
            $trend = -$trend;
        }

        if ($trend > 0) {
            return GREEN . '↑';
        } elseif ($trend < 0) {
            return RED . '↓';
        }

        return YELLOW . '→';
    }

    private function initializeData(): void
    {
        // Initialize metrics
        $this->metrics = [
            'operations_per_minute' => rand(20, 80),
            'success_rate' => rand(85, 99),
            'avg_response_ms' => rand(50, 500),
            'active_agents' => 3,
            'queued_tasks' => rand(5, 15),
            'completed_today' => rand(100, 500),
        ];

        // Initialize agent status
        $this->agentStatus = [
            ['name' => 'Analyzer', 'status' => 'active', 'cpu' => rand(20, 80)],
            ['name' => 'Coder', 'status' => 'active', 'cpu' => rand(30, 90)],
            ['name' => 'Tester', 'status' => 'idle', 'cpu' => rand(5, 20)],
            ['name' => 'Reviewer', 'status' => 'active', 'cpu' => rand(40, 70)],
        ];

        // Initialize task queue
        $taskTitles = [
            'Refactor authentication module',
            'Update dependencies',
            'Write unit tests',
            'Optimize database queries',
            'Generate documentation',
            'Security audit',
            'Performance profiling',
        ];

        foreach ($taskTitles as $i => $title) {
            $this->taskQueue[] = [
                'title' => $title,
                'priority' => ['high', 'normal', 'low'][rand(0, 2)],
                'status' => $i < 2 ? 'running' : 'pending',
                'progress' => $i < 2 ? rand(10, 90) : 0,
            ];
        }

        // Initialize tool stats
        $this->toolStats = [
            'ReadFile' => ['count' => rand(50, 200)],
            'WriteFile' => ['count' => rand(20, 100)],
            'Terminal' => ['count' => rand(30, 150)],
            'Search' => ['count' => rand(40, 180)],
            'Analyze' => ['count' => rand(10, 80)],
        ];

        // Initialize performance history
        for ($i = 0; $i < $this->maxHistoryPoints; $i++) {
            $this->performanceHistory[] = rand(60, 95);
        }

        // Initialize recent operations
        $this->generateRecentOperations();
    }

    private function generateRecentOperations(): void
    {
        $operations = [
            'Reading configuration file',
            'Analyzing code structure',
            'Running test suite',
            'Compiling assets',
            'Checking dependencies',
            'Validating syntax',
            'Generating report',
        ];

        $agents = ['Analyzer', 'Coder', 'Tester', 'Reviewer', 'Builder'];

        for ($i = 0; $i < 20; $i++) {
            $this->recentOperations[] = [
                'timestamp' => time() - ($i * 30),
                'agent' => $agents[array_rand($agents)],
                'operation' => $operations[array_rand($operations)],
                'status' => ['success', 'success', 'success', 'warning', 'error'][rand(0, 4)],
                'duration' => rand(50, 2000),
            ];
        }
    }

    private function updateMetrics(): void
    {
        // Simulate metric updates
        if ($this->frame % 10 === 0) {
            $this->metrics['operations_per_minute'] = max(0, $this->metrics['operations_per_minute'] + rand(-5, 10));
            $this->metrics['success_rate'] = max(0, min(100, $this->metrics['success_rate'] + rand(-3, 3)));
            $this->metrics['avg_response_ms'] = max(10, $this->metrics['avg_response_ms'] + rand(-50, 50));

            // Update performance history
            $this->performanceHistory[] = $this->metrics['success_rate'];
            if (count($this->performanceHistory) > $this->maxHistoryPoints) {
                array_shift($this->performanceHistory);
            }

            // Update agent CPU
            foreach ($this->agentStatus as &$agent) {
                $agent['cpu'] = max(0, min(100, $agent['cpu'] + rand(-10, 10)));
            }

            // Update task progress
            foreach ($this->taskQueue as &$task) {
                if ($task['status'] === 'running') {
                    $task['progress'] = min(100, ($task['progress'] ?? 0) + rand(1, 5));
                    if ($task['progress'] >= 100) {
                        $task['status'] = 'completed';
                    }
                }
            }
        }

        // Add random operation occasionally
        if ($this->frame % 20 === 0) {
            $this->addRandomOperation();
        }
    }

    private function addRandomOperation(): void
    {
        $operations = [
            'Processing request',
            'Executing command',
            'Analyzing data',
            'Generating output',
            'Validating input',
        ];

        $agents = ['Analyzer', 'Coder', 'Tester', 'Reviewer'];

        $newOp = [
            'timestamp' => time(),
            'agent' => $agents[array_rand($agents)],
            'operation' => $operations[array_rand($operations)],
            'status' => ['success', 'success', 'success', 'warning', 'error'][rand(0, 4)],
            'duration' => rand(50, 2000),
        ];

        array_unshift($this->recentOperations, $newOp);

        // Keep only last 50
        if (count($this->recentOperations) > 50) {
            array_pop($this->recentOperations);
        }

        $this->metrics['completed_today']++;
    }
}

// Run the dashboard
$dashboard = new DashboardUI;

try {
    $dashboard->run();
} catch (Exception $e) {
    echo SHOW_CURSOR . ALT_SCREEN_OFF . RESET;
    echo 'Dashboard Error: ' . $e->getMessage() . "\n";
    exit(1);
}
