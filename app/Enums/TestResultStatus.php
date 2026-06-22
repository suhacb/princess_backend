<?php

namespace App\Enums;

enum TestResultStatus: string
{
    case Pass    = 'pass';
    case Fail    = 'fail';
    case Blocked = 'blocked';
    case NotRun  = 'not_run';
}
