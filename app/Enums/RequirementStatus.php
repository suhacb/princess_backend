<?php

namespace App\Enums;

enum RequirementStatus: string
{
    case Draft    = 'draft';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Deferred = 'deferred';
}
