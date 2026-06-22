<?php

namespace App\Http\Requests\TestScenario;

use App\Enums\TestScenarioType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class TestScenarioRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'title'                    => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string'],
            'preconditions'            => ['nullable', 'string'],
            'type'                     => ['required', Rule::enum(TestScenarioType::class)],
            'acceptance_criterion_ids' => ['nullable', 'array'],
            'acceptance_criterion_ids.*' => ['integer', Rule::exists('acceptance_criteria', 'id')],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'title'                    => ['sometimes', 'required', 'string', 'max:255'],
            'description'              => ['nullable', 'string'],
            'preconditions'            => ['nullable', 'string'],
            'acceptance_criterion_ids' => ['nullable', 'array'],
            'acceptance_criterion_ids.*' => ['integer', Rule::exists('acceptance_criteria', 'id')],
        ];
    }

    public function rulesForMarkTestable(): array
    {
        return [
            'testable_notes' => ['nullable', 'string'],
        ];
    }
}
