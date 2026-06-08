<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'reference'         => ['nullable', 'string', 'max:50'],
            'description'       => ['nullable', 'string'],
            'status'            => ['nullable', Rule::enum(ProjectStatus::class)],
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
            'reference'         => ['nullable', 'string', 'max:50'],
            'description'       => ['nullable', 'string'],
            'status'            => ['nullable', Rule::enum(ProjectStatus::class)],
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
