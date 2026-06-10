<?php

namespace App\Enums;

enum DailyLogSource: string
{
    case Manual       = 'manual';
    case AiSuggested  = 'ai_suggested';
    case EmailDerived = 'email_derived';
}
