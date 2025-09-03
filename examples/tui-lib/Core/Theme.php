<?php

declare(strict_types=1);

namespace Examples\TuiLib\Core;

/**
 * Theme system for consistent styling across the TUI framework
 */
class Theme
{
    protected array $styles = [];

    public function __construct(array $styles = [])
    {
        $this->styles = array_merge($this->getDefaultStyles(), $styles);
    }

    /**
     * Get a style by name
     */
    public function get(string $name): ?Style
    {
        return $this->styles[$name] ?? null;
    }

    /**
     * Set a style
     */
    public function set(string $name, Style $style): void
    {
        $this->styles[$name] = $style;
    }

    /**
     * Apply a style to text by name
     */
    public function apply(string $styleName, string $text): string
    {
        $style = $this->get($styleName);
        if ($style === null) {
            return $text;
        }

        return $style->apply($text);
    }

    /**
     * Create styled text by name
     */
    public function text(string $styleName, string $text): StyledText
    {
        return new StyledText($text, $this->get($styleName));
    }

    /**
     * Create a dark theme
     */
    public static function dark(): self
    {
        return new self([
            'ui.header' => Style::bg(Color::BgDark)->withForeground(Color::BrightWhite),
            'ui.border' => Style::fg(Color::BrightBlack),
            'text.primary' => Style::fg(Color::BrightWhite),
            'text.secondary' => Style::fg(Color::White),
        ]);
    }

    /**
     * Create a light theme
     */
    public static function light(): self
    {
        return new self([
            'ui.header' => Style::bg(Color::BgWhite)->withForeground(Color::Black),
            'ui.border' => Style::fg(Color::Black),
            'text.primary' => Style::fg(Color::Black),
            'text.secondary' => Style::fg(Color::BrightBlack),
        ]);
    }

    /**
     * Swarm-specific theme
     */
    public static function swarm(): self
    {
        return new self([
            // Exact colors from Swarm UI
            'swarm.header' => Style::bg(Color::BgDark)->withForeground(Color::BrightWhite),
            'swarm.brand' => Style::fg(Color::BrightWhite)->withDecoration(TextDecoration::Bold),
            'swarm.status' => Style::fg(Color::Yellow),
            'swarm.separator' => Style::fg(Color::BrightBlack),
            'swarm.prompt.active' => Style::fg(Color::Blue)->withDecoration(TextDecoration::Bold),
            'swarm.prompt.inactive' => Style::decoration(TextDecoration::Dim),

            // Activity indicators (exact from FullTerminalUI)
            'swarm.activity.command' => Style::fg(Color::Blue),
            'swarm.activity.success' => Style::fg(Color::Green),
            'swarm.activity.tool' => Style::fg(Color::Cyan),
            'swarm.activity.error' => Style::fg(Color::Red),
            'swarm.activity.system' => Style::fg(Color::Yellow),
            'swarm.activity.info' => Style::fg(Color::Cyan),
            'swarm.activity.warning' => Style::fg(Color::Yellow),

            // Task status (exact from FullTerminalUI)
            'swarm.task.pending' => Style::fg(Color::BrightBlack),
            'swarm.task.running' => Style::fg(Color::Yellow),
            'swarm.task.completed' => Style::fg(Color::Green),
            'swarm.task.failed' => Style::fg(Color::Red),

            // Focus states
            'swarm.focus.active' => Style::fg(Color::BrightCyan)->withDecoration(TextDecoration::Bold),
            'swarm.focus.inactive' => Style::decoration(TextDecoration::Dim),
            'swarm.selected' => Style::decoration(TextDecoration::Reverse),

            // Section headers
            'swarm.section.header' => Style::decoration(TextDecoration::Bold),
            'swarm.section.active' => Style::fg(Color::BrightCyan)->withDecoration(TextDecoration::Bold),
        ]);
    }

    /**
     * Get default styles
     */
    protected function getDefaultStyles(): array
    {
        return [
            // Status indicators
            'status.command' => Style::fg(Color::Blue),
            'status.success' => Style::fg(Color::Green),
            'status.error' => Style::fg(Color::Red),
            'status.warning' => Style::fg(Color::Yellow),
            'status.info' => Style::fg(Color::Cyan),
            'status.muted' => Style::decoration(TextDecoration::Dim),

            // UI Elements
            'ui.header' => Style::bg(Color::BgDark)->withForeground(Color::White),
            'ui.border' => Style::fg(Color::BrightBlack),
            'ui.separator' => Style::fg(Color::BrightBlack),
            'ui.focus' => Style::fg(Color::BrightCyan)->withDecoration(TextDecoration::Bold),
            'ui.unfocus' => Style::decoration(TextDecoration::Dim),
            'ui.selected' => Style::decoration(TextDecoration::Reverse),
            'ui.prompt' => Style::fg(Color::Blue)->withDecoration(TextDecoration::Bold),

            // Text styles
            'text.bold' => Style::decoration(TextDecoration::Bold),
            'text.italic' => Style::decoration(TextDecoration::Italic),
            'text.underline' => Style::decoration(TextDecoration::Underline),
            'text.dim' => Style::decoration(TextDecoration::Dim),
            'text.reverse' => Style::decoration(TextDecoration::Reverse),

            // Activity log specific
            'activity.timestamp' => Style::decoration(TextDecoration::Dim),
            'activity.command' => Style::fg(Color::Blue),
            'activity.response' => Style::fg(Color::Green),
            'activity.tool' => Style::fg(Color::Cyan),
            'activity.error' => Style::fg(Color::Red),
            'activity.system' => Style::fg(Color::Yellow)->withDecoration(TextDecoration::Dim),
            'activity.thought' => Style::fg(Color::BrightBlack)->withDecoration(TextDecoration::Italic),

            // Task status
            'task.pending' => Style::fg(Color::BrightBlack),
            'task.running' => Style::fg(Color::Yellow),
            'task.completed' => Style::fg(Color::Green),
            'task.failed' => Style::fg(Color::Red),
            'task.number' => Style::decoration(TextDecoration::Dim),
            'task.progress' => Style::decoration(TextDecoration::Dim),

            // Context styles
            'context.directory' => Style::fg(Color::Cyan),
            'context.file' => Style::fg(Color::Yellow),
            'context.note' => Style::fg(Color::Magenta),
            'context.tool' => Style::fg(Color::Green),

            // Swarm specific
            'swarm.brand' => Style::fg(Color::White)->withDecoration(TextDecoration::Bold),
            'swarm.status' => Style::fg(Color::Yellow),
            'swarm.active' => Style::fg(Color::BrightCyan)->withDecoration(TextDecoration::Bold),

            // Header styles
            'header.app' => Style::fg(Color::BrightWhite),
            'header.separator' => Style::decoration(TextDecoration::Dim),
            'header.status' => Style::fg(Color::Yellow),
            'header.progress' => Style::fg(Color::BrightBlack),
            'header.background' => Style::bg(Color::BgDark),

            // Prompt styles
            'prompt.active' => Style::fg(Color::Blue),
            'prompt.inactive' => Style::decoration(TextDecoration::Dim),
            'input.inactive' => Style::decoration(TextDecoration::Dim),

            // History styles
            'history.timestamp' => Style::decoration(TextDecoration::Dim),
            'history.command' => Style::fg(Color::Blue),
            'history.status' => Style::fg(Color::Green),
            'history.tool' => Style::fg(Color::Cyan),
            'history.activity' => Style::fg(Color::Cyan),
            'history.system' => Style::fg(Color::Yellow),
            'history.assistant' => Style::fg(Color::Green),
            'history.error' => Style::fg(Color::Red),
            'history.thought' => Style::fg(Color::BrightBlack)->withDecoration(TextDecoration::Italic),
            'history.expand' => Style::decoration(TextDecoration::Dim),

            // UI styles
            'ui.muted' => Style::decoration(TextDecoration::Dim),
        ];
    }
}

/**
 * Icon registry for consistent icons across the UI
 */
class Icons
{
    protected static array $icons = [
        // Status icons
        'success' => '✓',
        'error' => '✗',
        'warning' => '⚠',
        'info' => 'ℹ',
        'pending' => '○',
        'running' => '▶',
        'completed' => '✓',
        'failed' => '✗',
        'loading' => '◐',

        // Activity icons
        'command' => '$',
        'response' => '●',
        'tool' => '🔧',
        'activity' => '⚡',
        'system' => '!',
        'thought' => '💭',

        // Navigation icons
        'arrow_up' => '▲',
        'arrow_down' => '▼',
        'arrow_left' => '◀',
        'arrow_right' => '▶',
        'expand' => '+',
        'collapse' => '-',

        // UI elements
        'separator_v' => '│',
        'separator_h' => '─',
        'separator_right' => '┤',
        'horizontal' => '─',
        'corner_tl' => '┌',
        'corner_tr' => '┐',
        'corner_bl' => '└',
        'corner_br' => '┘',
        'bullet' => '•',
        'ellipsis' => '…',

        // Extended activity icons
        'prompt' => '$',
        'assistant' => '●',
        'chevron_right' => '>',

        // Swarm specific
        'swarm' => '💮',
    ];

    /**
     * Get an icon by name
     */
    public static function get(string $name): string
    {
        return self::$icons[$name] ?? '';
    }

    /**
     * Set an icon
     */
    public static function set(string $name, string $icon): void
    {
        self::$icons[$name] = $icon;
    }

    /**
     * Create styled icon
     */
    public static function styled(string $name, Style $style): StyledText
    {
        return new StyledText(self::get($name), $style);
    }
}
