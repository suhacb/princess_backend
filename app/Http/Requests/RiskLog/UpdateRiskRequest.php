<?php

namespace App\Http\Requests\RiskLog;

use App\Enums\RiskProximity;
use App\Enums\RiskResponseType;
use App\Enums\RiskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRiskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'                 => ['sometimes', 'required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'category'              => ['nullable', 'string', 'max:255'],
            'probability'           => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'impact'                => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'proximity'             => ['sometimes', 'required', Rule::enum(RiskProximity::class)],
            'risk_owner'            => ['sometimes', 'required', 'integer', Rule::exists('people', 'id')],
            'response_type'         => ['sometimes', 'required', Rule::enum(RiskResponseType::class)],
            'response_action'       => ['nullable', 'string'],
            'residual_probability'  => ['nullable', 'integer', 'min:1', 'max:5'],
            'residual_impact'       => ['nullable', 'integer', 'min:1', 'max:5'],
            'status'                => ['sometimes', 'required', Rule::enum(RiskStatus::class)],
            'stage_id'              => ['nullable', 'integer', Rule::exists('stages', 'id')],
        ];
    }
}
