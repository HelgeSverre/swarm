<?php

declare(strict_types=1);

namespace Examples\TuiLib\Core;

/**
 * Abstract base class for all widgets in the TUI framework
 */
abstract class Widget
{
    protected string $id;

    protected bool $focusable = false;

    protected bool $visible = true;

    protected ?Widget $parent = null;

    protected array $children = [];

    protected ?Rect $bounds = null;

    protected bool $needsRebuild = true;

    protected bool $needsLayout = true;

    protected bool $needsRepaint = true;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? $this->generateId();
    }

    public function __toString(): string
    {
        return static::class . "(id: {$this->id})";
    }

    /**
     * Build the widget tree structure
     */
    abstract public function build(BuildContext $context): Widget;

    /**
     * Measure the widget's desired size given constraints
     */
    abstract public function measure(Constraints $constraints): Size;

    /**
     * Position child widgets within the allocated bounds
     */
    abstract public function layout(Rect $bounds): void;

    /**
     * Paint the widget to the terminal
     */
    abstract public function paint(BuildContext $context): string;

    // Property accessors
    public function getId(): string
    {
        return $this->id;
    }

    public function isFocusable(): bool
    {
        return $this->focusable;
    }

    public function setFocusable(bool $focusable): void
    {
        $this->focusable = $focusable;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        if ($this->visible !== $visible) {
            $this->visible = $visible;
            $this->markNeedsLayout();
        }
    }

    public function getParent(): ?Widget
    {
        return $this->parent;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getBounds(): ?Rect
    {
        return $this->bounds;
    }

    // Child management
    public function addChild(Widget $child): void
    {
        if (! in_array($child, $this->children, true)) {
            $this->children[] = $child;
            $child->setParent($this);
            $this->markNeedsLayout();
        }
    }

    public function removeChild(Widget $child): void
    {
        $index = array_search($child, $this->children, true);
        if ($index !== false) {
            array_splice($this->children, $index, 1);
            $child->setParent(null);
            $this->markNeedsLayout();
        }
    }

    public function removeAllChildren(): void
    {
        foreach ($this->children as $child) {
            $child->setParent(null);
        }
        $this->children = [];
        $this->markNeedsLayout();
    }

    public function hasChild(Widget $child): bool
    {
        return in_array($child, $this->children, true);
    }

    public function getChildCount(): int
    {
        return count($this->children);
    }

    public function getChildAt(int $index): ?Widget
    {
        return $this->children[$index] ?? null;
    }

    public function findChild(string $id): ?Widget
    {
        foreach ($this->children as $child) {
            if ($child->getId() === $id) {
                return $child;
            }
            $found = $child->findChild($id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    // Event handling
    public function handleKeyEvent(string $key): bool
    {
        // Default implementation - override in subclasses
        return false;
    }

    public function handleFocusChange(bool $focused): void
    {
        // Default implementation - override in subclasses
        $this->markNeedsRepaint();
    }

    public function handleMouseEvent(int $x, int $y, string $event): bool
    {
        // Default implementation - override in subclasses
        return false;
    }

    // Lifecycle management
    public function markNeedsRebuild(): void
    {
        $this->needsRebuild = true;
        $this->markNeedsLayout();
    }

    public function markNeedsLayout(): void
    {
        $this->needsLayout = true;
        $this->markNeedsRepaint();
        $this->parent?->markNeedsLayout();
    }

    public function markNeedsRepaint(): void
    {
        $this->needsRepaint = true;
        $this->parent?->markNeedsRepaint();
    }

    public function needsRebuild(): bool
    {
        return $this->needsRebuild;
    }

    public function needsLayout(): bool
    {
        return $this->needsLayout;
    }

    public function needsRepaint(): bool
    {
        return $this->needsRepaint;
    }

    // Utility methods
    public function isAncestorOf(Widget $widget): bool
    {
        $current = $widget->getParent();
        while ($current !== null) {
            if ($current === $this) {
                return true;
            }
            $current = $current->getParent();
        }

        return false;
    }

    public function isDescendantOf(Widget $widget): bool
    {
        return $widget->isAncestorOf($this);
    }

    public function getRoot(): Widget
    {
        $root = $this;
        while ($root->getParent() !== null) {
            $root = $root->getParent();
        }

        return $root;
    }

    public function getDepth(): int
    {
        $depth = 0;
        $current = $this->parent;
        while ($current !== null) {
            $depth++;
            $current = $current->getParent();
        }

        return $depth;
    }

    public function visitChildren(callable $visitor): void
    {
        foreach ($this->children as $child) {
            $visitor($child);
            $child->visitChildren($visitor);
        }
    }

    public function findChildrenByType(string $className): array
    {
        $result = [];
        $this->visitChildren(function (Widget $child) use ($className, &$result) {
            if ($child instanceof $className) {
                $result[] = $child;
            }
        });

        return $result;
    }

    // Debug helpers
    public function dumpTree(int $indent = 0): string
    {
        $prefix = str_repeat('  ', $indent);
        $info = $prefix . static::class . " (id: {$this->id})";

        if ($this->bounds !== null) {
            $info .= " [{$this->bounds->x},{$this->bounds->y} {$this->bounds->width}x{$this->bounds->height}]";
        }

        $info .= "\n";

        foreach ($this->children as $child) {
            $info .= $child->dumpTree($indent + 1);
        }

        return $info;
    }

    protected function setParent(?Widget $parent): void
    {
        $this->parent = $parent;
    }

    protected function setBounds(Rect $bounds): void
    {
        $this->bounds = $bounds;
    }

    protected function clearRebuildFlag(): void
    {
        $this->needsRebuild = false;
    }

    protected function clearLayoutFlag(): void
    {
        $this->needsLayout = false;
    }

    protected function clearRepaintFlag(): void
    {
        $this->needsRepaint = false;
    }

    protected function generateId(): string
    {
        return uniqid(static::class . '_', true);
    }
}
