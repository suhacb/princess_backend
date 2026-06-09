<?php

namespace App\Enums;

enum IssueType: string
{
    case Problem  = 'problem';
    case Concern  = 'concern';
    case Rfc      = 'rfc';
    case OffSpec  = 'off_spec';
}
