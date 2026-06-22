<?php

namespace App\Http\Requests\TestSession;

use App\Enums\TeamType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class TestSessionRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'title'               => ['required', 'string', 'max:255'],
            'session_date'        => ['required', 'date'],
            'tester_id'           => ['required', 'integer', Rule::exists('people', 'id')],
            'team_type'           => ['required', Rule::enum(TeamType::class)],
            'environment'         => ['nullable', 'string', 'max:255'],
            'notes'               => ['nullable', 'string'],
            'test_session_plan_id' => ['nullable', 'integer', Rule::exists('test_session_plans', 'id')],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'title'        => ['sometimes', 'required', 'string', 'max:255'],
            'session_date' => ['sometimes', 'required', 'date'],
            'tester_id'    => ['sometimes', 'required', 'integer', Rule::exists('people', 'id')],
            'environment'  => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string'],
        ];
    }

    public function rulesForUpdateResult(): array
    {
        return [
            'result'     => ['required', Rule::in(['pass', 'fail', 'blocked', 'not_run'])],
            'notes'      => ['nullable', 'string'],
            'defect_ref' => ['nullable', 'string', 'max:255'],
        ];
    }
}
