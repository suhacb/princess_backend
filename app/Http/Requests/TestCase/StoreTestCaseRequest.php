<?php

namespace App\Http\Requests\TestCase;

use App\Enums\TestCasePriority;
use App\Enums\TestCaseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:255'],
            'steps'           => ['required', 'array', 'min:1'],
            'steps.*'         => ['required', 'string'],
            'expected_result' => ['required', 'string'],
            'priority'        => ['sometimes', Rule::enum(TestCasePriority::class)],
            'type'            => ['required', Rule::enum(TestCaseType::class)],
        ];
    }
}
