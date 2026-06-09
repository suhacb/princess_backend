<?php

namespace App\Enums;

enum ChangeRequestType: string
{
    case Rfc     = 'rfc';
    case OffSpec = 'off_spec';
}
