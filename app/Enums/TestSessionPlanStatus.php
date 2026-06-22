<?php

namespace App\Enums;

enum TestSessionPlanStatus: string
{
    case Draft     = 'draft';
    case Active    = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
