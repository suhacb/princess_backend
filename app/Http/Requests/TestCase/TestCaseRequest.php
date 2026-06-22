<?php

namespace App\Http\Requests\TestCase;

use App\Http\Requests\DynamicRequest;

class TestCaseRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'title'           => ['required', 'string', 'max:255'],
            'steps'           => ['required', 'array', 'min:1'],
            'steps.*'         => ['required', 'string'],
            'expected_result' => ['required', 'string'],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'title'           => ['sometimes', 'required', 'string', 'max:255'],
            'steps'           => ['sometimes', 'required', 'array', 'min:1'],
            'steps.*'         => ['required', 'string'],
            'expected_result' => ['sometimes', 'required', 'string'],
        ];
    }
}
