<?php

namespace App\Http\Requests\TestCase;

use App\Enums\TestCasePriority;
use App\Enums\TestCaseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'           => ['sometimes', 'required', 'string', 'max:255'],
            'steps'           => ['sometimes', 'required', 'array', 'min:1'],
            'steps.*'         => ['required', 'string'],
            'expected_result' => ['sometimes', 'required', 'string'],
            'priority'        => ['sometimes', Rule::enum(TestCasePriority::class)],
            'type'            => ['sometimes', 'required', Rule::enum(TestCaseType::class)],
        ];
    }
}
