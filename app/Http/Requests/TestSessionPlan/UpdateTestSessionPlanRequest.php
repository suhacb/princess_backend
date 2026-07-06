<?php

namespace App\Http\Requests\TestSessionPlan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestSessionPlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'           => ['sometimes', 'required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'planned_date'    => ['sometimes', 'required', 'date'],
            'assignee_id'     => ['nullable', 'integer', Rule::exists('people', 'id')],
            'scenario_ids'    => ['nullable', 'array'],
            'scenario_ids.*'  => ['integer', Rule::exists('test_scenarios', 'id')],
        ];
    }
}
