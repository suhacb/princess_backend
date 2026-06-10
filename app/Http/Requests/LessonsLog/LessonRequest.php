<?php

namespace App\Http\Requests\LessonsLog;

use App\Enums\LessonSource;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'description'    => ['required', 'string'],
            'source'         => ['required', Rule::enum(LessonSource::class)],
            'category'       => ['nullable', 'string', 'max:255'],
            'recommendation' => ['nullable', 'string'],
            'stage_id'       => ['nullable', 'integer', Rule::exists('stages', 'id')],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'description'    => ['sometimes', 'required', 'string'],
            'source'         => ['sometimes', 'required', Rule::enum(LessonSource::class)],
            'category'       => ['nullable', 'string', 'max:255'],
            'recommendation' => ['nullable', 'string'],
            'stage_id'       => ['nullable', 'integer', Rule::exists('stages', 'id')],
        ];
    }
}
