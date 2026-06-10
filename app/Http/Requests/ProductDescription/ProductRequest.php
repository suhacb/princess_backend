<?php

namespace App\Http\Requests\ProductDescription;

use App\Enums\ProductType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'parent_id'               => ['nullable', 'integer', Rule::exists('products', 'id')],
            'identifier'              => ['nullable', 'string', 'max:50'],
            'title'                   => ['required', 'string', 'max:255'],
            'purpose'                 => ['nullable', 'string'],
            'composition'             => ['nullable', 'string'],
            'derivation'              => ['nullable', 'string'],
            'format_and_presentation' => ['nullable', 'string'],
            'type'                    => ['required', Rule::enum(ProductType::class)],
            'quality_criteria'        => ['nullable', 'array'],
            'quality_criteria.*'      => ['string'],
            'quality_tolerance'       => ['nullable', 'string'],
            'quality_method'          => ['nullable', 'string'],
            'quality_skills_required' => ['nullable', 'string'],
            'quality_responsibilities' => ['nullable', 'array'],
            'quality_responsibilities.producer' => ['nullable', 'string'],
            'quality_responsibilities.reviewer' => ['nullable', 'string'],
            'quality_responsibilities.approver' => ['nullable', 'string'],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'parent_id'               => ['nullable', 'integer', Rule::exists('products', 'id')],
            'identifier'              => ['nullable', 'string', 'max:50'],
            'title'                   => ['sometimes', 'required', 'string', 'max:255'],
            'purpose'                 => ['nullable', 'string'],
            'composition'             => ['nullable', 'string'],
            'derivation'              => ['nullable', 'string'],
            'format_and_presentation' => ['nullable', 'string'],
            'type'                    => ['sometimes', 'required', Rule::enum(ProductType::class)],
            'quality_criteria'        => ['nullable', 'array'],
            'quality_criteria.*'      => ['string'],
            'quality_tolerance'       => ['nullable', 'string'],
            'quality_method'          => ['nullable', 'string'],
            'quality_skills_required' => ['nullable', 'string'],
            'quality_responsibilities' => ['nullable', 'array'],
            'quality_responsibilities.producer' => ['nullable', 'string'],
            'quality_responsibilities.reviewer' => ['nullable', 'string'],
            'quality_responsibilities.approver' => ['nullable', 'string'],
        ];
    }
}
