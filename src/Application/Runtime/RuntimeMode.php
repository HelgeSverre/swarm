<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Application\Runtime;

enum RuntimeMode: string
{
    case Cli = 'cli';
    case Worker = 'worker';
}
