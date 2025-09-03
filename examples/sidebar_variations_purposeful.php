#!/usr/bin/env php
<?php

/**
 * Purposeful Sidebar Variations for Swarm
 * 5 displays designed based on different user needs and workflows
 * Each optimized for specific use cases
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HelgeSverre\Swarm\CLI\Terminal\Ansi;

class PurposefulSidebarVariations
{
    private int $terminalHeight;

    private int $terminalWidth;

    private int $mainAreaWidth;

    private int $sidebarWidth;

    private int $currentStyle = 1;

    private bool $running = true;

    // Comprehensive Swarm data
    private array $tasks = [
        ['id' => '1', 'status' => 'completed', 'description' => 'Analyze codebase structure', 'duration' => 45],
        ['id' => '2', 'status' => 'completed', 'description' => 'Find authentication patterns', 'duration' => 12],
        ['id' => '3', 'status' => 'running', 'description' => 'Implement password hashing', 'duration' => null, 'progress' => 65],
        ['id' => '4', 'status' => 'pending', 'description' => 'Write unit tests', 'duration' => null],
        ['id' => '5', 'status' => 'pending', 'description' => 'Update documentation', 'duration' => null],
    ];

    private array $toolMetrics = [
        'Grep' => ['calls' => 23, 'success' => 21, 'avgTime' => 0.3],
        'ReadFile' => ['calls' => 45, 'success' => 45, 'avgTime' => 0.1],
        'WriteFile' => ['calls' => 8, 'success' => 7, 'avgTime' => 0.5],
        'FindFiles' => ['calls' => 15, 'success' => 15, 'avgTime' => 0.8],
        'Terminal' => ['calls' => 3, 'success' => 3, 'avgTime' => 2.1],
    ];

    private array $conversationHistory = [
        ['role' => 'user', 'summary' => 'Implement secure auth system'],
        ['role' => 'assistant', 'summary' => 'Analyzing existing auth...'],
        ['role' => 'user', 'summary' => 'Use bcrypt for passwords'],
        ['role' => 'assistant', 'summary' => 'Implementing bcrypt...'],
    ];

    private array $errorLog = [
        ['time' => '14:23:15', 'type' => 'warning', 'message' => 'Deprecated function found'],
        ['time' => '14:24:02', 'type' => 'info', 'message' => 'Creating backup before modification'],
        ['time' => '14:25:33', 'type' => 'error', 'message' => 'Test failed: AuthTest::testLogin'],
    ];

    private array $fileChanges = [
        'src/Auth/Hash.php' => ['added' => 45, 'removed' => 0, 'modified' => 0],
        'src/UserController.php' => ['added' => 12, 'removed' => 3, 'modified' => 8],
        'tests/AuthTest.php' => ['added' => 67, 'removed' => 0, 'modified' => 0],
    ];

    private float $cpuUsage = 23.5;

    private float $memoryUsage = 156.2; // MB

    private int $apiCalls = 12;

    private float $apiCost = 0.0234;

    private int $totalTokens = 4567;

    public function __construct()
    {
        $this->updateTerminalSize();
        $this->sidebarWidth = max(45, (int) ($this->terminalWidth * 0.35));
        $this->mainAreaWidth = $this->terminalWidth - $this->sidebarWidth - 1;
    }

    public function run(): void
    {
        system('stty -echo -icanon min 1 time 0');
        stream_set_blocking(STDIN, false);
        echo "\033[?1049h\033[?25l";

        while ($this->running) {
            $this->render();

            $key = $this->readKey();
            if ($key !== null) {
                $this->handleInput($key);
            }

            usleep(50000);
        }

        echo "\033[?25h\033[?1049l";
        system('stty sane');
    }

    private function render(): void
    {
        $this->clearScreen();
        $this->renderHeader();
        $this->renderMainArea();

        switch ($this->currentStyle) {
            case 1:
                $this->renderStyle1_DeveloperDashboard();
                break;
            case 2:
                $this->renderStyle2_DebuggingConsole();
                break;
            case 3:
                $this->renderStyle3_ManagerOverview();
                break;
            case 4:
                $this->renderStyle4_LearningMode();
                break;
            case 5:
                $this->renderStyle5_PerformanceMonitor();
                break;
        }

        $this->renderFooter();
    }

    /**
     * Style 1: Developer Dashboard
     * WHY: Developers need to see what code is being changed, tests status, and git-ready info
     * USEFUL FOR: Code reviews, understanding changes, preparing commits
     */
    private function renderStyle1_DeveloperDashboard(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Code Changes Summary
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '📝 Code Changes' . Ansi::RESET;

        $totalAdded = array_sum(array_column($this->fileChanges, 'added'));
        $totalRemoved = array_sum(array_column($this->fileChanges, 'removed'));
        $totalModified = array_sum(array_column($this->fileChanges, 'modified'));

        $this->moveCursor($row++, $col);
        echo Ansi::GREEN . "+{$totalAdded}" . Ansi::RESET . ' ';
        echo Ansi::RED . "-{$totalRemoved}" . Ansi::RESET . ' ';
        echo Ansi::YELLOW . "~{$totalModified}" . Ansi::RESET;

        $row++;

        // Files changed (git-style)
        foreach ($this->fileChanges as $file => $changes) {
            if ($row > 12) {
                break;
            }
            $this->moveCursor($row++, $col);

            $changeIndicator = '';
            if ($changes['added'] > 0) {
                $changeIndicator .= Ansi::GREEN . '+';
            }
            if ($changes['removed'] > 0) {
                $changeIndicator .= Ansi::RED . '-';
            }
            if ($changes['modified'] > 0) {
                $changeIndicator .= Ansi::YELLOW . 'M';
            }

            echo $changeIndicator . Ansi::RESET . ' ' . $this->truncate($file, 35);

            $this->moveCursor($row++, $col);
            echo '  ' . Ansi::DIM . "+{$changes['added']} -{$changes['removed']} ~{$changes['modified']}" . Ansi::RESET;
        }

        $row++;

        // Test Status
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '🧪 Test Status' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::GREEN . '✓ Passing: 42' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::RED . '✗ Failing: 1' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::YELLOW . '○ Pending: 5' . Ansi::RESET;

        $row++;

        // Suggested Commit Message
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '💾 Suggested Commit' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '"feat: add password hashing"' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '"- Implement bcrypt hashing"' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '"- Add Hash utility class"' . Ansi::RESET;
    }

    /**
     * Style 2: Debugging Console
     * WHY: When things go wrong, developers need error context and tool diagnostics
     * USEFUL FOR: Troubleshooting failures, understanding tool behavior, fixing issues
     */
    private function renderStyle2_DebuggingConsole(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Error & Warning Log
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '⚠️ Issues & Warnings' . Ansi::RESET;

        $row++;
        foreach ($this->errorLog as $log) {
            if ($row > 12) {
                break;
            }

            $this->moveCursor($row++, $col);
            $icon = match ($log['type']) {
                'error' => Ansi::RED . '✗',
                'warning' => Ansi::YELLOW . '⚠',
                'info' => Ansi::BLUE . 'ℹ',
            };

            echo $icon . Ansi::RESET . ' ' . Ansi::DIM . $log['time'] . Ansi::RESET;

            $this->moveCursor($row++, $col);
            echo '  ' . $this->truncate($log['message'], 38);

            $row++;
        }

        // Tool Diagnostics
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '🔧 Tool Performance' . Ansi::RESET;

        $row++;
        foreach ($this->toolMetrics as $tool => $metrics) {
            if ($row > $this->terminalHeight - 8) {
                break;
            }

            $this->moveCursor($row++, $col);
            $successRate = round(($metrics['success'] / $metrics['calls']) * 100);
            $color = $successRate >= 90 ? Ansi::GREEN : ($successRate >= 70 ? Ansi::YELLOW : Ansi::RED);

            echo $tool . ': ' . $color . $successRate . '%' . Ansi::RESET;
            echo ' (' . $metrics['calls'] . ' calls, ' . $metrics['avgTime'] . 's avg)';
        }

        $row++;

        // Current Stack Trace (simulated)
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '📚 Call Stack' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '→ processRequest()' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '  → executeTask()' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::CYAN . '    → WriteFile::execute()' . Ansi::RESET;
    }

    /**
     * Style 3: Manager Overview
     * WHY: Non-technical stakeholders need high-level progress and cost tracking
     * USEFUL FOR: Progress reporting, budget monitoring, timeline estimation
     */
    private function renderStyle3_ManagerOverview(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // High-level Progress
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '📊 Project Progress' . Ansi::RESET;

        $completed = count(array_filter($this->tasks, fn ($t) => $t['status'] === 'completed'));
        $total = count($this->tasks);
        $percentage = round(($completed / $total) * 100);

        $this->moveCursor($row++, $col);
        $this->renderVisualProgress($percentage, 35);

        $this->moveCursor($row++, $col);
        echo "Overall: {$percentage}% ({$completed}/{$total} tasks)";

        $row += 2;

        // Time & Cost Metrics
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '💰 Resources Used' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'Time Elapsed: ' . Ansi::CYAN . '15m 32s' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'Est. Remaining: ' . Ansi::YELLOW . '~8m' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'API Cost: ' . Ansi::GREEN . '$' . number_format($this->apiCost, 4) . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'Tokens Used: ' . number_format($this->totalTokens);

        $row += 2;

        // Milestone Summary
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '🎯 Milestones' . Ansi::RESET;

        $milestones = [
            ['name' => 'Code Analysis', 'status' => 'done'],
            ['name' => 'Implementation', 'status' => 'active'],
            ['name' => 'Testing', 'status' => 'pending'],
            ['name' => 'Documentation', 'status' => 'pending'],
        ];

        foreach ($milestones as $milestone) {
            if ($row > $this->terminalHeight - 6) {
                break;
            }

            $this->moveCursor($row++, $col);
            $icon = match ($milestone['status']) {
                'done' => Ansi::GREEN . '✓',
                'active' => Ansi::YELLOW . '●',
                'pending' => Ansi::DIM . '○',
            };

            echo $icon . ' ' . $milestone['name'] . Ansi::RESET;
        }

        $row++;

        // Efficiency Score
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '⚡ Efficiency' . Ansi::RESET;

        $efficiency = round((array_sum(array_column($this->toolMetrics, 'success')) /
                            array_sum(array_column($this->toolMetrics, 'calls'))) * 100);

        $this->moveCursor($row++, $col);
        echo 'Success Rate: ' . ($efficiency >= 90 ? Ansi::GREEN : Ansi::YELLOW) . $efficiency . '%' . Ansi::RESET;
    }

    /**
     * Style 4: Learning Mode
     * WHY: Users learning from the AI need to understand its reasoning and approach
     * USEFUL FOR: Education, understanding AI decisions, learning patterns
     */
    private function renderStyle4_LearningMode(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // Current Strategy
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '🧠 AI Strategy' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::CYAN . 'Approach: ' . Ansi::RESET . 'Test-Driven Development';

        $this->moveCursor($row++, $col);
        echo Ansi::CYAN . 'Pattern: ' . Ansi::RESET . 'Repository Pattern';

        $row++;

        // Decision Points
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '🤔 Recent Decisions' . Ansi::RESET;

        $decisions = [
            'Chose bcrypt over SHA256 for security',
            'Created utility class for reusability',
            'Added validation before hashing',
        ];

        foreach ($decisions as $decision) {
            if ($row > 16) {
                break;
            }
            $this->moveCursor($row++, $col);
            echo '• ' . $this->truncate($decision, 38);
        }

        $row++;

        // Conversation Context
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '💬 Conversation Flow' . Ansi::RESET;

        foreach ($this->conversationHistory as $i => $msg) {
            if ($row > $this->terminalHeight - 8) {
                break;
            }

            $this->moveCursor($row++, $col);
            $icon = $msg['role'] === 'user' ? '👤' : '🤖';
            $color = $msg['role'] === 'user' ? Ansi::BLUE : Ansi::GREEN;

            echo $icon . ' ' . $color . $this->truncate($msg['summary'], 35) . Ansi::RESET;
        }

        $row++;

        // Learning Tips
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '💡 Key Insights' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '• Always hash passwords before storage' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '• Use cost factor 10+ for bcrypt' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::DIM . '• Test with various input lengths' . Ansi::RESET;
    }

    /**
     * Style 5: Performance Monitor
     * WHY: Power users need to optimize speed and resource usage
     * USEFUL FOR: Performance tuning, identifying bottlenecks, resource optimization
     */
    private function renderStyle5_PerformanceMonitor(): void
    {
        $col = $this->mainAreaWidth + 3;
        $row = 3;

        // System Resources
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '⚙️ System Resources' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'CPU: ';
        $this->renderMiniBar($this->cpuUsage, 100, 20);
        echo ' ' . round($this->cpuUsage) . '%';

        $this->moveCursor($row++, $col);
        echo 'MEM: ';
        $this->renderMiniBar($this->memoryUsage, 512, 20);
        echo ' ' . round($this->memoryUsage) . 'MB';

        $row++;

        // API Performance
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '🌐 API Performance' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'Requests: ' . $this->apiCalls . ' (' . round($this->apiCalls / 15, 1) . '/min)';

        $this->moveCursor($row++, $col);
        echo 'Avg Response: ' . Ansi::GREEN . '230ms' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo 'Token Usage: ' . $this->totalTokens . '/150k';

        $row++;

        // Tool Performance Breakdown
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '🔧 Tool Efficiency' . Ansi::RESET;

        $row++;

        // Sort tools by total time
        $toolTimes = [];
        foreach ($this->toolMetrics as $tool => $metrics) {
            $toolTimes[$tool] = $metrics['calls'] * $metrics['avgTime'];
        }
        arsort($toolTimes);

        foreach (array_slice($toolTimes, 0, 5, true) as $tool => $totalTime) {
            if ($row > $this->terminalHeight - 6) {
                break;
            }

            $this->moveCursor($row++, $col);
            echo mb_str_pad($tool, 12) . ': ';
            $this->renderMiniBar($totalTime, max($toolTimes), 15);
            echo ' ' . round($totalTime, 1) . 's';
        }

        $row++;

        // Optimization Suggestions
        $this->moveCursor($row++, $col);
        echo Ansi::BOLD . '💡 Optimizations' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::YELLOW . '• Cache frequent file reads' . Ansi::RESET;

        $this->moveCursor($row++, $col);
        echo Ansi::YELLOW . '• Batch similar operations' . Ansi::RESET;
    }

    private function renderHeader(): void
    {
        $this->moveCursor(1, 1);
        echo Ansi::BG_DARK;

        $styleName = $this->getStyleName($this->currentStyle);
        echo ' 🎯 ' . Ansi::BOLD . "Purposeful Display: {$styleName}" . Ansi::RESET;
        echo Ansi::BG_DARK . str_repeat(' ', max(0, $this->terminalWidth - mb_strlen($styleName) - 25)) . Ansi::RESET;
    }

    private function renderMainArea(): void
    {
        $this->moveCursor(3, 2);
        echo Ansi::BOLD . 'Activity Feed' . Ansi::RESET;

        $this->moveCursor(5, 2);
        echo 'Style ' . $this->currentStyle . ': ' . $this->getStyleName($this->currentStyle);

        $this->moveCursor(7, 2);
        echo Ansi::DIM . 'Why this display?' . Ansi::RESET;

        $rationale = $this->getStyleRationale($this->currentStyle);
        $row = 8;
        foreach ($rationale as $point) {
            $this->moveCursor($row++, 4);
            echo '• ' . $point;
            if ($row > 20) {
                break;
            }
        }
    }

    private function renderVisualProgress(int $percentage, int $width): void
    {
        $filled = (int) (($percentage / 100) * $width);

        echo '[';
        for ($i = 0; $i < $width; $i++) {
            if ($i < $filled) {
                echo Ansi::GREEN . '█' . Ansi::RESET;
            } else {
                echo Ansi::DIM . '░' . Ansi::RESET;
            }
        }
        echo ']';
    }

    private function renderMiniBar(float $value, float $max, int $width): void
    {
        $percentage = min(100, ($value / $max) * 100);
        $filled = (int) (($percentage / 100) * $width);

        $color = $percentage > 80 ? Ansi::RED : ($percentage > 50 ? Ansi::YELLOW : Ansi::GREEN);

        echo $color;
        for ($i = 0; $i < $width; $i++) {
            echo ($i < $filled) ? '▓' : '░';
        }
        echo Ansi::RESET;
    }

    private function getStyleName(int $style): string
    {
        return match ($style) {
            1 => 'Developer Dashboard',
            2 => 'Debugging Console',
            3 => 'Manager Overview',
            4 => 'Learning Mode',
            5 => 'Performance Monitor',
            default => 'Unknown'
        };
    }

    private function getStyleRationale(int $style): array
    {
        return match ($style) {
            1 => [
                'Shows git-ready change summary',
                'Tracks test status for CI/CD',
                'Suggests commit messages',
                'Perfect for code review workflow',
            ],
            2 => [
                'Highlights errors and warnings',
                'Shows tool success rates',
                'Displays call stack for debugging',
                'Essential for troubleshooting',
            ],
            3 => [
                'High-level progress metrics',
                'Cost and time tracking',
                'Milestone visualization',
                'Non-technical stakeholder friendly',
            ],
            4 => [
                'Explains AI reasoning',
                'Shows decision points',
                'Tracks conversation flow',
                'Educational and transparent',
            ],
            5 => [
                'System resource monitoring',
                'API performance metrics',
                'Tool efficiency analysis',
                'Optimization suggestions',
            ],
            default => []
        };
    }

    private function renderFooter(): void
    {
        $this->moveCursor($this->terminalHeight - 1, 2);
        echo Ansi::DIM . 'Press 1-5 to switch displays | Q to quit' . Ansi::RESET;
    }

    private function handleInput(string $key): void
    {
        switch ($key) {
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
                $this->currentStyle = (int) $key;
                break;
            case 'q':
            case 'Q':
                $this->running = false;
                break;
        }
    }

    private function readKey(): ?string
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        if (stream_select($read, $write, $except, 0, 0) > 0) {
            return fgetc(STDIN);
        }

        return null;
    }

    private function clearScreen(): void
    {
        echo "\033[2J\033[H";
    }

    private function moveCursor(int $row, int $col): void
    {
        echo "\033[{$row};{$col}H";
    }

    private function updateTerminalSize(): void
    {
        $this->terminalHeight = (int) exec('tput lines') ?: 24;
        $this->terminalWidth = (int) exec('tput cols') ?: 80;
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 1) . '…';
    }
}

// Run the demo
echo "Starting Purposeful Display Demo...\n";
echo "Each display is designed for a specific user need.\n";
sleep(1);
$demo = new PurposefulSidebarVariations;
$demo->run();
echo "Demo ended.\n";
