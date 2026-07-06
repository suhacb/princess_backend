<?php

namespace App\Http\Requests\DailyLog;

use App\Enums\DailyLogEntryType;
use App\Enums\DailyLogSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyLogEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date'       => ['required', 'date'],
            'entry_type' => ['required', Rule::enum(DailyLogEntryType::class)],
            'body'       => ['required', 'string'],
            'stage_id'   => ['nullable', 'integer', Rule::exists('stages', 'id')],
            'source'     => ['nullable', Rule::enum(DailyLogSource::class)],
        ];
    }
}
