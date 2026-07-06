<?php

namespace App\Http\Requests\HighlightReport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHighlightReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'stage_id'             => ['nullable', 'integer', Rule::exists('stages', 'id')],
            'title'                => ['sometimes', 'required', 'string', 'max:255'],
            'period_from'          => ['sometimes', 'required', 'date'],
            'period_to'            => ['sometimes', 'required', 'date', 'after_or_equal:period_from'],
            'budget_status'        => ['nullable', Rule::in(['green', 'amber', 'red'])],
            'schedule_status'      => ['nullable', Rule::in(['green', 'amber', 'red'])],
            'this_period_work'     => ['sometimes', 'required', 'string'],
            'next_period_work'     => ['sometimes', 'required', 'string'],
            'issues_summary'       => ['nullable', 'string'],
            'risks_summary'        => ['nullable', 'string'],
            'quality_summary'      => ['nullable', 'string'],
            'business_case_review' => ['nullable', 'string'],
            'forecast_finish'      => ['nullable', 'date'],
        ];
    }
}
