<?php

namespace App\Http\Requests\ChangeLog;

use App\Enums\ChangeRequestType;
use App\Enums\ChangeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChangeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'request_type'       => ['sometimes', 'required', Rule::enum(ChangeRequestType::class)],
            'title'              => ['sometimes', 'required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'impact_assessment'  => ['nullable', 'string'],
            'priority'           => ['nullable', 'string', 'max:50'],
            'status'             => ['sometimes', 'required', Rule::enum(ChangeStatus::class)],
            'issue_id'           => ['nullable', 'integer', Rule::exists('issues', 'id')],
            'implementation_due' => ['nullable', 'date'],
            'implemented_at'     => ['nullable', 'date'],
        ];
    }
}
