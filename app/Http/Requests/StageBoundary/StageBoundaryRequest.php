<?php

namespace App\Http\Requests\StageBoundary;

use App\Enums\BoundaryType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class StageBoundaryRequest extends DynamicRequest
{
    public function rulesForStore(): array
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

    public function rulesForUpdate(): array
    {
        return [
            'title'             => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string'],
            'next_stage_id'     => ['nullable', 'integer'],
            'exception_summary' => ['nullable', 'string'],
        ];
    }
}
