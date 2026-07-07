<?php

namespace App\Http\Requests\TestSession;

use App\Enums\TestResultStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestCaseResultTestSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'result'                        => ['required_without:step_results', Rule::enum(TestResultStatus::class)],
            'step_results'                  => ['nullable', 'array'],
            'step_results.*.step_index'     => ['required', 'integer', 'min:0'],
            'step_results.*.result'         => ['required', Rule::in(['pass', 'fail', 'blocked'])],
            'step_results.*.actual_result'  => ['nullable', 'string'],
            'step_results.*.defect_ref'     => ['nullable', 'string', 'max:255'],
            'notes'                         => ['nullable', 'string'],
            'defect_ref'                    => ['nullable', 'string', 'max:255'],
        ];
    }
}
