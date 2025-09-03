<?php

declare(strict_types=1);

namespace Examples\TuiLib\Focus;

/**
 * Individual focus node that can participate in focus management
 */
class FocusNode
{
    protected bool $hasFocus = false;

    protected bool $canRequestFocus = true;

    protected ?FocusNode $parent = null;

    /** @var array<FocusNode> */
    protected array $children = [];

    /** @var array<callable> */
    protected array $focusListeners = [];

    /** @var array<callable> */
    protected array $unfocusListeners = [];

    public function __construct(
        protected readonly string $id,
        bool $canRequestFocus = true,
    ) {
        $this->canRequestFocus = $canRequestFocus;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function hasFocus(): bool
    {
        return $this->hasFocus;
    }

    public function canRequestFocus(): bool
    {
        return $this->canRequestFocus;
    }

    public function setCanRequestFocus(bool $canRequestFocus): void
    {
        $this->canRequestFocus = $canRequestFocus;
    }

    public function getParent(): ?FocusNode
    {
        return $this->parent;
    }

    /** @return array<FocusNode> */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Request focus for this node
     */
    public function requestFocus(): bool
    {
        if (! $this->canRequestFocus) {
            return false;
        }

        // If already focused, nothing to do
        if ($this->hasFocus) {
            return true;
        }

        // Get the focus manager through the tree
        $manager = $this->getFocusManager();
        if ($manager === null) {
            return false;
        }

        return $manager->requestFocus($this);
    }

    /**
     * Remove focus from this node
     */
    public function unfocus(): void
    {
        if (! $this->hasFocus) {
            return;
        }

        $this->setFocusInternal(false);
        $this->dispatchUnfocusEvent();
    }

    /**
     * Add a focus change listener
     */
    public function addFocusListener(callable $listener): void
    {
        $this->focusListeners[] = $listener;
    }

    /**
     * Add an unfocus listener
     */
    public function addUnfocusListener(callable $listener): void
    {
        $this->unfocusListeners[] = $listener;
    }

    /**
     * Remove a focus listener
     */
    public function removeFocusListener(callable $listener): void
    {
        $this->focusListeners = array_filter(
            $this->focusListeners,
            fn ($l) => $l !== $listener
        );
    }

    /**
     * Remove an unfocus listener
     */
    public function removeUnfocusListener(callable $listener): void
    {
        $this->unfocusListeners = array_filter(
            $this->unfocusListeners,
            fn ($l) => $l !== $listener
        );
    }

    /**
     * Add a child node
     */
    public function addChild(FocusNode $child): void
    {
        if ($child->parent !== null) {
            $child->parent->removeChild($child);
        }

        $this->children[] = $child;
        $child->parent = $this;
    }

    /**
     * Remove a child node
     */
    public function removeChild(FocusNode $child): void
    {
        $this->children = array_filter(
            $this->children,
            fn ($c) => $c !== $child
        );
        $child->parent = null;
    }

    /**
     * Get the next focusable node in the tree traversal order
     */
    public function getNextFocusable(): ?FocusNode
    {
        // Check children first (depth-first)
        foreach ($this->children as $child) {
            if ($child->canRequestFocus()) {
                return $child;
            }

            $descendant = $child->getNextFocusable();
            if ($descendant !== null) {
                return $descendant;
            }
        }

        // If no focusable children, check siblings
        if ($this->parent !== null) {
            $siblings = $this->parent->getChildren();
            $selfIndex = array_search($this, $siblings, true);

            if ($selfIndex !== false) {
                for ($i = $selfIndex + 1; $i < count($siblings); $i++) {
                    $sibling = $siblings[$i];
                    if ($sibling->canRequestFocus()) {
                        return $sibling;
                    }

                    $descendant = $sibling->getNextFocusable();
                    if ($descendant !== null) {
                        return $descendant;
                    }
                }
            }

            // Continue with parent's next
            return $this->parent->getNextFocusable();
        }

        return null;
    }

    /**
     * Get the previous focusable node in the tree traversal order
     */
    public function getPreviousFocusable(): ?FocusNode
    {
        if ($this->parent === null) {
            return null;
        }

        $siblings = $this->parent->getChildren();
        $selfIndex = array_search($this, $siblings, true);

        if ($selfIndex === false) {
            return null;
        }

        // Check previous siblings (in reverse order)
        for ($i = $selfIndex - 1; $i >= 0; $i--) {
            $sibling = $siblings[$i];

            // Get the last focusable descendant of this sibling
            $lastDescendant = $sibling->getLastFocusableDescendant();
            if ($lastDescendant !== null) {
                return $lastDescendant;
            }

            if ($sibling->canRequestFocus()) {
                return $sibling;
            }
        }

        // If no previous siblings, return parent if it's focusable
        if ($this->parent->canRequestFocus()) {
            return $this->parent;
        }

        // Continue with parent's previous
        return $this->parent->getPreviousFocusable();
    }

    /**
     * Internal method to set focus state - should only be called by FocusManager
     */
    public function setFocusInternal(bool $focused): void
    {
        if ($this->hasFocus === $focused) {
            return;
        }

        $this->hasFocus = $focused;

        if ($focused) {
            $this->dispatchFocusEvent();
        }
    }

    /**
     * Get all focusable nodes in this subtree
     *
     * @return array<FocusNode>
     */
    public function getFocusableNodes(): array
    {
        $nodes = [];

        if ($this->canRequestFocus()) {
            $nodes[] = $this;
        }

        foreach ($this->children as $child) {
            $nodes = [...$nodes, ...$child->getFocusableNodes()];
        }

        return $nodes;
    }

    /**
     * Find a node by ID in this subtree
     */
    public function findNodeById(string $id): ?FocusNode
    {
        if ($this->id === $id) {
            return $this;
        }

        foreach ($this->children as $child) {
            $found = $child->findNodeById($id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Check if this node is an ancestor of the given node
     */
    public function isAncestorOf(FocusNode $node): bool
    {
        $current = $node->parent;

        while ($current !== null) {
            if ($current === $this) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    /**
     * Check if this node is a descendant of the given node
     */
    public function isDescendantOf(FocusNode $node): bool
    {
        return $node->isAncestorOf($this);
    }

    /**
     * Get the last focusable descendant in this subtree
     */
    protected function getLastFocusableDescendant(): ?FocusNode
    {
        // Check children in reverse order
        for ($i = count($this->children) - 1; $i >= 0; $i--) {
            $child = $this->children[$i];

            $descendant = $child->getLastFocusableDescendant();
            if ($descendant !== null) {
                return $descendant;
            }

            if ($child->canRequestFocus()) {
                return $child;
            }
        }

        return $this->canRequestFocus() ? $this : null;
    }

    /**
     * Find the focus manager by traversing up the tree
     */
    protected function getFocusManager(): ?FocusManager
    {
        $current = $this;

        while ($current !== null) {
            if ($current instanceof FocusScope && $current->getFocusManager() !== null) {
                return $current->getFocusManager();
            }
            $current = $current->parent;
        }

        return null;
    }

    /**
     * Dispatch focus gained event
     */
    protected function dispatchFocusEvent(): void
    {
        foreach ($this->focusListeners as $listener) {
            $listener($this);
        }
    }

    /**
     * Dispatch focus lost event
     */
    protected function dispatchUnfocusEvent(): void
    {
        foreach ($this->unfocusListeners as $listener) {
            $listener($this);
        }
    }
}
