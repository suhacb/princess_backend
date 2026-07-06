<?php

namespace App\Http\Requests\ExceptionReport;

use App\Enums\ExceptionTriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExceptionReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'stage_id'              => ['nullable', 'integer', Rule::exists('stages', 'id')],
            'title'                 => ['required', 'string', 'max:255'],
            'trigger_type'          => ['required', Rule::enum(ExceptionTriggerType::class)],
            'description'           => ['required', 'string'],
            'cause'                 => ['required', 'string'],
            'impact'                => ['required', 'string'],
            'options'               => ['nullable', 'array'],
            'options.*.title'       => ['required', 'string'],
            'options.*.description' => ['required', 'string'],
            'options.*.pros'        => ['nullable', 'string'],
            'options.*.cons'        => ['nullable', 'string'],
            'recommendation'        => ['required', 'string'],
        ];
    }
}
