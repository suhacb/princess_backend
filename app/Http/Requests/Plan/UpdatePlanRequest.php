<?php

namespace App\Http\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'                     => ['sometimes', 'required', 'string', 'max:255'],
            'description'              => ['nullable', 'string'],
            'stage_id'                 => ['nullable', Rule::exists('stages', 'id')],
            'planned_start'            => ['sometimes', 'required', 'date'],
            'planned_end'              => ['sometimes', 'required', 'date', 'after_or_equal:planned_start'],
            'tolerance_time'           => ['nullable', 'string', 'max:100'],
            'tolerance_cost'           => ['nullable', 'string', 'max:100'],
            'tolerance_scope'          => ['nullable', 'string'],
            'tolerance_quality'        => ['nullable', 'string'],
            'tolerance_risk'           => ['nullable', 'string'],
            'tolerance_benefits'       => ['nullable', 'string'],
            'assumptions'              => ['nullable', 'string'],
            'external_dependencies'    => ['nullable', 'string'],
            'monitoring_and_reporting' => ['nullable', 'string'],
        ];
    }
}
