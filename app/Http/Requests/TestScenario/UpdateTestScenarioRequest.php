<?php

namespace App\Http\Requests\TestScenario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestScenarioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'                       => ['sometimes', 'required', 'string', 'max:255'],
            'description'                 => ['nullable', 'string'],
            'preconditions'               => ['nullable', 'string'],
            'acceptance_criterion_ids'    => ['nullable', 'array'],
            'acceptance_criterion_ids.*'  => ['integer', Rule::exists('acceptance_criteria', 'id')],
        ];
    }
}
