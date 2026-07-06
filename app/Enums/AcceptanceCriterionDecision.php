<?php

namespace App\Enums;

enum AcceptanceCriterionDecision: string
{
    case Pending  = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
