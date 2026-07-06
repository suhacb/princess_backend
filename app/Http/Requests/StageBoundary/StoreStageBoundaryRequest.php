<?php

namespace App\Http\Requests\StageBoundary;

use App\Enums\BoundaryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStageBoundaryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type'              => ['required', Rule::enum(BoundaryType::class)],
            'title'             => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string'],
            'next_stage_id'     => ['nullable', 'integer'],
            'exception_summary' => [
                Rule::requiredIf(fn () => $this->input('type') === BoundaryType::ExceptionReport->value),
                'nullable', 'string',
            ],
        ];
    }
}
