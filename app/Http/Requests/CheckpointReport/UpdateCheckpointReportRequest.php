<?php

namespace App\Http\Requests\CheckpointReport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCheckpointReportRequest extends FormRequest
{
    public function rules(): array
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
