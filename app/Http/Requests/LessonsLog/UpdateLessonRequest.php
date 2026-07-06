<?php

namespace App\Http\Requests\LessonsLog;

use App\Enums\LessonSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonRequest extends FormRequest
{
    public function rules(): array
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
