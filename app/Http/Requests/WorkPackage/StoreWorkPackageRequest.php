<?php

namespace App\Http\Requests\WorkPackage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkPackageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan_id'                               => ['nullable', 'integer', Rule::exists('plans', 'id')],
            'team_manager_id'                       => ['required', 'integer', Rule::exists('people', 'id')],
            'title'                                 => ['required', 'string', 'max:255'],
            'description'                           => ['nullable', 'string'],
            'techniques_and_processes'              => ['nullable', 'string'],
            'development_interfaces'                => ['nullable', 'string'],
            'operations_interfaces'                 => ['nullable', 'string'],
            'configuration_management_requirements' => ['nullable', 'string'],
            'constraints'                           => ['nullable', 'string'],
            'reporting_requirements'                => ['nullable', 'string'],
            'tolerance_time'                        => ['nullable', 'string', 'max:100'],
            'tolerance_cost'                        => ['nullable', 'string', 'max:100'],
            'tolerance_scope'                       => ['nullable', 'string'],
            'tolerance_quality'                     => ['nullable', 'string'],
            'tolerance_risk'                        => ['nullable', 'string'],
            'tolerance_benefits'                    => ['nullable', 'string'],
            'planned_start'                         => ['required', 'date'],
            'planned_end'                           => ['required', 'date', 'after_or_equal:planned_start'],
            'product_ids'                           => ['nullable', 'array'],
            'product_ids.*'                         => ['integer', Rule::exists('products', 'id')],
        ];
    }
}
