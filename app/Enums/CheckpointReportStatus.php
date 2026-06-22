<?php

namespace App\Enums;

enum CheckpointReportStatus: string
{
    case Draft        = 'draft';
    case Submitted    = 'submitted';
    case Acknowledged = 'acknowledged';
}
