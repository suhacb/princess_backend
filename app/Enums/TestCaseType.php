<?php

namespace App\Enums;

enum TestCaseType: string
{
    case Positive = 'positive';
    case Negative = 'negative';
    case Edge     = 'edge';
}
