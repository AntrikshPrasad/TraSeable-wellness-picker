<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Open = 'open';
    case Decided = 'decided';
    case Skipped = 'skipped';
}
