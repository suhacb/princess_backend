<?php

namespace App\Enums;

enum StageStatus: string
{
    case Planned   = 'planned';
    case Active    = 'active';
    case Completed = 'completed';
    case Exception = 'exception';
}
