<?php

namespace App\Enums;

enum WorkPackageStatus: string
{
    case Draft      = 'draft';
    case Authorized = 'authorized';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';
}
