<?php

use App\Models\ActivityLog;
use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;

return [
    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    'clean_after_days' => 365,

    'default_log_name' => 'default',

    'default_auth_driver' => null,

    'include_soft_deleted_subjects' => false,

    'activity_model' => ActivityLog::class,

    'default_except_attributes' => [],

    'buffer' => [
        'enabled' => env('ACTIVITYLOG_BUFFER_ENABLED', false),
    ],

    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],
];
