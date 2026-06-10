<?php

namespace App\Http\Requests\ProductDescription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'predecessor_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'successor_id'   => ['required', 'integer', Rule::exists('products', 'id')],
        ];
    }
}
