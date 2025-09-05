<?php

declare(strict_types=1);

namespace Examples\TuiLib\SwarmMock;

use Closure;
use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Icons;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Theme;
use Examples\TuiLib\Core\Widget;

/**
 * SwarmMockApp - Main application widget that exactly replicates FullTerminalUI layout
 *
 * This is the exact replica of the FullTerminalUI layout and behavior to test
 * the framework's ability to handle the same rendering challenges.
 */
class SwarmMockApp extends Widget
{
    // Focus modes (exact from FullTerminalUI)
    const FOCUS_MAIN = 'main';

    const FOCUS_TASKS = 'tasks';

    const FOCUS_CONTEXT = 'context';

    // Components
    protected SwarmHeader $header;

    protected SwarmActivityLog $activityLog;

    protected SwarmSidebar $sidebar;

    protected SwarmInput $input;

    protected Theme $theme;

    // State (exact from FullTerminalUI)
    protected array $history = [];

    protected array $expandedThoughts = [];

    protected array $tasks = [];

    protected array $context = [
        'directory' => '',
        'files' => [],
        'tools' => [],
        'notes' => [],
    ];

    protected array $pendingToolCalls = [];

    protected array $activityFeed = [];

    protected string $currentTask = '';

    protected string $status = 'Ready';

    protected int $currentStep = 0;

    protected int $totalSteps = 0;

    protected bool $showTaskOverlay = false;

    protected bool $showHelp = false;

    protected int $selectedTaskIndex = 0;

    protected int $selectedContextLine = 0;

    protected int $taskScrollOffset = 0;

    // Layout (exact from FullTerminalUI)
    protected int $sidebarWidth = 30;

    protected string $currentFocus = self::FOCUS_MAIN;

    protected string $modKey = 'Option';

    protected string $modSymbol = '⌥';

    // Callbacks
    protected Closure $onCommand;

    protected Closure $onTaskSelect;

    public function __construct(string $id = 'swarm_mock_app')
    {
        parent::__construct($id);

        // Initialize theme
        $this->theme = Theme::swarm();

        // Initialize components
        $this->header = new SwarmHeader('header');
        $this->activityLog = new SwarmActivityLog('activity_log');
        $this->sidebar = new SwarmSidebar('sidebar');
        $this->input = new SwarmInput('input');

        // Add children
        $this->addChild($this->header);
        $this->addChild($this->activityLog);
        $this->addChild($this->sidebar);
        $this->addChild($this->input);

        // Setup callbacks
        $this->onCommand = function (string $command) {
            $this->addHistory('command', $command);
        };

        $this->onTaskSelect = function (array $task) {
            // Task selection logic
        };

        // Configure input callback
        $this->input->setOnSubmit(function (string $command) {
            call_user_func($this->onCommand, $command);
        });

        // Set modifier symbol
        $this->detectOS();
        $this->input->setModSymbol($this->modSymbol);

        // Initialize with mock data
        $this->initializeMockData();
    }

    /**
     * Set command callback
     */
    public function setOnCommand(Closure $callback): void
    {
        $this->onCommand = $callback;
    }

    /**
     * Set task selection callback
     */
    public function setOnTaskSelect(Closure $callback): void
    {
        $this->onTaskSelect = $callback;
    }

    /**
     * Add a history entry (exact API from FullTerminalUI)
     */
    public function addHistory(string $type, string $content, string $params = '', string $result = ''): void
    {
        $entry = [
            'time' => time(),
            'type' => $type,
            'content' => $content,
        ];

        if ($type === 'tool') {
            $entry['tool'] = $content;
            $entry['params'] = $params;
            $entry['result'] = $result;
        }

        $this->history[] = $entry;

        if (count($this->history) > 100) {
            array_shift($this->history);
        }

        $this->activityLog->setHistory($this->history);
        $this->markNeedsRepaint();
    }

    /**
     * Add activity entry (exact API from FullTerminalUI)
     */
    public function addActivity($activityEntry): void
    {
        $this->activityFeed[] = $activityEntry;

        if (count($this->activityFeed) > 50) {
            array_shift($this->activityFeed);
        }

        $this->activityLog->setActivityFeed($this->activityFeed);

        // Also add simplified version to history
        if (method_exists($activityEntry, 'getMessage')) {
            $this->addHistory('activity', $activityEntry->getMessage());
        }

        $this->markNeedsRepaint();
    }

    /**
     * Update status (exact API from FullTerminalUI)
     */
    public function updateProcessingMessage(string $message): void
    {
        $this->status = $message;
        $this->header->setStatus($this->status);
        $this->markNeedsRepaint();
    }

    /**
     * Set progress (exact API from FullTerminalUI)
     */
    public function setProgress(int $current, int $total): void
    {
        $this->currentStep = $current;
        $this->totalSteps = $total;
        $this->header->setProgress($this->currentStep, $this->totalSteps);
        $this->markNeedsRepaint();
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        return $constraints->biggest();
    }

    public function layout(Rect $bounds): void
    {
        $this->setBounds($bounds);

        // Calculate sidebar width (exact logic from FullTerminalUI)
        $this->sidebarWidth = max(30, (int) ($bounds->width * 0.25));
        $mainAreaWidth = $bounds->width - $this->sidebarWidth - 1;

        // Header layout (full width, 1 row)
        $headerRect = new Rect($bounds->x, $bounds->y, $bounds->width, 1);
        $this->header->layout($headerRect);

        // Activity log layout (main area, minus header and input)
        $activityRect = new Rect(
            $bounds->x,
            $bounds->y + 1,
            $mainAreaWidth,
            $bounds->height - 3 // Header + input area
        );
        $this->activityLog->layout($activityRect);

        // Sidebar layout (right side)
        $sidebarRect = new Rect(
            $bounds->x + $mainAreaWidth + 1,
            $bounds->y + 1,
            $this->sidebarWidth,
            $bounds->height - 3
        );
        $this->sidebar->layout($sidebarRect);

        // Input layout (bottom, main area width)
        $inputRect = new Rect(
            $bounds->x,
            $bounds->y + $bounds->height - 2,
            $mainAreaWidth,
            2
        );
        $this->input->layout($inputRect);

        $this->clearLayoutFlag();
    }

    public function paint(BuildContext $context): string
    {
        if ($this->bounds === null) {
            return '';
        }

        $canvas = $context->canvas;
        if (! $canvas) {
            return '';
        }

        // Paint header to canvas
        $this->header->paint($context);

        // Paint vertical divider using Canvas instead of direct ANSI
        $mainAreaWidth = $this->bounds->width - $this->sidebarWidth - 1;
        $dividerCol = $this->bounds->x + $mainAreaWidth;

        $dividerStyle = $this->theme->get('ui.border');
        for ($row = $this->bounds->y + 1; $row < $this->bounds->y + $this->bounds->height - 2; $row++) {
            $canvas->drawText($dividerCol, $row, $dividerStyle->apply(Icons::get('separator_v')));
        }

        // Paint components with focus context
        $activityContext = $context->withFocus($this->currentFocus === self::FOCUS_MAIN);
        $this->activityLog->paint($activityContext);

        // Set sidebar focus
        $sidebarFocusArea = match ($this->currentFocus) {
            self::FOCUS_TASKS => 'tasks',
            self::FOCUS_CONTEXT => 'context',
            default => 'none'
        };
        $this->sidebar->setFocused(
            in_array($this->currentFocus, [self::FOCUS_TASKS, self::FOCUS_CONTEXT]),
            $sidebarFocusArea
        );
        $this->sidebar->paint($context);

        // Set input focus
        $this->input->setFocused($this->currentFocus === self::FOCUS_MAIN);
        $this->input->paint($context);

        // Paint overlays if needed
        if ($this->showTaskOverlay) {
            $this->renderTaskOverlayToCanvas($canvas);
        }

        if ($this->showHelp) {
            $this->renderHelpOverlayToCanvas($canvas);
        }

        $this->clearRepaintFlag();

        return '';
    }

    /**
     * Handle key events (exact logic from FullTerminalUI)
     */
    public function handleKeyEvent(string $key): bool
    {
        // Handle overlay-specific keys first
        if ($this->showTaskOverlay) {
            return $this->handleTaskOverlayInput($key);
        }

        if ($this->showHelp) {
            return $this->handleHelpInput($key);
        }

        // Handle global shortcuts
        if (str_starts_with($key, 'ALT+')) {
            return $this->handleGlobalShortcuts($key);
        }

        // Handle focus-specific input
        switch ($this->currentFocus) {
            case self::FOCUS_MAIN:
                return $this->handleMainInput($key);
            case self::FOCUS_TASKS:
            case self::FOCUS_CONTEXT:
                return $this->sidebar->handleKeyEvent($key);
        }

        return false;
    }

    /**
     * Get current input text
     */
    public function getCurrentInput(): string
    {
        return $this->input->getInput();
    }

    /**
     * Clear current input
     */
    public function clearInput(): void
    {
        $this->input->clearInput();
    }

    /**
     * Check if needs repaint
     */
    public function needsRepaint(): bool
    {
        $appNeedsRepaint = $this->needsRepaint;
        $headerNeedsRepaint = $this->header->needsRepaint();
        $activityLogNeedsRepaint = $this->activityLog->needsRepaint();
        $sidebarNeedsRepaint = $this->sidebar->needsRepaint();
        $inputNeedsRepaint = $this->input->needsRepaint();

        $needsRepaint = $appNeedsRepaint || $headerNeedsRepaint || $activityLogNeedsRepaint || $sidebarNeedsRepaint || $inputNeedsRepaint;

        // Debug: Log which components need repainting (but throttle to avoid spam)
        static $debugCount = 0;
        if ($needsRepaint && ++$debugCount % 100 === 0) { // Only log every 100th repaint request
            \Examples\TuiLib\Core\Logger::debug('Repaint needed', [
                'app' => $appNeedsRepaint,
                'header' => $headerNeedsRepaint,
                'activityLog' => $activityLogNeedsRepaint,
                'sidebar' => $sidebarNeedsRepaint,
                'input' => $inputNeedsRepaint,
                'debug_count' => $debugCount,
            ]);
        }

        return $needsRepaint;
    }

    /**
     * Get application statistics
     */
    public function getStats(): array
    {
        return [
            'history_count' => count($this->history),
            'activity_count' => count($this->activityFeed),
            'task_count' => count($this->tasks),
            'expanded_thoughts' => count($this->expandedThoughts),
            'current_focus' => $this->currentFocus,
            'sidebar_width' => $this->sidebarWidth,
        ];
    }

    /**
     * Initialize with Swarm-specific mock data
     */
    protected function initializeMockData(): void
    {
        // Load mock history
        $this->history = SwarmMockData::generateSwarmHistory(30);
        $this->activityLog->setHistory($this->history);

        // Load mock activity feed
        $this->activityFeed = SwarmMockData::generateRecentActivities(15);
        $this->activityLog->setActivityFeed($this->activityFeed);

        // Load mock tasks
        $this->tasks = SwarmMockData::generateSwarmTasks(12);
        $this->sidebar->setTasks($this->tasks);

        // Load mock context
        $this->context = SwarmMockData::generateSwarmContext();
        $this->sidebar->setContext($this->context);

        // Set initial progress
        $progress = SwarmMockData::getCurrentProgress();
        $this->currentStep = $progress['current_step'];
        $this->totalSteps = $progress['total_steps'];
        $this->status = $progress['operation'];

        // Update header
        $this->header->setStatus($this->status);
        $this->header->setProgress($this->currentStep, $this->totalSteps);
        $this->header->setCurrentFocus($this->currentFocus);
    }

    /**
     * Handle main area input (exact from FullTerminalUI)
     */
    protected function handleMainInput(string $key): bool
    {
        // Tab to cycle focus
        if ($key === 'TAB') {
            $this->currentFocus = self::FOCUS_TASKS;
            $this->header->setCurrentFocus($this->currentFocus);
            $this->markNeedsRepaint();

            return true;
        }

        // Forward to input widget
        return $this->input->handleKeyEvent($key);
    }

    /**
     * Handle global shortcuts (exact from FullTerminalUI)
     */
    protected function handleGlobalShortcuts(string $key): bool
    {
        switch ($key) {
            case 'ALT+Q':
                // Quit signal - handled by main loop
                return false;
            case 'ALT+T':
                $this->showTaskOverlay = ! $this->showTaskOverlay;
                $this->markNeedsRepaint();

                return true;
            case 'ALT+H':
                $this->showHelp = true;
                $this->markNeedsRepaint();

                return true;
            case 'ALT+C':
                if ($this->currentFocus === self::FOCUS_MAIN) {
                    $this->history = [];
                    $this->activityFeed = [];
                    $this->activityLog->setHistory($this->history);
                    $this->activityLog->setActivityFeed($this->activityFeed);
                    $this->addHistory('system', 'History cleared');
                    $this->markNeedsRepaint();
                }

                return true;
            case 'ALT+R':
                // Toggle thoughts or refresh
                $thoughtToggled = $this->activityLog->toggleNearestThought();
                if (! $thoughtToggled) {
                    $this->addHistory('system', 'Display refreshed');
                }
                $this->markNeedsRepaint();

                return true;
            case 'ALT+1':
                $this->currentFocus = self::FOCUS_MAIN;
                $this->header->setCurrentFocus($this->currentFocus);
                $this->markNeedsRepaint();

                return true;
            case 'ALT+2':
                $this->currentFocus = self::FOCUS_TASKS;
                $this->header->setCurrentFocus($this->currentFocus);
                $this->markNeedsRepaint();

                return true;
            case 'ALT+3':
                $this->currentFocus = self::FOCUS_CONTEXT;
                $this->header->setCurrentFocus($this->currentFocus);
                $this->markNeedsRepaint();

                return true;
        }

        return false;
    }

    /**
     * Handle task overlay input (simplified version)
     */
    protected function handleTaskOverlayInput(string $key): bool
    {
        if ($key === 'ESC' || $key === 'ALT+T') {
            $this->showTaskOverlay = false;
            $this->markNeedsRepaint();

            return true;
        }

        // Add navigation logic here
        return false;
    }

    /**
     * Handle help input
     */
    protected function handleHelpInput(string $key): bool
    {
        $this->showHelp = false;
        $this->markNeedsRepaint();

        return true;
    }

    /**
     * Render task overlay (placeholder)
     */
    protected function renderTaskOverlay(): string
    {
        // Placeholder for task overlay
        return '';
    }

    /**
     * Render help overlay (placeholder)
     */
    protected function renderHelpOverlay(): string
    {
        // Placeholder for help overlay
        return '';
    }

    /**
     * Detect OS for modifier keys (exact from FullTerminalUI)
     */
    protected function detectOS(): void
    {
        $isMacOS = mb_stripos(PHP_OS, 'darwin') !== false;
        if ($isMacOS) {
            $this->modKey = 'Option';
            $this->modSymbol = '⌥';
        } else {
            $this->modKey = 'Alt';
            $this->modSymbol = 'Alt+';
        }
    }
}
