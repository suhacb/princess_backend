<?php

namespace App\Enums;

enum BoundaryType: string
{
    case EndStageReport  = 'end_stage_report';
    case ExceptionReport = 'exception_report';
}
