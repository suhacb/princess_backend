<?php

namespace App\Enums;

enum RiskProximity: string
{
    case Imminent = 'imminent';
    case Near     = 'near';
    case Distant  = 'distant';
}
