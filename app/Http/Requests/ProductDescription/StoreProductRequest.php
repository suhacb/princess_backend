<?php

namespace App\Http\Requests\ProductDescription;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'parent_id'                          => ['nullable', 'integer', Rule::exists('products', 'id')],
            'identifier'                         => ['nullable', 'string', 'max:50'],
            'title'                              => ['required', 'string', 'max:255'],
            'purpose'                            => ['nullable', 'string'],
            'composition'                        => ['nullable', 'string'],
            'derivation'                         => ['nullable', 'string'],
            'format_and_presentation'            => ['nullable', 'string'],
            'type'                               => ['required', Rule::enum(ProductType::class)],
            'quality_criteria'                   => ['nullable', 'array'],
            'quality_criteria.*'                 => ['string'],
            'quality_tolerance'                  => ['nullable', 'string'],
            'quality_method'                     => ['nullable', 'string'],
            'quality_skills_required'            => ['nullable', 'string'],
            'quality_responsibilities'           => ['nullable', 'array'],
            'quality_responsibilities.producer'  => ['nullable', 'string'],
            'quality_responsibilities.reviewer'  => ['nullable', 'string'],
            'quality_responsibilities.approver'  => ['nullable', 'string'],
        ];
    }
}
