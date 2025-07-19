<?php

namespace HelgeSverre\Swarm\CLI;

use HelgeSverre\Swarm\CLI\Activity\ActivityEntry;
use HelgeSverre\Swarm\CLI\Layout\PaneLayout;
use HelgeSverre\Swarm\CLI\Layout\ScrollablePane;
use HelgeSverre\Swarm\Enums\CLI\AnsiColor;
use HelgeSverre\Swarm\Enums\CLI\BoxCharacter;
use HelgeSverre\Swarm\Enums\CLI\ThemeColor;

/**
 * Three-pane UI with scrollable main area
 * 
 * Layout:
 * ┌─────────────┬───────────────────────────┬──────────────┐
 * │ File Tree   ┃      Main Chat Area       ┃   Context    │
 * │             ┃   (Scrollable History)    ┃    Notes     │
 * │             ┃                           ┃              │
 * └─────────────┴───────────────────────────┴──────────────┘
 */
class UIThreePanes extends UI
{
    protected PaneLayout $layout;
    protected ScrollablePane $mainPane;
    protected array $fileTree = [];
    protected array $contextNotes = [];
    
    protected int $focusedPane = 1; // 0=left, 1=main, 2=right
    protected bool $isMacOS = false;
    protected string $modKey = 'Ctrl';
    protected string $modSymbol = '^';
    
    // Full conversation render cache
    protected array $renderedConversation = [];
    protected bool $conversationCacheDirty = true;
    
    public function __construct()
    {
        parent::__construct();
        
        // Initialize layout
        $this->layout = new PaneLayout($this->terminalWidth, $this->terminalHeight);
        
        // Initialize scrollable main pane
        $mainDims = $this->layout->getMainAreaDimensions();
        $this->mainPane = new ScrollablePane($mainDims['width'], $mainDims['height']);
        
        // Detect OS for key labels
        $this->detectOS();
        
        // Initialize demo data
        $this->initializeDemoData();
    }
    
    protected function detectOS(): void
    {
        $this->isMacOS = stripos(PHP_OS, 'darwin') !== false;
        if ($this->isMacOS) {
            $this->modKey = 'Option';
            $this->modSymbol = '⌥';
        }
    }
    
    protected function initializeDemoData(): void
    {
        // Demo file tree
        $this->fileTree = [
            ['name' => 'src/', 'type' => 'dir', 'expanded' => true, 'level' => 0],
            ['name' => '  CLI/', 'type' => 'dir', 'expanded' => true, 'level' => 1],
            ['name' => '    UI.php', 'type' => 'file', 'level' => 2],
            ['name' => '    UIThreePanes.php', 'type' => 'file', 'level' => 2, 'active' => true],
            ['name' => '  Agent/', 'type' => 'dir', 'expanded' => false, 'level' => 1],
            ['name' => 'composer.json', 'type' => 'file', 'level' => 0],
        ];
        
        // Demo context notes
        $this->contextNotes = [
            'Current Task: Implementing three-pane UI',
            'Model: GPT-4',
            'Session: 2h 15m',
        ];
    }
    
    public function refresh(array $status): void
    {
        // Mark conversation cache as dirty if history changed
        $this->conversationCacheDirty = true;
        
        $this->clearScreen();
        $this->drawHeader();
        $this->drawPanes($status);
        $this->drawFooter();
        
        // No need for extra newlines as we're drawing full screen
    }
    
    protected function drawPanes(array $status): void
    {
        // Draw the three panes side by side
        $leftDims = $this->layout->getLeftPaneDimensions();
        $mainDims = $this->layout->getMainAreaDimensions();
        $rightDims = $this->layout->getRightPaneDimensions();
        
        // Prepare main area content
        $this->prepareMainContent($status);
        
        // Draw content line by line
        for ($row = 0; $row < max($leftDims['height'], $mainDims['height'], $rightDims['height']); $row++) {
            $line = '';
            
            // Left pane
            if ($this->layout->hasSidebars() && $leftDims['width'] > 0) {
                $leftContent = $this->getLeftPaneContent($row);
                $line .= $this->padContent($leftContent, $leftDims['width']);
                $line .= $this->colorize(BoxCharacter::VerticalHeavy->getChar(), ThemeColor::Border);
            }
            
            // Main pane
            $mainContent = $this->getMainPaneContent($row);
            $line .= $this->padContent($mainContent, $mainDims['width']);
            
            // Right pane
            if ($this->layout->hasSidebars() && $rightDims['width'] > 0) {
                $line .= $this->colorize(BoxCharacter::VerticalHeavy->getChar(), ThemeColor::Border);
                $rightContent = $this->getRightPaneContent($row);
                $line .= $this->padContent($rightContent, $rightDims['width']);
            }
            
            echo $line . "\n";
        }
    }
    
    protected function prepareMainContent(array $status): void
    {
        if (!$this->conversationCacheDirty) {
            return;
        }
        
        $this->renderedConversation = [];
        
        // Add task status if any
        if (!empty($status['current_task']) || !empty($status['tasks'])) {
            $this->renderedConversation[] = $this->colorize('━━━ Current Tasks ━━━', ThemeColor::Header);
            
            if ($status['current_task']) {
                $desc = $status['current_task']['description'] ?? '';
                $this->renderedConversation[] = $this->colorize('🎯 ' . $desc, ThemeColor::Accent);
            }
            
            foreach (($status['tasks'] ?? []) as $task) {
                $icon = $task['status'] === 'completed' ? '✓' : '○';
                $this->renderedConversation[] = $this->colorize("  {$icon} " . $task['description'], ThemeColor::Muted);
            }
            
            $this->renderedConversation[] = '';
        }
        
        // Add conversation history (all of it, not just recent)
        $history = $this->getFullConversationHistory($status);
        
        foreach ($history as $entry) {
            $this->renderedConversation[] = $this->colorize('─────', ThemeColor::Border);
            
            // Format based on role
            $role = $entry['role'] ?? 'unknown';
            $content = $entry['content'] ?? '';
            $timestamp = isset($entry['timestamp']) ? date('H:i', $entry['timestamp']) : '';
            
            // Role header
            $roleDisplay = match($role) {
                'user' => $this->colorize('You', ThemeColor::Accent) . " {$timestamp}",
                'assistant' => $this->colorize('Assistant', ThemeColor::Success) . " {$timestamp}",
                'tool' => $this->colorize('Tool', ThemeColor::Warning) . " {$timestamp}",
                default => $this->colorize(ucfirst($role), ThemeColor::Muted) . " {$timestamp}",
            };
            
            $this->renderedConversation[] = $roleDisplay;
            $this->renderedConversation[] = '';
            
            // Content - wrap long lines
            $lines = explode("\n", $content);
            $mainDims = $this->layout->getMainAreaDimensions();
            $wrapWidth = $mainDims['width'] - 4; // Account for padding
            
            foreach ($lines as $line) {
                if (mb_strlen($line) > $wrapWidth) {
                    // Wrap long lines
                    $wrapped = wordwrap($line, $wrapWidth, "\n", true);
                    $wrappedLines = explode("\n", $wrapped);
                    foreach ($wrappedLines as $wl) {
                        $this->renderedConversation[] = '  ' . $wl;
                    }
                } else {
                    $this->renderedConversation[] = '  ' . $line;
                }
            }
            
            $this->renderedConversation[] = '';
        }
        
        // Update scrollable pane with rendered content
        $this->mainPane->setContent($this->renderedConversation);
        $this->mainPane->scrollToBottom(); // Auto-scroll to latest
        
        $this->conversationCacheDirty = false;
    }
    
    protected function getFullConversationHistory(array $status): array
    {
        // Combine all sources of conversation history
        $allHistory = [];
        
        // From synced state
        if (isset($status['conversation_history'])) {
            foreach ($status['conversation_history'] as $entry) {
                $allHistory[] = $entry;
            }
        }
        
        // From internal history (avoiding duplicates)
        $existingTimestamps = array_column($allHistory, 'timestamp');
        foreach ($this->history as $entry) {
            $timestamp = $entry->timestamp;
            if (!in_array($timestamp, $existingTimestamps)) {
                $allHistory[] = [
                    'role' => method_exists($entry, 'getRole') ? $entry->getRole() : 'activity',
                    'content' => $entry->getMessage(),
                    'timestamp' => $timestamp,
                ];
            }
        }
        
        // Sort by timestamp
        usort($allHistory, fn($a, $b) => ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0));
        
        return $allHistory;
    }
    
    protected function getLeftPaneContent(int $row): string
    {
        // File tree header
        if ($row === 0) {
            $header = ' 📁 Files ';
            return $this->colorize($header, $this->focusedPane === 0 ? ThemeColor::Accent : ThemeColor::Header);
        }
        
        if ($row === 1) {
            return $this->colorize(' ' . str_repeat('─', 18), ThemeColor::Border);
        }
        
        // File tree content
        $fileIndex = $row - 2;
        if (isset($this->fileTree[$fileIndex])) {
            $item = $this->fileTree[$fileIndex];
            $icon = $item['type'] === 'dir' ? 
                ($item['expanded'] ? '▼' : '▶') : '·';
            $name = $item['name'];
            $color = isset($item['active']) && $item['active'] ? ThemeColor::Accent : ThemeColor::Muted;
            
            return ' ' . $this->colorize($icon . ' ' . $name, $color);
        }
        
        return '';
    }
    
    protected function getMainPaneContent(int $row): string
    {
        $visibleLines = $this->mainPane->getVisibleLines();
        $scrollInfo = $this->mainPane->getScrollInfo();
        
        // Show scroll indicators
        if ($row === 0 && $scrollInfo['canScrollUp']) {
            return $this->colorize(' ↑ More above (' . $scrollInfo['offset'] . ' lines) ↑ ', ThemeColor::Muted);
        }
        
        $contentRow = $scrollInfo['canScrollUp'] ? $row - 1 : $row;
        
        if (isset($visibleLines[$contentRow])) {
            return ' ' . $visibleLines[$contentRow] . ' ';
        }
        
        // Show bottom scroll indicator
        $mainDims = $this->layout->getMainAreaDimensions();
        if ($row === $mainDims['height'] - 1 && $scrollInfo['canScrollDown']) {
            $remaining = $scrollInfo['total'] - $scrollInfo['offset'] - $scrollInfo['visible'];
            return $this->colorize(' ↓ More below (' . $remaining . ' lines) ↓ ', ThemeColor::Muted);
        }
        
        return '';
    }
    
    protected function getRightPaneContent(int $row): string
    {
        // Context header
        if ($row === 0) {
            $header = ' 📝 Context ';
            return $this->colorize($header, $this->focusedPane === 2 ? ThemeColor::Accent : ThemeColor::Header);
        }
        
        if ($row === 1) {
            return $this->colorize(' ' . str_repeat('─', 23), ThemeColor::Border);
        }
        
        // Context notes
        $noteIndex = $row - 2;
        if (isset($this->contextNotes[$noteIndex])) {
            return ' ' . $this->colorize($this->contextNotes[$noteIndex], ThemeColor::Muted);
        }
        
        // Scroll info at bottom
        $rightDims = $this->layout->getRightPaneDimensions();
        if ($row === $rightDims['height'] - 3) {
            $scrollInfo = $this->mainPane->getScrollInfo();
            return $this->colorize(' ' . str_repeat('─', 23), ThemeColor::Border);
        }
        if ($row === $rightDims['height'] - 2) {
            $scrollInfo = $this->mainPane->getScrollInfo();
            $percent = $scrollInfo['percentage'];
            return $this->colorize(" Scroll: {$percent}%", ThemeColor::Muted);
        }
        if ($row === $rightDims['height'] - 1) {
            $scrollInfo = $this->mainPane->getScrollInfo();
            $line = $scrollInfo['offset'] + 1;
            $total = $scrollInfo['total'];
            return $this->colorize(" Line {$line}/{$total}", ThemeColor::Muted);
        }
        
        return '';
    }
    
    protected function drawHeader(): void
    {
        $title = '💮 Swarm Assistant';
        $model = 'GPT-4';
        $time = date('H:i:s');
        
        // Create status line with background
        $leftPart = $this->colorize(' ' . $title, ThemeColor::Header, 'bold');
        $middlePart = $this->colorize(' | ' . $model, ThemeColor::Success);
        $rightPart = $this->colorize(' | ' . $time . ' ', ThemeColor::Muted);
        
        $statusLength = mb_strlen($title) + mb_strlen($model) + mb_strlen($time) + 8;
        $padding = str_repeat(' ', $this->terminalWidth - $statusLength);
        
        // Use a subtle background color
        echo "\033[48;5;236m"; // Dark gray background
        echo $leftPart . $middlePart . $rightPart . $padding;
        echo AnsiColor::Reset->toEscapeCode() . "\n";
    }
    
    protected function drawFooter(): void
    {
        // Draw separator
        echo $this->colorize(str_repeat('─', $this->terminalWidth), ThemeColor::Border) . "\n";
        
        // Keyboard shortcuts
        $shortcuts = [
            'Tab' => 'Switch Pane',
            $this->modSymbol . 'B' => 'Toggle Sidebars',
            '↑↓' => 'Scroll',
            'PgUp/Dn' => 'Page',
            $this->modSymbol . 'Q' => 'Quit',
        ];
        
        $shortcutStr = '';
        foreach ($shortcuts as $key => $desc) {
            $shortcutStr .= $this->colorize($key, ThemeColor::Accent) . ' ' . 
                           $this->colorize($desc, ThemeColor::Muted) . '  ';
        }
        
        echo ' ' . $shortcutStr . "\n";
    }
    
    protected function padContent(string $content, int $width): string
    {
        $contentLength = mb_strlen(strip_tags($content));
        if ($contentLength >= $width) {
            return mb_substr($content, 0, $width);
        }
        
        return $content . str_repeat(' ', $width - $contentLength);
    }
    
    /**
     * Handle keyboard input for navigation
     */
    public function handleKeyboardInput(string $key): bool
    {
        switch ($key) {
            case "\t": // Tab
                $this->focusedPane = ($this->focusedPane + 1) % 3;
                return true;
                
            case "\033[A": // Up arrow
                if ($this->focusedPane === 1) {
                    $this->mainPane->scrollUp();
                    return true;
                }
                break;
                
            case "\033[B": // Down arrow
                if ($this->focusedPane === 1) {
                    $this->mainPane->scrollDown();
                    return true;
                }
                break;
                
            case "\033[5~": // Page Up
                if ($this->focusedPane === 1) {
                    $this->mainPane->pageUp();
                    return true;
                }
                break;
                
            case "\033[6~": // Page Down
                if ($this->focusedPane === 1) {
                    $this->mainPane->pageDown();
                    return true;
                }
                break;
                
            case "\033b": // Option+B (macOS) or Alt+B
            case "\002": // Ctrl+B
                $this->layout->toggleSidebars();
                $this->updatePaneDimensions();
                return true;
        }
        
        return false;
    }
    
    /**
     * Update pane dimensions after layout change
     */
    protected function updatePaneDimensions(): void
    {
        $mainDims = $this->layout->getMainAreaDimensions();
        $this->mainPane->updateViewport($mainDims['width'], $mainDims['height']);
        $this->conversationCacheDirty = true;
    }
    
    protected function detectTerminalSize(): void
    {
        parent::detectTerminalSize();
        
        // Update layout dimensions
        if (isset($this->layout)) {
            $this->layout->updateDimensions($this->terminalWidth, $this->terminalHeight);
            $this->updatePaneDimensions();
        }
    }
}