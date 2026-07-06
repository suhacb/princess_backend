<?php

namespace App\Http\Requests\TestSession;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'        => ['sometimes', 'required', 'string', 'max:255'],
            'session_date' => ['sometimes', 'required', 'date'],
            'tester_id'    => ['sometimes', 'required', 'integer', Rule::exists('people', 'id')],
            'environment'  => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string'],
        ];
    }
}
