<?php

namespace App\Enums;

enum DailyLogEntryType: string
{
    case Note        = 'note';
    case Action      = 'action';
    case Reminder    = 'reminder';
    case Observation = 'observation';
}
