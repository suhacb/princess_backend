<?php

namespace App\Enums;

enum QualityMethod: string
{
    case Review     = 'review';
    case Test       = 'test';
    case Audit      = 'audit';
    case Inspection = 'inspection';
}
