<?php

namespace App\Http\Requests\TestSession;

use App\Enums\TeamType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'                 => ['required', 'string', 'max:255'],
            'session_date'          => ['required', 'date'],
            'tester_id'             => ['required', 'integer', Rule::exists('people', 'id')],
            'team_type'             => ['required', Rule::enum(TeamType::class)],
            'environment'           => ['nullable', 'string', 'max:255'],
            'notes'                 => ['nullable', 'string'],
            'test_session_plan_id'  => ['nullable', 'integer', Rule::exists('test_session_plans', 'id')],
        ];
    }
}
