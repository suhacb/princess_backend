<?php

namespace App\Enums;

enum AcceptanceCriterionStatus: string
{
    case Draft    = 'draft';
    case Approved = 'approved';
}
