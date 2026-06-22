<?php

namespace App\Enums;

enum RequirementPriority: string
{
    case Must   = 'must';
    case Should = 'should';
    case Could  = 'could';
    case Wont   = 'wont';
}
