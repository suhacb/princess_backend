<?php

namespace App\Http\Requests\QualityRegister;

use App\Enums\QualityMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQualityRegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_name'   => ['required', 'string', 'max:255'],
            'quality_method' => ['required', Rule::enum(QualityMethod::class)],
            'planned_date'   => ['nullable', 'date'],
            'stage_id'       => ['nullable', 'integer', Rule::exists('stages', 'id')],
        ];
    }
}
