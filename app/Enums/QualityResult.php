<?php

namespace App\Enums;

enum QualityResult: string
{
    case Passed      = 'passed';
    case Failed      = 'failed';
    case Conditional = 'conditional';
}
