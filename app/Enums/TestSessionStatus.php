<?php

namespace App\Enums;

enum TestSessionStatus: string
{
    case Planned    = 'planned';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';
}
