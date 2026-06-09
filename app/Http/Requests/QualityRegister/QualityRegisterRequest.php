<?php

namespace App\Http\Requests\QualityRegister;

use App\Enums\QualityMethod;
use App\Enums\QualityResult;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class QualityRegisterRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'product_name'   => ['required', 'string', 'max:255'],
            'quality_method' => ['required', Rule::enum(QualityMethod::class)],
            'planned_date'   => ['nullable', 'date'],
            'stage_id'       => ['nullable', 'integer', Rule::exists('stages', 'id')],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'product_name'   => ['sometimes', 'required', 'string', 'max:255'],
            'quality_method' => ['sometimes', 'required', Rule::enum(QualityMethod::class)],
            'planned_date'   => ['nullable', 'date'],
            'actual_date'    => ['nullable', 'date'],
            'reviewers'      => ['nullable', 'array'],
            'reviewers.*'    => ['integer', Rule::exists('people', 'id')],
            'result'         => ['nullable', Rule::enum(QualityResult::class)],
            'issues_raised'  => ['nullable', 'string'],
            'sign_off_by'    => ['nullable', 'integer', Rule::exists('people', 'id')],
            'sign_off_at'    => ['nullable', 'date'],
            'stage_id'       => ['nullable', 'integer', Rule::exists('stages', 'id')],
        ];
    }
}
