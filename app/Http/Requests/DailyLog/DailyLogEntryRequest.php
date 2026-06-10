<?php

namespace App\Http\Requests\DailyLog;

use App\Enums\DailyLogEntryType;
use App\Enums\DailyLogSource;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class DailyLogEntryRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'date'       => ['required', 'date'],
            'entry_type' => ['required', Rule::enum(DailyLogEntryType::class)],
            'body'       => ['required', 'string'],
            'stage_id'   => ['nullable', 'integer', Rule::exists('stages', 'id')],
            'source'     => ['nullable', Rule::enum(DailyLogSource::class)],
        ];
    }

    public function rulesForUpdate(): array
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
