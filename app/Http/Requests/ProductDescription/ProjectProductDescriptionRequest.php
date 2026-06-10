<?php

namespace App\Http\Requests\ProductDescription;

use App\Http\Requests\DynamicRequest;

class ProjectProductDescriptionRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'title'                         => ['required', 'string', 'max:255'],
            'purpose'                       => ['nullable', 'string'],
            'composition'                   => ['nullable', 'string'],
            'derivation'                    => ['nullable', 'string'],
            'format_and_presentation'       => ['nullable', 'string'],
            'quality_criteria'              => ['nullable', 'array'],
            'quality_criteria.*'            => ['string'],
            'quality_tolerance'             => ['nullable', 'string'],
            'quality_method'                => ['nullable', 'string'],
            'quality_skills_required'       => ['nullable', 'string'],
            'quality_responsibilities'      => ['nullable', 'array'],
            'quality_responsibilities.producer' => ['nullable', 'string'],
            'quality_responsibilities.reviewer' => ['nullable', 'string'],
            'quality_responsibilities.approver' => ['nullable', 'string'],
            'customer_quality_expectations' => ['nullable', 'string'],
            'acceptance_criteria'           => ['nullable', 'array'],
            'acceptance_criteria.*'         => ['string'],
            'acceptance_methods'            => ['nullable', 'string'],
            'acceptance_responsibilities'   => ['nullable', 'string'],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'title'                         => ['sometimes', 'required', 'string', 'max:255'],
            'purpose'                       => ['nullable', 'string'],
            'composition'                   => ['nullable', 'string'],
            'derivation'                    => ['nullable', 'string'],
            'format_and_presentation'       => ['nullable', 'string'],
            'quality_criteria'              => ['nullable', 'array'],
            'quality_criteria.*'            => ['string'],
            'quality_tolerance'             => ['nullable', 'string'],
            'quality_method'                => ['nullable', 'string'],
            'quality_skills_required'       => ['nullable', 'string'],
            'quality_responsibilities'      => ['nullable', 'array'],
            'quality_responsibilities.producer' => ['nullable', 'string'],
            'quality_responsibilities.reviewer' => ['nullable', 'string'],
            'quality_responsibilities.approver' => ['nullable', 'string'],
            'customer_quality_expectations' => ['nullable', 'string'],
            'acceptance_criteria'           => ['nullable', 'array'],
            'acceptance_criteria.*'         => ['string'],
            'acceptance_methods'            => ['nullable', 'string'],
            'acceptance_responsibilities'   => ['nullable', 'string'],
        ];
    }
}
