<?php

namespace App\Enums;

enum HighlightReportStatus: string
{
    case Draft     = 'draft';
    case Submitted = 'submitted';
    case Approved  = 'approved';
}
