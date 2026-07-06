<?php

namespace App\Http\Requests\QaDocument;

use App\Models\CheckpointReport;
use App\Models\ExceptionReport;
use App\Models\HighlightReport;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Stage;

class DocumentableTypes
{
    private const MAP = [
        'meeting'           => Meeting::class,
        'highlight_report'  => HighlightReport::class,
        'checkpoint_report' => CheckpointReport::class,
        'exception_report'  => ExceptionReport::class,
        'stage'             => Stage::class,
        'project'           => Project::class,
    ];

    public static function map(): array
    {
        return self::MAP;
    }
}
