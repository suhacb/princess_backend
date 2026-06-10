<?php

namespace App\Enums;

enum RiskResponseType: string
{
    case Avoid    = 'avoid';
    case Reduce   = 'reduce';
    case Transfer = 'transfer';
    case Accept   = 'accept';
    case Share    = 'share';
    case Exploit  = 'exploit';
}
