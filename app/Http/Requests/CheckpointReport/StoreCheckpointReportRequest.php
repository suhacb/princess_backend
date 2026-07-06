<?php

namespace App\Http\Requests\CheckpointReport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckpointReportRequest extends FormRequest
{
    public function rules(): array
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
}
