<?php

namespace App\Http\Requests\TestSessionPlan;

use App\Enums\TeamType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestSessionPlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'planned_date'    => ['required', 'date'],
            'team_type'       => ['required', Rule::enum(TeamType::class)],
            'assignee_id'     => ['nullable', 'integer', Rule::exists('people', 'id')],
            'scenario_ids'    => ['nullable', 'array'],
            'scenario_ids.*'  => ['integer', Rule::exists('test_scenarios', 'id')],
        ];
    }
}
