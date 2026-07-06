<?php

namespace App\Http\Requests\TestCase;

use App\Enums\TestCasePriority;
use App\Enums\TestCaseType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class TestCaseRequest extends DynamicRequest
{
    public function rulesForStore(): array
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

    public function rulesForUpdate(): array
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
