<?php

namespace App\Http\Requests\TestScenario;

use Illuminate\Foundation\Http\FormRequest;

class MarkTestableTestScenarioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'testable_notes' => ['nullable', 'string'],
        ];
    }
}
