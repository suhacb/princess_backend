<?php

namespace App\Http\Requests\TestSession;

use App\Enums\TestResultStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResultTestSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'result'     => ['required', Rule::enum(TestResultStatus::class)],
            'notes'      => ['nullable', 'string'],
            'defect_ref' => ['nullable', 'string', 'max:255'],
        ];
    }
}
