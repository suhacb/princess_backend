<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft         = 'draft';
    case InDevelopment = 'in_development';
    case Baselined     = 'baselined';
    case Superseded    = 'superseded';
}
