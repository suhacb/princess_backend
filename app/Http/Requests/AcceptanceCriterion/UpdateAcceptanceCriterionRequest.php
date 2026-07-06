<?php

namespace App\Http\Requests\AcceptanceCriterion;

use App\Enums\VerificationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcceptanceCriterionRequest extends FormRequest
{
    public function rules(): array
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
