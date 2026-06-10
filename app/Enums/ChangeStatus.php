<?php

namespace App\Enums;

enum ChangeStatus: string
{
    case Proposed    = 'proposed';
    case Assessed    = 'assessed';
    case Approved    = 'approved';
    case Rejected    = 'rejected';
    case Implemented = 'implemented';
}
