<?php

namespace App\Http\Requests\TestScenario;

use App\Enums\TestScenarioType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestScenarioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'                       => ['required', 'string', 'max:255'],
            'description'                 => ['nullable', 'string'],
            'preconditions'               => ['nullable', 'string'],
            'type'                        => ['required', Rule::enum(TestScenarioType::class)],
            'acceptance_criterion_ids'    => ['nullable', 'array'],
            'acceptance_criterion_ids.*'  => ['integer', Rule::exists('acceptance_criteria', 'id')],
        ];
    }
}
