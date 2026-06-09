<?php

namespace App\Enums;

enum LessonSource: string
{
    case Retrospective = 'retrospective';
    case Incident      = 'incident';
    case Observation   = 'observation';
}
