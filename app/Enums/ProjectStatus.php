<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case PreProject = 'pre_project';
    case Initiation = 'initiation';
    case Delivery   = 'delivery';
    case Closing    = 'closing';
    case Closed     = 'closed';
}
