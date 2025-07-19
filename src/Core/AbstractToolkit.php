<?php

namespace HelgeSverre\Swarm\Core;

use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Contracts\Toolkit;

abstract class AbstractToolkit implements Toolkit
{
    protected array $exclude = [];

    protected array $only = [];

    /**
     * Exclude specific tool classes from being provided
     */
    public function exclude(array $classes): self
    {
        $this->exclude = $classes;

        return $this;
    }

    /**
     * Only include specific tool classes
     */
    public function only(array $classes): self
    {
        $this->only = $classes;

        return $this;
    }

    /**
     * Get filtered tools based on exclude/only settings
     *
     * @return Tool[]
     */
    public function tools(): array
    {
        $tools = $this->provide();

        // Apply only filter first if set - this takes precedence over exclude
        if (! empty($this->only)) {
            $tools = array_filter($tools, function (Tool $tool) {
                foreach ($this->only as $class) {
                    if ($tool instanceof $class || get_class($tool) === $class) {
                        return true;
                    }
                }

                return false;
            });
        } elseif (! empty($this->exclude)) {
            // Apply exclude filter only if no "only" filter is set
            $tools = array_filter($tools, function (Tool $tool) {
                foreach ($this->exclude as $class) {
                    if ($tool instanceof $class || get_class($tool) === $class) {
                        return false;
                    }
                }

                return true;
            });
        }

        return array_values($tools); // Reset array keys
    }

    /**
     * Default implementation returns no guidelines
     */
    public function guidelines(): ?string
    {
        return null;
    }
}
