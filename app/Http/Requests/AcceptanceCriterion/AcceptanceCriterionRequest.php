<?php

namespace App\Http\Requests\AcceptanceCriterion;

use App\Enums\VerificationMethod;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class AcceptanceCriterionRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'requirement_id'       => ['required', 'integer', Rule::exists('requirements', 'id')],
            'title'                => ['required', 'string', 'max:255'],
            'description'          => ['required', 'string'],
            'measurement_method'   => ['nullable', 'string'],
            'acceptance_threshold' => ['nullable', 'string', 'max:255'],
            'verifier_id'          => ['nullable', 'integer', Rule::exists('people', 'id')],
            'verification_method'  => ['nullable', Rule::enum(VerificationMethod::class)],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'title'                => ['sometimes', 'required', 'string', 'max:255'],
            'description'          => ['sometimes', 'required', 'string'],
            'measurement_method'   => ['nullable', 'string'],
            'acceptance_threshold' => ['nullable', 'string', 'max:255'],
            'verifier_id'          => ['nullable', 'integer', Rule::exists('people', 'id')],
            'verification_method'  => ['nullable', Rule::enum(VerificationMethod::class)],
        ];
    }
}
