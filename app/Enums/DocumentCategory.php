<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Initiation = 'initiation';
    case Planning   = 'planning';
    case Reporting  = 'reporting';
    case Register   = 'register';
    case Qa         = 'qa';
    case Meeting    = 'meeting';
    case General    = 'general';
}
