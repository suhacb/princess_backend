<?php

namespace App\Http\Requests\RiskLog;

use App\Enums\RiskProximity;
use App\Enums\RiskResponseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'category'         => ['nullable', 'string', 'max:255'],
            'probability'      => ['required', 'integer', 'min:1', 'max:5'],
            'impact'           => ['required', 'integer', 'min:1', 'max:5'],
            'proximity'        => ['required', Rule::enum(RiskProximity::class)],
            'risk_owner'       => ['required', 'integer', Rule::exists('people', 'id')],
            'response_type'    => ['required', Rule::enum(RiskResponseType::class)],
            'response_action'  => ['nullable', 'string'],
            'stage_id'         => ['nullable', 'integer', Rule::exists('stages', 'id')],
        ];
    }
}
