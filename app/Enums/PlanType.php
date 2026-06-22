<?php

namespace App\Enums;

enum PlanType: string
{
    case Stage     = 'stage';
    case Team      = 'team';
    case Exception = 'exception';
}
