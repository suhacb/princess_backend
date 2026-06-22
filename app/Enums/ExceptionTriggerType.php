<?php

namespace App\Enums;

enum ExceptionTriggerType: string
{
    case ToleranceTime    = 'tolerance_time';
    case ToleranceCost    = 'tolerance_cost';
    case ToleranceScope   = 'tolerance_scope';
    case ToleranceQuality = 'tolerance_quality';
    case ToleranceRisk    = 'tolerance_risk';
    case IssueEscalation  = 'issue_escalation';
    case Manual           = 'manual';
}
