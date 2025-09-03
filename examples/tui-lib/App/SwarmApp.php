<?php

declare(strict_types=1);

namespace Examples\TuiLib\App;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\Widget;
use Examples\TuiLib\Focus\FocusManager;
use Examples\TuiLib\Focus\FocusNode;

/**
 * Focus area enumeration
 */
enum FocusArea: string
{
    case ActivityLog = 'activity_log';
    case TaskList = 'task_list';
    case MainArea = 'main_area';
}

/**
 * Main application widget for the Swarm TUI demo
 */
class SwarmApp extends Widget
{
    protected ActivityLog $activityLog;

    protected TaskList $taskList;

    protected FocusManager $focusManager;

    protected FocusArea $currentFocus = FocusArea::ActivityLog;

    protected string $statusMessage = 'Ready';

    protected bool $showHelp = false;

    protected array $appTheme = [
        'border_color' => '90',
        'header_color' => '36',
        'status_color' => '32',
        'help_color' => '33',
    ];

    // Layout configuration
    protected int $sidebarWidth = 40;

    protected int $headerHeight = 1;

    protected int $statusHeight = 1;

    protected bool $sidebarOnLeft = true;

    public function __construct(?string $id = null)
    {
        parent::__construct($id);

        // Create child widgets
        $this->activityLog = new ActivityLog('activity_log');
        $this->taskList = new TaskList('task_list');

        // Setup focus management
        $this->focusManager = new FocusManager;
        $this->setupFocusTree();

        // Add child widgets
        $this->addChild($this->activityLog);
        $this->addChild($this->taskList);

        // Initialize with mock data
        $this->initializeMockData();
    }

    /**
     * Get the activity log widget
     */
    public function getActivityLog(): ActivityLog
    {
        return $this->activityLog;
    }

    /**
     * Get the task list widget
     */
    public function getTaskList(): TaskList
    {
        return $this->taskList;
    }

    /**
     * Get the focus manager
     */
    public function getFocusManager(): FocusManager
    {
        return $this->focusManager;
    }

    /**
     * Set status message
     */
    public function setStatusMessage(string $message): void
    {
        $this->statusMessage = $message;
        $this->markNeedsRepaint();
    }

    /**
     * Toggle help display
     */
    public function toggleHelp(): void
    {
        $this->showHelp = ! $this->showHelp;
        $this->markNeedsRepaint();
    }

    /**
     * Check if help is currently displayed
     */
    public function isShowingHelp(): bool
    {
        return $this->showHelp;
    }

    /**
     * Set sidebar width
     */
    public function setSidebarWidth(int $width): void
    {
        $this->sidebarWidth = max(20, min(60, $width));
        $this->markNeedsLayout();
    }

    /**
     * Toggle sidebar position
     */
    public function toggleSidebarPosition(): void
    {
        $this->sidebarOnLeft = ! $this->sidebarOnLeft;
        $this->markNeedsLayout();
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

        // Calculate layout areas
        $headerRect = new Rect(
            $bounds->x,
            $bounds->y,
            $bounds->width,
            $this->headerHeight
        );

        $statusRect = new Rect(
            $bounds->x,
            $bounds->y + $bounds->height - $this->statusHeight,
            $bounds->width,
            $this->statusHeight
        );

        $contentHeight = $bounds->height - $this->headerHeight - $this->statusHeight;
        $contentY = $bounds->y + $this->headerHeight;

        // Calculate sidebar and main area
        if ($this->sidebarOnLeft) {
            $sidebarRect = new Rect(
                $bounds->x,
                $contentY,
                $this->sidebarWidth,
                $contentHeight
            );

            $mainRect = new Rect(
                $bounds->x + $this->sidebarWidth,
                $contentY,
                $bounds->width - $this->sidebarWidth,
                $contentHeight
            );
        } else {
            $mainRect = new Rect(
                $bounds->x,
                $contentY,
                $bounds->width - $this->sidebarWidth,
                $contentHeight
            );

            $sidebarRect = new Rect(
                $bounds->x + $bounds->width - $this->sidebarWidth,
                $contentY,
                $this->sidebarWidth,
                $contentHeight
            );
        }

        // Layout child widgets
        $this->taskList->layout($sidebarRect);
        $this->activityLog->layout($mainRect);

        $this->clearLayoutFlag();
    }

    public function paint(BuildContext $context): string
    {
        if ($this->bounds === null) {
            return '';
        }

        $output = '';
        $width = $this->bounds->width;
        $height = $this->bounds->height;

        // Paint header
        $output .= $this->paintHeader($width);

        // Paint content areas with borders
        $output .= $this->paintContentBorders();

        // Paint child widgets with focus context
        $currentFocus = $this->focusManager->getCurrentFocus();

        // Paint task list
        $taskContext = $context->withFocus($currentFocus?->getId() === 'task_list');
        $output .= $this->taskList->paint($taskContext);

        // Paint activity log
        $activityContext = $context->withFocus($currentFocus?->getId() === 'activity_log');
        $output .= $this->activityLog->paint($activityContext);

        // Paint status bar
        $output .= $this->paintStatusBar($width);

        // Paint help overlay if shown
        if ($this->showHelp) {
            $output .= $this->paintHelpOverlay($width, $height);
        }

        $this->clearRepaintFlag();

        return $output;
    }

    public function handleKeyEvent(string $key): bool
    {
        // Global key handlers
        switch ($key) {
            case "\t": // Tab - switch focus
                return $this->switchFocus();
            case "\033[Z": // Shift+Tab - reverse focus
                return $this->switchFocusReverse();
            case 'h':
            case '?':
                $this->toggleHelp();

                return true;
            case 'q':
                // Quit signal - handled by main loop
                return false;
            case "\033[1;5A": // Ctrl+Up - increase sidebar
                $this->setSidebarWidth($this->sidebarWidth + 5);

                return true;
            case "\033[1;5B": // Ctrl+Down - decrease sidebar
                $this->setSidebarWidth($this->sidebarWidth - 5);

                return true;
            case "\033[1;5C": // Ctrl+Right - toggle sidebar position
                $this->toggleSidebarPosition();

                return true;
        }

        // Forward to focused widget
        $currentFocus = $this->focusManager->getCurrentFocus();
        if ($currentFocus !== null) {
            $handled = match ($currentFocus->getId()) {
                'activity_log' => $this->activityLog->handleKeyEvent($key),
                'task_list' => $this->taskList->handleKeyEvent($key),
                default => false,
            };

            if ($handled) {
                $this->updateStatusFromFocusedWidget();

                return true;
            }
        }

        return false;
    }

    /**
     * Add a new activity to the log
     */
    public function addActivity(Activity $activity): void
    {
        $this->activityLog->addActivity($activity);
        $this->markNeedsRepaint();
    }

    /**
     * Add a new task to the list
     */
    public function addTask(Task $task): void
    {
        $this->taskList->addTask($task);
        $this->markNeedsRepaint();
    }

    /**
     * Get application statistics
     */
    public function getStats(): array
    {
        return [
            'activities' => count($this->activityLog->getActivities()),
            'tasks' => $this->taskList->getTaskStats(),
            'current_focus' => $this->focusManager->getCurrentFocus()?->getId(),
            'sidebar_width' => $this->sidebarWidth,
            'sidebar_position' => $this->sidebarOnLeft ? 'left' : 'right',
        ];
    }

    /**
     * Initialize the app with mock data
     */
    protected function initializeMockData(): void
    {
        // Load mock activities
        $activities = MockData::generateActivities(50);
        $this->activityLog->setActivities($activities);

        // Load mock tasks
        $tasks = MockData::generateTasks(15);
        $this->taskList->setTasks($tasks);
    }

    /**
     * Setup focus tree for navigation
     */
    protected function setupFocusTree(): void
    {
        $rootNode = new FocusNode('app_root', canRequestFocus: false);

        $activityNode = new FocusNode('activity_log', canRequestFocus: true);
        $taskNode = new FocusNode('task_list', canRequestFocus: true);

        $rootNode->addChild($activityNode);
        $rootNode->addChild($taskNode);

        $this->focusManager->setRootNode($rootNode);
        $this->focusManager->requestFocus($activityNode);
    }

    /**
     * Switch to next focusable widget
     */
    protected function switchFocus(): bool
    {
        $moved = $this->focusManager->nextFocus();
        if ($moved) {
            $this->updateStatusFromFocusedWidget();
            $this->markNeedsRepaint();
        }

        return $moved;
    }

    /**
     * Switch to previous focusable widget
     */
    protected function switchFocusReverse(): bool
    {
        $moved = $this->focusManager->previousFocus();
        if ($moved) {
            $this->updateStatusFromFocusedWidget();
            $this->markNeedsRepaint();
        }

        return $moved;
    }

    /**
     * Update status message based on focused widget
     */
    protected function updateStatusFromFocusedWidget(): void
    {
        $currentFocus = $this->focusManager->getCurrentFocus();
        if ($currentFocus === null) {
            $this->setStatusMessage('Ready');

            return;
        }

        match ($currentFocus->getId()) {
            'activity_log' => $this->updateActivityLogStatus(),
            'task_list' => $this->updateTaskListStatus(),
            default => $this->setStatusMessage('Ready'),
        };
    }

    /**
     * Update status based on activity log state
     */
    protected function updateActivityLogStatus(): void
    {
        $selected = $this->activityLog->getSelectedActivity();
        $total = count($this->activityLog->getActivities());

        if ($selected !== null) {
            $this->setStatusMessage("Activity: {$selected->type->value} | {$selected->message}");
        } else {
            $this->setStatusMessage("Activity Log | {$total} activities | c=clear, t=toggle timestamps");
        }
    }

    /**
     * Update status based on task list state
     */
    protected function updateTaskListStatus(): void
    {
        $selected = $this->taskList->getSelectedTask();
        $stats = $this->taskList->getTaskStats();

        if ($selected !== null) {
            $this->setStatusMessage("Task: {$selected->status->value} | {$selected->title}");
        } else {
            $total = $stats['total'];
            $completed = $stats['completed'];
            $this->setStatusMessage("Tasks | {$completed}/{$total} completed | d=descriptions, f=filter, r=reset");
        }
    }

    /**
     * Paint the header
     */
    protected function paintHeader(int $width): string
    {
        $title = 'Swarm TUI Demo';
        $currentFocus = $this->focusManager->getCurrentFocus();
        $focusInfo = $currentFocus ? ' [' . $currentFocus->getId() . ']' : '';

        $headerText = $title . $focusInfo;
        $padding = max(0, $width - mb_strlen($headerText));
        $line = $headerText . str_repeat(' ', $padding);

        $color = $this->appTheme['header_color'];

        return "\033[{$this->bounds->y};{$this->bounds->x}H\033[{$color};1m{$line}\033[0m";
    }

    /**
     * Paint content area borders
     */
    protected function paintContentBorders(): string
    {
        $output = '';
        $borderColor = $this->appTheme['border_color'];

        // Vertical separator between sidebar and main area
        $separatorX = $this->sidebarOnLeft
            ? $this->bounds->x + $this->sidebarWidth
            : $this->bounds->x + $this->bounds->width - $this->sidebarWidth;

        $contentStart = $this->bounds->y + $this->headerHeight;
        $contentEnd = $this->bounds->y + $this->bounds->height - $this->statusHeight;

        for ($y = $contentStart; $y < $contentEnd; $y++) {
            $output .= "\033[{$y};{$separatorX}H\033[{$borderColor}m│\033[0m";
        }

        return $output;
    }

    /**
     * Paint the status bar
     */
    protected function paintStatusBar(int $width): string
    {
        $y = $this->bounds->y + $this->bounds->height - 1;

        $status = $this->statusMessage;
        $helpHint = ' | h=help, q=quit';

        $availableWidth = max(0, $width - mb_strlen($helpHint));
        if (mb_strlen($status) > $availableWidth) {
            $status = mb_substr($status, 0, $availableWidth - 1) . '…';
        }

        $padding = max(0, $width - mb_strlen($status) - mb_strlen($helpHint));
        $line = $status . str_repeat(' ', $padding) . $helpHint;

        $color = $this->appTheme['status_color'];

        return "\033[{$y};{$this->bounds->x}H\033[{$color}m{$line}\033[0m";
    }

    /**
     * Paint help overlay
     */
    protected function paintHelpOverlay(int $width, int $height): string
    {
        $helpContent = [
            'Swarm TUI Demo - Help',
            '',
            'Navigation:',
            '  Tab/Shift+Tab    Switch between panels',
            '  Arrow Keys       Navigate within panel',
            '  Page Up/Down     Page through lists',
            '  Home/End         Go to start/end',
            '',
            'Activity Log:',
            '  c                Clear log',
            '  t                Toggle timestamps',
            '',
            'Task List:',
            '  d                Toggle descriptions',
            '  f                Filter by status',
            '  r                Reset filter',
            '',
            'Global:',
            '  Ctrl+Up/Down     Resize sidebar',
            '  Ctrl+Right       Toggle sidebar position',
            '  h or ?           Toggle this help',
            '  q                Quit',
            '',
            'Press any key to close help...',
        ];

        // Calculate overlay size
        $overlayWidth = min($width - 4, 60);
        $overlayHeight = min($height - 4, count($helpContent) + 2);
        $overlayX = $this->bounds->x + intval(($width - $overlayWidth) / 2);
        $overlayY = $this->bounds->y + intval(($height - $overlayHeight) / 2);

        $output = '';
        $helpColor = $this->appTheme['help_color'];

        // Paint overlay background and border
        for ($y = 0; $y < $overlayHeight; $y++) {
            $line = str_repeat(' ', $overlayWidth);
            $currentY = $overlayY + $y;
            $output .= "\033[{$currentY};{$overlayX}H\033[40;{$helpColor}m{$line}\033[0m";
        }

        // Paint help content
        foreach ($helpContent as $i => $line) {
            if ($i >= $overlayHeight - 2) {
                break;
            }

            $contentY = $overlayY + $i + 1;
            $contentX = $overlayX + 2;
            $maxLineWidth = $overlayWidth - 4;

            if (mb_strlen($line) > $maxLineWidth) {
                $line = mb_substr($line, 0, $maxLineWidth);
            }

            $output .= "\033[{$contentY};{$contentX}H\033[{$helpColor}m{$line}\033[0m";
        }

        return $output;
    }
}
