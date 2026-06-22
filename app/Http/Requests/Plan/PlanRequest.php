<?php

namespace App\Http\Requests\Plan;

use App\Enums\PlanType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'type'                    => ['required', Rule::enum(PlanType::class)],
            'name'                    => ['required', 'string', 'max:255'],
            'description'             => ['nullable', 'string'],
            'stage_id'                => [
                Rule::requiredIf(fn () => in_array($this->type, [PlanType::Stage->value, PlanType::Exception->value])),
                'nullable',
                Rule::exists('stages', 'id'),
            ],
            'replaces_plan_id'        => [
                Rule::requiredIf(fn () => $this->type === PlanType::Exception->value),
                'nullable',
                Rule::exists('plans', 'id'),
            ],
            'planned_start'           => ['required', 'date'],
            'planned_end'             => ['required', 'date', 'after_or_equal:planned_start'],
            'tolerance_time'          => ['nullable', 'string', 'max:100'],
            'tolerance_cost'          => ['nullable', 'string', 'max:100'],
            'tolerance_scope'         => ['nullable', 'string'],
            'tolerance_quality'       => ['nullable', 'string'],
            'tolerance_risk'          => ['nullable', 'string'],
            'tolerance_benefits'      => ['nullable', 'string'],
            'assumptions'             => ['nullable', 'string'],
            'external_dependencies'   => ['nullable', 'string'],
            'monitoring_and_reporting' => ['nullable', 'string'],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'name'                    => ['sometimes', 'required', 'string', 'max:255'],
            'description'             => ['nullable', 'string'],
            'stage_id'                => ['nullable', Rule::exists('stages', 'id')],
            'planned_start'           => ['sometimes', 'required', 'date'],
            'planned_end'             => ['sometimes', 'required', 'date', 'after_or_equal:planned_start'],
            'tolerance_time'          => ['nullable', 'string', 'max:100'],
            'tolerance_cost'          => ['nullable', 'string', 'max:100'],
            'tolerance_scope'         => ['nullable', 'string'],
            'tolerance_quality'       => ['nullable', 'string'],
            'tolerance_risk'          => ['nullable', 'string'],
            'tolerance_benefits'      => ['nullable', 'string'],
            'assumptions'             => ['nullable', 'string'],
            'external_dependencies'   => ['nullable', 'string'],
            'monitoring_and_reporting' => ['nullable', 'string'],
        ];
    }
}
