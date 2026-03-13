<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\CLI\Command;

enum CommandAction: string
{
    case Exit = 'exit';
    case SaveState = 'save_state';
    case ClearState = 'clear_state';
    case ClearHistory = 'clear_history';
    case ShowHelp = 'show_help';
    case Error = 'error';
}
