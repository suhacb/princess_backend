<?php

namespace App\Http\Requests\Stage;

use App\Enums\StageStatus;
use App\Enums\StageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'required', 'string', 'max:255'],
            'type'              => ['sometimes', 'required', Rule::enum(StageType::class)],
            'sequence'          => ['integer', 'min:0'],
            'description'       => ['nullable', 'string'],
            'status'            => [Rule::enum(StageStatus::class)],
            'planned_start'     => ['nullable', 'date'],
            'planned_end'       => ['nullable', 'date', 'after_or_equal:planned_start'],
            'actual_start'      => ['nullable', 'date'],
            'actual_end'        => ['nullable', 'date', 'after_or_equal:actual_start'],
            'tolerance_time'    => ['nullable', 'string', 'max:255'],
            'tolerance_cost'    => ['nullable', 'string', 'max:255'],
            'tolerance_scope'   => ['nullable', 'string', 'max:255'],
            'tolerance_risk'    => ['nullable', 'string', 'max:255'],
            'tolerance_quality' => ['nullable', 'string', 'max:255'],
            'tolerance_benefit' => ['nullable', 'string', 'max:255'],
            'version'           => ['integer', 'min:1'],
        ];
    }
}
