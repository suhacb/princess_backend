<?php

namespace App\Http\Requests\ExceptionReport;

use App\Enums\ExceptionTriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExceptionReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'stage_id'              => ['nullable', 'integer', Rule::exists('stages', 'id')],
            'title'                 => ['sometimes', 'required', 'string', 'max:255'],
            'trigger_type'          => ['sometimes', 'required', Rule::enum(ExceptionTriggerType::class)],
            'description'           => ['sometimes', 'required', 'string'],
            'cause'                 => ['sometimes', 'required', 'string'],
            'impact'                => ['sometimes', 'required', 'string'],
            'options'               => ['nullable', 'array'],
            'options.*.title'       => ['required', 'string'],
            'options.*.description' => ['required', 'string'],
            'options.*.pros'        => ['nullable', 'string'],
            'options.*.cons'        => ['nullable', 'string'],
            'recommendation'        => ['sometimes', 'required', 'string'],
        ];
    }
}
