<?php

namespace App\Http\Requests\AcceptanceCriterion;

use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class AcceptanceCriterionRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'requirement_id'       => ['required', 'integer', Rule::exists('requirements', 'id')],
            'description'          => ['required', 'string'],
            'measurement_method'   => ['nullable', 'string'],
            'acceptance_threshold' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'description'          => ['sometimes', 'required', 'string'],
            'measurement_method'   => ['nullable', 'string'],
            'acceptance_threshold' => ['nullable', 'string', 'max:255'],
        ];
    }
}
