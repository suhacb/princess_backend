<?php

namespace App\Enums;

enum RequirementType: string
{
    case Classic    = 'classic';
    case Epic       = 'epic';
    case UserStory  = 'user_story';
}
