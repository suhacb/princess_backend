<?php

namespace App\Http\Requests\TestSessionPlan;

use App\Enums\TeamType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class TestSessionPlanRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'planned_date' => ['required', 'date'],
            'team_type'    => ['required', Rule::enum(TeamType::class)],
            'assignee_id'  => ['nullable', 'integer', Rule::exists('people', 'id')],
            'scenario_ids' => ['nullable', 'array'],
            'scenario_ids.*' => ['integer', Rule::exists('test_scenarios', 'id')],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'title'        => ['sometimes', 'required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'planned_date' => ['sometimes', 'required', 'date'],
            'assignee_id'  => ['nullable', 'integer', Rule::exists('people', 'id')],
            'scenario_ids' => ['nullable', 'array'],
            'scenario_ids.*' => ['integer', Rule::exists('test_scenarios', 'id')],
        ];
    }
}
