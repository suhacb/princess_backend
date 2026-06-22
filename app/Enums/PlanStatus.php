<?php

namespace App\Enums;

enum PlanStatus: string
{
    case Draft     = 'draft';
    case Approved  = 'approved';
    case Active    = 'active';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
}
