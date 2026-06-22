<?php

namespace App\Enums;

enum TestScenarioType: string
{
    case Feature = 'feature';
    case E2E     = 'e2e';
}
