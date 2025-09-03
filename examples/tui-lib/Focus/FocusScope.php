<?php

declare(strict_types=1);

namespace Examples\TuiLib\Focus;

/**
 * Widget that creates a focus scope and manages focus within a subtree
 */
class FocusScope extends FocusNode
{
    protected ?FocusManager $focusManager = null;

    protected bool $autofocus = false;

    protected bool $trapFocus = false;

    protected ?string $autofocusTargetId = null;

    public function __construct(
        string $id,
        bool $autofocus = false,
        bool $trapFocus = false,
        ?string $autofocusTargetId = null,
    ) {
        parent::__construct($id);
        $this->autofocus = $autofocus;
        $this->trapFocus = $trapFocus;
        $this->autofocusTargetId = $autofocusTargetId;
        $this->focusManager = new FocusManager($this);
    }

    public function getFocusManager(): ?FocusManager
    {
        return $this->focusManager;
    }

    public function setFocusManager(?FocusManager $manager): void
    {
        $this->focusManager = $manager;
        if ($manager !== null) {
            $manager->setRootNode($this);
        }
    }

    public function isAutofocus(): bool
    {
        return $this->autofocus;
    }

    public function setAutofocus(bool $autofocus): void
    {
        $this->autofocus = $autofocus;

        if ($autofocus) {
            $this->performAutofocus();
        }
    }

    public function isTrapFocus(): bool
    {
        return $this->trapFocus;
    }

    public function setTrapFocus(bool $trapFocus): void
    {
        $this->trapFocus = $trapFocus;
    }

    public function getAutofocusTargetId(): ?string
    {
        return $this->autofocusTargetId;
    }

    public function setAutofocusTargetId(?string $targetId): void
    {
        $this->autofocusTargetId = $targetId;
    }

    /**
     * Override addChild to perform autofocus when children are added
     */
    public function addChild(FocusNode $child): void
    {
        parent::addChild($child);

        if ($this->autofocus && $this->focusManager !== null && $this->focusManager->getCurrentFocus() === null) {
            $this->performAutofocus();
        }
    }

    /**
     * Request focus within this scope
     */
    public function requestFocusInScope(FocusNode $node): bool
    {
        if ($this->focusManager === null) {
            return false;
        }

        // Check if node is within this scope
        if (! $this->isAncestorOf($node) && $node !== $this) {
            return false;
        }

        return $this->focusManager->requestFocus($node);
    }

    /**
     * Move focus to next focusable within this scope
     */
    public function nextFocusInScope(): bool
    {
        if ($this->focusManager === null) {
            return false;
        }

        $currentFocus = $this->focusManager->getCurrentFocus();

        // If trap focus is enabled, prevent moving outside scope
        if ($this->trapFocus && $currentFocus !== null) {
            $next = $this->getNextFocusableInScope($currentFocus);
            if ($next !== null) {
                return $this->focusManager->requestFocus($next);
            }

            // Wrap around to first in scope
            return $this->focusFirstInScope();
        }

        return $this->focusManager->nextFocus();
    }

    /**
     * Move focus to previous focusable within this scope
     */
    public function previousFocusInScope(): bool
    {
        if ($this->focusManager === null) {
            return false;
        }

        $currentFocus = $this->focusManager->getCurrentFocus();

        // If trap focus is enabled, prevent moving outside scope
        if ($this->trapFocus && $currentFocus !== null) {
            $previous = $this->getPreviousFocusableInScope($currentFocus);
            if ($previous !== null) {
                return $this->focusManager->requestFocus($previous);
            }

            // Wrap around to last in scope
            return $this->focusLastInScope();
        }

        return $this->focusManager->previousFocus();
    }

    /**
     * Focus the first focusable node in this scope
     */
    public function focusFirstInScope(): bool
    {
        if ($this->focusManager === null) {
            return false;
        }

        $focusableNodes = $this->getFocusableNodesInScope();
        if (count($focusableNodes) > 0) {
            return $this->focusManager->requestFocus($focusableNodes[0]);
        }

        return false;
    }

    /**
     * Focus the last focusable node in this scope
     */
    public function focusLastInScope(): bool
    {
        if ($this->focusManager === null) {
            return false;
        }

        $focusableNodes = $this->getFocusableNodesInScope();
        if (count($focusableNodes) > 0) {
            return $this->focusManager->requestFocus($focusableNodes[count($focusableNodes) - 1]);
        }

        return false;
    }

    /**
     * Clear focus within this scope
     */
    public function clearFocusInScope(): void
    {
        if ($this->focusManager !== null) {
            $this->focusManager->unfocus();
        }
    }

    /**
     * Check if focus is currently within this scope
     */
    public function hasFocusInScope(): bool
    {
        if ($this->focusManager === null) {
            return false;
        }

        $currentFocus = $this->focusManager->getCurrentFocus();
        if ($currentFocus === null) {
            return false;
        }

        return $currentFocus === $this || $this->isAncestorOf($currentFocus);
    }

    /**
     * Get all focusable nodes within this scope only
     *
     * @return array<FocusNode>
     */
    public function getFocusableNodesInScope(): array
    {
        return $this->getFocusableNodes();
    }

    /**
     * Perform autofocus when the scope becomes active
     */
    public function performAutofocus(): bool
    {
        if (! $this->autofocus || $this->focusManager === null) {
            return false;
        }

        // If specific target is specified, try to focus it
        if ($this->autofocusTargetId !== null) {
            $targetNode = $this->findNodeById($this->autofocusTargetId);
            if ($targetNode !== null && $targetNode->canRequestFocus()) {
                return $this->focusManager->requestFocus($targetNode);
            }
        }

        // Otherwise, focus the first focusable node
        return $this->focusFirstInScope();
    }

    /**
     * Handle entering the scope (when this scope gains focus from outside)
     */
    public function onScopeEnter(): void
    {
        if ($this->autofocus) {
            $this->performAutofocus();
        }
    }

    /**
     * Handle leaving the scope (when focus moves outside this scope)
     */
    public function onScopeLeave(): void
    {
        // Can be overridden by subclasses for cleanup
    }

    /**
     * Override the focus request to handle scope entry
     */
    public function requestFocus(): bool
    {
        $result = parent::requestFocus();

        if ($result) {
            $this->onScopeEnter();
        }

        return $result;
    }

    /**
     * Override unfocus to handle scope exit
     */
    public function unfocus(): void
    {
        $hadFocus = $this->hasFocus();
        parent::unfocus();

        if ($hadFocus) {
            $this->onScopeLeave();
        }
    }

    /**
     * Get focus statistics for this scope
     */
    public function getFocusStats(): array
    {
        $focusableNodes = $this->getFocusableNodesInScope();
        $currentFocus = $this->focusManager?->getCurrentFocus();

        return [
            'total_focusable' => count($focusableNodes),
            'has_focus' => $this->hasFocusInScope(),
            'current_focus_id' => $currentFocus?->getId(),
            'autofocus_enabled' => $this->autofocus,
            'trap_focus_enabled' => $this->trapFocus,
            'autofocus_target_id' => $this->autofocusTargetId,
        ];
    }

    /**
     * Export focus tree structure for debugging
     */
    public function exportFocusTree(): array
    {
        return [
            'id' => $this->id,
            'type' => 'FocusScope',
            'has_focus' => $this->hasFocus(),
            'can_request_focus' => $this->canRequestFocus(),
            'autofocus' => $this->autofocus,
            'trap_focus' => $this->trapFocus,
            'autofocus_target_id' => $this->autofocusTargetId,
            'children' => array_map(
                fn (FocusNode $child) => $child instanceof FocusScope
                    ? $child->exportFocusTree()
                    : [
                        'id' => $child->getId(),
                        'type' => 'FocusNode',
                        'has_focus' => $child->hasFocus(),
                        'can_request_focus' => $child->canRequestFocus(),
                    ],
                $this->children
            ),
        ];
    }

    /**
     * Get the next focusable node within this scope
     */
    protected function getNextFocusableInScope(FocusNode $current): ?FocusNode
    {
        $focusableNodes = $this->getFocusableNodesInScope();
        $currentIndex = array_search($current, $focusableNodes, true);

        if ($currentIndex !== false && $currentIndex < count($focusableNodes) - 1) {
            return $focusableNodes[$currentIndex + 1];
        }

        return null;
    }

    /**
     * Get the previous focusable node within this scope
     */
    protected function getPreviousFocusableInScope(FocusNode $current): ?FocusNode
    {
        $focusableNodes = $this->getFocusableNodesInScope();
        $currentIndex = array_search($current, $focusableNodes, true);

        if ($currentIndex !== false && $currentIndex > 0) {
            return $focusableNodes[$currentIndex - 1];
        }

        return null;
    }
}
