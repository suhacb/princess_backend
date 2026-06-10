<?php

namespace App\Enums;

enum IssuePriority: string
{
    case Low      = 'low';
    case Medium   = 'medium';
    case High     = 'high';
    case Critical = 'critical';
}
