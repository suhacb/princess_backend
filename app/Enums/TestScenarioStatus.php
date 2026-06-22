<?php

namespace App\Enums;

enum TestScenarioStatus: string
{
    case Draft    = 'draft';
    case Ready    = 'ready';
    case Obsolete = 'obsolete';
}
