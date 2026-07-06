<?php

namespace App\Http\Requests\StageBoundary;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStageBoundaryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'             => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string'],
            'next_stage_id'     => ['nullable', 'integer'],
            'exception_summary' => ['nullable', 'string'],
        ];
    }
}
