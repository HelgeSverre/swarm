<?php

declare(strict_types=1);

namespace Examples\TuiLib\SwarmMock;

use Examples\TuiLib\Core\BuildContext;
use Examples\TuiLib\Core\Constraints;
use Examples\TuiLib\Core\Icons;
use Examples\TuiLib\Core\Rect;
use Examples\TuiLib\Core\Size;
use Examples\TuiLib\Core\TextMeasurement;
use Examples\TuiLib\Core\Theme;
use Examples\TuiLib\Core\Widget;

/**
 * SwarmActivityLog widget - Exact replica of FullTerminalUI history rendering
 *
 * This component replicates the exact logic from FullTerminalUI::renderMainArea()
 * and FullTerminalUI::renderHistoryEntry() to test and fix text wrapping issues.
 */
class SwarmActivityLog extends Widget
{
    protected array $history = [];

    protected array $activityFeed = [];

    protected array $expandedThoughts = [];

    protected bool $isFocused = false;

    protected string $modSymbol = '⌥'; // macOS default, could be detected

    protected Theme $theme;

    public function __construct(string $id = 'swarm_activity_log')
    {
        parent::__construct($id);
        $this->theme = Theme::swarm();
    }

    /**
     * Set the conversation history
     */
    public function setHistory(array $history): void
    {
        $this->history = $history;
        $this->markNeedsRepaint();
    }

    /**
     * Set the activity feed (tool calls, etc.)
     */
    public function setActivityFeed(array $activityFeed): void
    {
        $this->activityFeed = $activityFeed;
        $this->markNeedsRepaint();
    }

    /**
     * Add a single history entry
     */
    public function addHistoryEntry(array $entry): void
    {
        $this->history[] = $entry;

        // Keep history from growing too large (matches FullTerminalUI)
        if (count($this->history) > 100) {
            array_shift($this->history);
        }

        $this->markNeedsRepaint();
    }

    /**
     * Set focus state
     */
    public function setFocused(bool $focused): void
    {
        $this->isFocused = $focused;
        $this->markNeedsRepaint();
    }

    public function build(BuildContext $context): Widget
    {
        return $this;
    }

    public function measure(Constraints $constraints): Size
    {
        return new Size($constraints->maxWidth, $constraints->maxHeight);
    }

    public function layout(Rect $bounds): void
    {
        $this->setBounds($bounds);
        $this->clearLayoutFlag();
    }

    public function paint(BuildContext $context): string
    {
        if ($this->bounds === null) {
            return '';
        }

        $this->renderToCanvas($context->canvas);

        $this->clearRepaintFlag();

        return '';
    }

    /**
     * Toggle thought expansion (matches FullTerminalUI logic)
     */
    public function toggleNearestThought(): bool
    {
        $recentHistory = array_slice($this->history, -20);
        foreach (array_reverse($recentHistory) as $entry) {
            if ($entry['type'] === 'assistant' && isset($entry['thought'])) {
                $thoughtId = md5($entry['time'] . $entry['thought']);
                $thoughtLines = TextMeasurement::wordWrap($entry['thought'], $this->bounds->width - 15);

                if (count($thoughtLines) > 4) {
                    if (in_array($thoughtId, $this->expandedThoughts)) {
                        $this->expandedThoughts = array_diff($this->expandedThoughts, [$thoughtId]);
                    } else {
                        $this->expandedThoughts[] = $thoughtId;
                    }

                    $this->markNeedsRepaint();

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Handle key events
     */
    public function handleKeyEvent(string $key): bool
    {
        // Handle thought expansion
        if ($key === 'r' || $key === 'R') {
            return $this->toggleNearestThought();
        }

        // Handle clearing log
        if ($key === 'c' || $key === 'C') {
            $this->history = [];
            $this->activityFeed = [];
            $this->markNeedsRepaint();

            return true;
        }

        return false;
    }

    /**
     * Render to canvas instead of direct terminal output
     */
    protected function renderToCanvas(?\Examples\TuiLib\Core\Canvas $canvas): void
    {
        if (! $canvas || ! $this->bounds) {
            return;
        }

        $maxWidth = $this->bounds->width - 2; // Account for margins
        $availableLines = $this->bounds->height - 2;

        // Merge activity feed and history, prioritizing activity feed
        $allEntries = [];

        // Add activity feed entries (they have a getMessage method)
        foreach ($this->activityFeed as $activity) {
            if (method_exists($activity, 'getMessage')) {
                $allEntries[] = [
                    'time' => $activity->timestamp ?? time(),
                    'type' => 'tool_activity',
                    'content' => $activity->getMessage(),
                    'activity_object' => $activity,
                ];
            }
        }

        // Add history entries
        foreach ($this->history as $entry) {
            if ($entry['type'] !== 'tool') { // Skip old tool entries, we use activity feed now
                $allEntries[] = $entry;
            }
        }

        // Sort by time (oldest first) and get recent ones
        usort($allEntries, fn ($a, $b) => ($a['time'] ?? 0) - ($b['time'] ?? 0));

        // Get the most recent entries but maintain chronological order
        $totalEntries = count($allEntries);
        $startIndex = max(0, $totalEntries - $availableLines);
        $recentEntries = array_slice($allEntries, $startIndex, $availableLines);

        $currentRow = $this->bounds->y;
        foreach ($recentEntries as $entry) {
            if ($currentRow >= $this->bounds->y + $this->bounds->height - 1) {
                break;
            }

            $rowsUsed = $this->renderHistoryEntryToCanvas($canvas, $entry, $maxWidth, $currentRow);
            $currentRow += $rowsUsed;
        }
    }

    /**
     * Canvas-based version of renderHistoryEntry()
     */
    protected function renderHistoryEntryToCanvas(\Examples\TuiLib\Core\Canvas $canvas, array $entry, int $maxWidth, int $currentRow): int
    {
        $output = '';
        $time = date('H:i:s', $entry['time'] ?? time());
        $prefix = $this->theme->apply('history.timestamp', "[{$time}]") . ' ';
        $prefixLen = 11; // Length of "[HH:MM:SS] "

        // Calculate actual content width (account for prefix and type indicator)
        // maxWidth is the total width available, we need to subtract prefix and type indicator
        $typeIndicatorLen = 2; // Most indicators are 1 char + space
        $contentWidth = $maxWidth - $prefixLen - $typeIndicatorLen - 2; // Extra margin for safety

        // Ensure content width is positive
        if ($contentWidth < 10) {
            $contentWidth = 10; // Minimum width for content
        }

        $rowsUsed = 0;

        // Prepare the content based on type
        $typeIndicator = '';
        $content = $entry['content'] ?? '';
        $formatting = '';
        $formattingEnd = '';

        switch ($entry['type']) {
            case 'command':
                $typeIndicator = $this->theme->apply('history.command', Icons::get('prompt')) . ' ';
                break;
            case 'status':
                $typeIndicator = $this->theme->apply('history.status', Icons::get('success')) . ' ';
                break;
            case 'tool_activity':
                $typeIndicator = $this->theme->apply('history.tool', Icons::get('tool')) . ' ';
                break;
            case 'activity':
                $typeIndicator = $this->theme->apply('history.activity', Icons::get('activity')) . ' ';
                break;
            case 'tool':
                $typeIndicator = $this->theme->apply('history.tool', Icons::get('chevron_right')) . ' ';
                $content = "{$entry['tool']} {$entry['params']}";
                break;
            case 'system':
                $typeIndicator = $this->theme->apply('history.system', Icons::get('warning')) . ' ';
                $formatting = 'system';
                break;
            case 'assistant':
                $typeIndicator = $this->theme->apply('history.assistant', Icons::get('assistant')) . ' ';
                break;
            case 'error':
                $typeIndicator = $this->theme->apply('history.error', Icons::get('error')) . ' ';
                $formatting = 'error';
                break;
            default:
                $typeIndicator = '• ';
        }

        // Wrap the content to fit the available width using improved text measurement
        $wrappedLines = TextMeasurement::wordWrap($content, $contentWidth);

        // Render the first line with prefix and type indicator
        if (! empty($wrappedLines)) {
            // Build the first line with proper styling
            $content = $wrappedLines[0];
            if ($formatting) {
                $content = $this->theme->apply('history.' . $formatting, $content);
            }
            $firstLineStyled = new \Examples\TuiLib\Core\StyledText($prefix, $this->theme->get('history.timestamp'));
            $typeStyled = new \Examples\TuiLib\Core\StyledText($typeIndicator, null);
            $contentStyled = new \Examples\TuiLib\Core\StyledText($content, $formatting ? $this->theme->get('history.' . $formatting) : null);

            // Draw each part separately to preserve styling
            $x = $this->bounds->x + 2;
            $canvas->drawText($x, $currentRow, $firstLineStyled->render());
            $x += TextMeasurement::width($firstLineStyled->getText());

            $canvas->drawText($x, $currentRow, $typeStyled->render());
            $x += TextMeasurement::width($typeStyled->getText());

            $canvas->drawText($x, $currentRow, $contentStyled->render());

            $rowsUsed = 1;

            // Render additional wrapped lines with proper indentation
            $indentSpace = str_repeat(' ', $prefixLen + $typeIndicatorLen);
            for ($i = 1; $i < count($wrappedLines); $i++) {
                // Build the continuation line with proper styling
                $content = $wrappedLines[$i];
                if ($formatting) {
                    $content = $this->theme->apply('history.' . $formatting, $content);
                }
                $continuationStyled = new \Examples\TuiLib\Core\StyledText($indentSpace . $content, $formatting ? $this->theme->get('history.' . $formatting) : null);

                $canvas->drawText($this->bounds->x + 2, $currentRow + $rowsUsed, $continuationStyled->render());
                $rowsUsed++;
            }
        } else {
            // If no content, just show the prefix and type indicator
            $emptyStyled = new \Examples\TuiLib\Core\StyledText($prefix . $typeIndicator, $this->theme->get('history.timestamp'));
            $canvas->drawText($this->bounds->x + 2, $currentRow, $emptyStyled->render());
            $rowsUsed = 1;
        }

        // Special handling for assistant thoughts (if present)
        if ($entry['type'] === 'assistant' && isset($entry['thought']) && ! empty($entry['thought'])) {
            $thoughtId = md5($entry['time'] . $entry['thought']);
            $isExpanded = in_array($thoughtId, $this->expandedThoughts);
            $thoughtLines = TextMeasurement::wordWrap($entry['thought'], $contentWidth - 2);

            $thoughtIndent = str_repeat(' ', $prefixLen + $typeIndicatorLen);

            if (count($thoughtLines) > 4 && ! $isExpanded) {
                // Show collapsed version
                for ($i = 0; $i < min(3, count($thoughtLines)); $i++) {
                    $thoughtStyled = new \Examples\TuiLib\Core\StyledText($thoughtIndent . $thoughtLines[$i], $this->theme->get('history.thought'));
                    $canvas->drawText($this->bounds->x + 2, $currentRow + $rowsUsed, $thoughtStyled->render());
                    $rowsUsed++;
                }

                $remainingLines = count($thoughtLines) - 3;
                $expandStyled = new \Examples\TuiLib\Core\StyledText($thoughtIndent . "... +{$remainingLines} more lines ({$this->modSymbol}R to expand)", $this->theme->get('history.expand'));
                $canvas->drawText($this->bounds->x + 2, $currentRow + $rowsUsed, $expandStyled->render());
                $rowsUsed++;
            } else {
                // Show all thought lines
                foreach ($thoughtLines as $line) {
                    $thoughtStyled = new \Examples\TuiLib\Core\StyledText($thoughtIndent . $line, $this->theme->get('history.thought'));
                    $canvas->drawText($this->bounds->x + 2, $currentRow + $rowsUsed, $thoughtStyled->render());
                    $rowsUsed++;
                }

                if (count($thoughtLines) > 4) {
                    $collapseStyled = new \Examples\TuiLib\Core\StyledText($thoughtIndent . "({$this->modSymbol}R to collapse)", $this->theme->get('history.expand'));
                    $canvas->drawText($this->bounds->x + 2, $currentRow + $rowsUsed, $collapseStyled->render());
                    $rowsUsed++;
                }
            }
        }

        return $rowsUsed;
    }
}
