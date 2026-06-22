<?php

namespace App\Http\Requests\CheckpointReport;

use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class CheckpointReportRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'work_package_id'     => ['nullable', 'integer', Rule::exists('work_packages', 'id')],
            'title'               => ['required', 'string', 'max:255'],
            'period_from'         => ['required', 'date'],
            'period_to'           => ['required', 'date', 'after_or_equal:period_from'],
            'achievements'        => ['required', 'string'],
            'planned_next_period' => ['required', 'string'],
            'issues_this_period'  => ['nullable', 'string'],
            'issues_forecast'     => ['nullable', 'string'],
            'quality_notes'       => ['nullable', 'string'],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'work_package_id'     => ['nullable', 'integer', Rule::exists('work_packages', 'id')],
            'title'               => ['sometimes', 'required', 'string', 'max:255'],
            'period_from'         => ['sometimes', 'required', 'date'],
            'period_to'           => ['sometimes', 'required', 'date', 'after_or_equal:period_from'],
            'achievements'        => ['sometimes', 'required', 'string'],
            'planned_next_period' => ['sometimes', 'required', 'string'],
            'issues_this_period'  => ['nullable', 'string'],
            'issues_forecast'     => ['nullable', 'string'],
            'quality_notes'       => ['nullable', 'string'],
        ];
    }
}
