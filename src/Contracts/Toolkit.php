<?php

namespace HelgeSverre\Swarm\Contracts;

interface Toolkit
{
    /**
     * Provide all tools that this toolkit offers
     *
     * @return Tool[]
     */
    public function provide(): array;

    /**
     * Get optional usage guidelines for this toolkit
     */
    public function guidelines(): ?string;
}
