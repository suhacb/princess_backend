<?php

namespace App\Http\Requests\DailyLog;

use App\Enums\DailyLogEntryType;
use App\Enums\DailyLogSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDailyLogEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date'       => ['sometimes', 'required', 'date'],
            'entry_type' => ['sometimes', 'required', Rule::enum(DailyLogEntryType::class)],
            'body'       => ['sometimes', 'required', 'string'],
            'stage_id'   => ['nullable', 'integer', Rule::exists('stages', 'id')],
            'source'     => ['nullable', Rule::enum(DailyLogSource::class)],
        ];
    }
}
