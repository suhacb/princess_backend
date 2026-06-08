<?php

namespace App\Http\Requests\Stage;

use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class StageRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'type'              => ['required', Rule::enum(StageType::class)],
            'sequence'          => ['nullable', 'integer', 'min:0'],
            'description'       => ['nullable', 'string'],
            'status'            => ['nullable', Rule::enum(StageStatus::class)],
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
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'name'              => ['sometimes', 'required', 'string', 'max:255'],
            'type'              => ['nullable', Rule::enum(StageType::class)],
            'sequence'          => ['nullable', 'integer', 'min:0'],
            'description'       => ['nullable', 'string'],
            'status'            => ['nullable', Rule::enum(StageStatus::class)],
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
            'version'           => ['nullable', 'integer', 'min:1'],
        ];
    }
}
