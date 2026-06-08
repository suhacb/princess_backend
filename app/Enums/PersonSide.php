<?php

namespace App\Enums;

enum PersonSide: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Neutral  = 'neutral';
}
