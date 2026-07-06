<?php

namespace App\Enums;

enum VerificationMethod: string
{
    case Test       = 'test';
    case Demo       = 'demo';
    case Review     = 'review';
    case Inspection = 'inspection';
}
