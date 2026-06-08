<?php

namespace App\Enums;

enum StageType: string
{
    case Initiation = 'initiation';
    case Delivery   = 'delivery';
    case Final      = 'final';
}
