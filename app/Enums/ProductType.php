<?php

namespace App\Enums;

enum ProductType: string
{
    case Specialist = 'specialist';
    case Management = 'management';
    case External   = 'external';
}
