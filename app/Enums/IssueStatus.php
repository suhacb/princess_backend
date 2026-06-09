<?php

namespace App\Enums;

enum IssueStatus: string
{
    case Open        = 'open';
    case UnderReview = 'under_review';
    case Escalated   = 'escalated';
    case Closed      = 'closed';
}
