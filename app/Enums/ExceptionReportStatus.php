<?php

namespace App\Enums;

enum ExceptionReportStatus: string
{
    case Draft     = 'draft';
    case Submitted = 'submitted';
    case Closed    = 'closed';
}
