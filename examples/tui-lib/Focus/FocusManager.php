<?php

declare(strict_types=1);

namespace Examples\TuiLib\Focus;

/**
 * Direction for focus movement
 */
enum FocusDirection: string
{
    case Next = 'next';
    case Previous = 'previous';
    case Up = 'up';
    case Down = 'down';
    case Left = 'left';
    case Right = 'right';
}

/**
 * Focus traversal policy
 */
enum TraversalPolicy: string
{
    case ReadingOrder = 'reading_order';  // Left-to-right, top-to-bottom
    case TabOrder = 'tab_order';          // Explicit tab indices
    case TreeOrder = 'tree_order';        // Tree traversal order
}

/**
 * Central focus management system
 */
class FocusManager
{
    protected ?FocusNode $currentFocus = null;

    protected ?FocusNode $rootNode = null;

    protected TraversalPolicy $traversalPolicy = TraversalPolicy::TreeOrder;

    /** @var array<callable> */
    protected array $focusChangeListeners = [];

    public function __construct(?FocusNode $rootNode = null)
    {
        $this->rootNode = $rootNode;
    }

    public function setRootNode(FocusNode $rootNode): void
    {
        // Unfocus current if changing root
        if ($this->currentFocus !== null && $this->rootNode !== $rootNode) {
            $this->unfocus();
        }

        $this->rootNode = $rootNode;
    }

    public function getRootNode(): ?FocusNode
    {
        return $this->rootNode;
    }

    public function getCurrentFocus(): ?FocusNode
    {
        return $this->currentFocus;
    }

    public function getTraversalPolicy(): TraversalPolicy
    {
        return $this->traversalPolicy;
    }

    public function setTraversalPolicy(TraversalPolicy $policy): void
    {
        $this->traversalPolicy = $policy;
    }

    /**
     * Request focus for a specific node
     */
    public function requestFocus(FocusNode $node): bool
    {
        if (! $node->canRequestFocus()) {
            return false;
        }

        // Check if node is in our tree
        if ($this->rootNode !== null && ! $this->isNodeInTree($node)) {
            return false;
        }

        // Unfocus current node
        if ($this->currentFocus !== null && $this->currentFocus !== $node) {
            $this->currentFocus->setFocusInternal(false);
            $this->currentFocus->unfocus();
        }

        // Focus new node
        $previousFocus = $this->currentFocus;
        $this->currentFocus = $node;
        $node->setFocusInternal(true);

        // Notify listeners
        $this->dispatchFocusChangeEvent($previousFocus, $node);

        return true;
    }

    /**
     * Move focus to the next focusable node
     */
    public function nextFocus(): bool
    {
        if ($this->currentFocus === null) {
            return $this->focusFirst();
        }

        $next = $this->getNextFocusable($this->currentFocus);
        if ($next !== null) {
            return $this->requestFocus($next);
        }

        // Wrap around to first if at end
        return $this->focusFirst();
    }

    /**
     * Move focus to the previous focusable node
     */
    public function previousFocus(): bool
    {
        if ($this->currentFocus === null) {
            return $this->focusLast();
        }

        $previous = $this->getPreviousFocusable($this->currentFocus);
        if ($previous !== null) {
            return $this->requestFocus($previous);
        }

        // Wrap around to last if at beginning
        return $this->focusLast();
    }

    /**
     * Move focus in a specific direction
     */
    public function moveFocus(FocusDirection $direction): bool
    {
        return match ($direction) {
            FocusDirection::Next => $this->nextFocus(),
            FocusDirection::Previous => $this->previousFocus(),
            FocusDirection::Up => $this->moveFocusUp(),
            FocusDirection::Down => $this->moveFocusDown(),
            FocusDirection::Left => $this->moveFocusLeft(),
            FocusDirection::Right => $this->moveFocusRight(),
        };
    }

    /**
     * Focus the first focusable node
     */
    public function focusFirst(): bool
    {
        if ($this->rootNode === null) {
            return false;
        }

        $firstFocusable = $this->getFirstFocusable();
        if ($firstFocusable !== null) {
            return $this->requestFocus($firstFocusable);
        }

        return false;
    }

    /**
     * Focus the last focusable node
     */
    public function focusLast(): bool
    {
        if ($this->rootNode === null) {
            return false;
        }

        $lastFocusable = $this->getLastFocusable();
        if ($lastFocusable !== null) {
            return $this->requestFocus($lastFocusable);
        }

        return false;
    }

    /**
     * Remove focus from the current node
     */
    public function unfocus(): void
    {
        if ($this->currentFocus === null) {
            return;
        }

        $previousFocus = $this->currentFocus;
        $this->currentFocus->setFocusInternal(false);
        $this->currentFocus->unfocus();
        $this->currentFocus = null;

        // Notify listeners
        $this->dispatchFocusChangeEvent($previousFocus, null);
    }

    /**
     * Check if focus can move in the specified direction
     */
    public function canMoveFocus(FocusDirection $direction): bool
    {
        if ($this->currentFocus === null) {
            return $this->hasFocusableNodes();
        }

        return match ($direction) {
            FocusDirection::Next => $this->getNextFocusable($this->currentFocus) !== null,
            FocusDirection::Previous => $this->getPreviousFocusable($this->currentFocus) !== null,
            FocusDirection::Up => $this->getFocusableUp($this->currentFocus) !== null,
            FocusDirection::Down => $this->getFocusableDown($this->currentFocus) !== null,
            FocusDirection::Left => $this->getFocusableLeft($this->currentFocus) !== null,
            FocusDirection::Right => $this->getFocusableRight($this->currentFocus) !== null,
        };
    }

    /**
     * Add a focus change listener
     */
    public function addFocusChangeListener(callable $listener): void
    {
        $this->focusChangeListeners[] = $listener;
    }

    /**
     * Remove a focus change listener
     */
    public function removeFocusChangeListener(callable $listener): void
    {
        $this->focusChangeListeners = array_filter(
            $this->focusChangeListeners,
            fn ($l) => $l !== $listener
        );
    }

    /**
     * Get all focusable nodes in the tree
     *
     * @return array<FocusNode>
     */
    public function getFocusableNodes(): array
    {
        if ($this->rootNode === null) {
            return [];
        }

        return $this->rootNode->getFocusableNodes();
    }

    /**
     * Find a node by ID
     */
    public function findNodeById(string $id): ?FocusNode
    {
        if ($this->rootNode === null) {
            return null;
        }

        return $this->rootNode->findNodeById($id);
    }

    /**
     * Check if there are any focusable nodes
     */
    public function hasFocusableNodes(): bool
    {
        return count($this->getFocusableNodes()) > 0;
    }

    /**
     * Get the next focusable node based on traversal policy
     */
    protected function getNextFocusable(FocusNode $current): ?FocusNode
    {
        return match ($this->traversalPolicy) {
            TraversalPolicy::TreeOrder => $current->getNextFocusable(),
            TraversalPolicy::ReadingOrder => $this->getNextInReadingOrder($current),
            TraversalPolicy::TabOrder => $this->getNextInTabOrder($current),
        };
    }

    /**
     * Get the previous focusable node based on traversal policy
     */
    protected function getPreviousFocusable(FocusNode $current): ?FocusNode
    {
        return match ($this->traversalPolicy) {
            TraversalPolicy::TreeOrder => $current->getPreviousFocusable(),
            TraversalPolicy::ReadingOrder => $this->getPreviousInReadingOrder($current),
            TraversalPolicy::TabOrder => $this->getPreviousInTabOrder($current),
        };
    }

    /**
     * Get the first focusable node
     */
    protected function getFirstFocusable(): ?FocusNode
    {
        $focusableNodes = $this->getFocusableNodes();

        return $focusableNodes[0] ?? null;
    }

    /**
     * Get the last focusable node
     */
    protected function getLastFocusable(): ?FocusNode
    {
        $focusableNodes = $this->getFocusableNodes();

        return $focusableNodes[count($focusableNodes) - 1] ?? null;
    }

    /**
     * Move focus up (for directional navigation)
     */
    protected function moveFocusUp(): bool
    {
        if ($this->currentFocus === null) {
            return $this->focusFirst();
        }

        $upNode = $this->getFocusableUp($this->currentFocus);
        if ($upNode !== null) {
            return $this->requestFocus($upNode);
        }

        return false;
    }

    /**
     * Move focus down (for directional navigation)
     */
    protected function moveFocusDown(): bool
    {
        if ($this->currentFocus === null) {
            return $this->focusFirst();
        }

        $downNode = $this->getFocusableDown($this->currentFocus);
        if ($downNode !== null) {
            return $this->requestFocus($downNode);
        }

        return false;
    }

    /**
     * Move focus left (for directional navigation)
     */
    protected function moveFocusLeft(): bool
    {
        if ($this->currentFocus === null) {
            return $this->focusFirst();
        }

        $leftNode = $this->getFocusableLeft($this->currentFocus);
        if ($leftNode !== null) {
            return $this->requestFocus($leftNode);
        }

        return false;
    }

    /**
     * Move focus right (for directional navigation)
     */
    protected function moveFocusRight(): bool
    {
        if ($this->currentFocus === null) {
            return $this->focusFirst();
        }

        $rightNode = $this->getFocusableRight($this->currentFocus);
        if ($rightNode !== null) {
            return $this->requestFocus($rightNode);
        }

        return false;
    }

    /**
     * Get focusable node above current (spatial navigation)
     */
    protected function getFocusableUp(FocusNode $current): ?FocusNode
    {
        // This would need spatial/coordinate information to work properly
        // For now, fall back to previous node
        return $this->getPreviousFocusable($current);
    }

    /**
     * Get focusable node below current (spatial navigation)
     */
    protected function getFocusableDown(FocusNode $current): ?FocusNode
    {
        // This would need spatial/coordinate information to work properly
        // For now, fall back to next node
        return $this->getNextFocusable($current);
    }

    /**
     * Get focusable node to the left of current (spatial navigation)
     */
    protected function getFocusableLeft(FocusNode $current): ?FocusNode
    {
        // This would need spatial/coordinate information to work properly
        // For now, fall back to previous node
        return $this->getPreviousFocusable($current);
    }

    /**
     * Get focusable node to the right of current (spatial navigation)
     */
    protected function getFocusableRight(FocusNode $current): ?FocusNode
    {
        // This would need spatial/coordinate information to work properly
        // For now, fall back to next node
        return $this->getNextFocusable($current);
    }

    /**
     * Get next node in reading order (left-to-right, top-to-bottom)
     */
    protected function getNextInReadingOrder(FocusNode $current): ?FocusNode
    {
        // This would need spatial/coordinate information to implement properly
        // For now, fall back to tree order
        return $current->getNextFocusable();
    }

    /**
     * Get previous node in reading order
     */
    protected function getPreviousInReadingOrder(FocusNode $current): ?FocusNode
    {
        // This would need spatial/coordinate information to implement properly
        // For now, fall back to tree order
        return $current->getPreviousFocusable();
    }

    /**
     * Get next node in tab order
     */
    protected function getNextInTabOrder(FocusNode $current): ?FocusNode
    {
        // This would need tab index information to implement properly
        // For now, fall back to tree order
        return $current->getNextFocusable();
    }

    /**
     * Get previous node in tab order
     */
    protected function getPreviousInTabOrder(FocusNode $current): ?FocusNode
    {
        // This would need tab index information to implement properly
        // For now, fall back to tree order
        return $current->getPreviousFocusable();
    }

    /**
     * Check if a node is in the managed tree
     */
    protected function isNodeInTree(FocusNode $node): bool
    {
        if ($this->rootNode === null) {
            return false;
        }

        return $this->rootNode === $node || $this->rootNode->isAncestorOf($node);
    }

    /**
     * Dispatch focus change event to listeners
     */
    protected function dispatchFocusChangeEvent(?FocusNode $previousFocus, ?FocusNode $newFocus): void
    {
        foreach ($this->focusChangeListeners as $listener) {
            $listener($previousFocus, $newFocus);
        }
    }
}
