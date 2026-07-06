<?php

namespace App\Http\Requests\IssueLog;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueLogRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'issue_type'  => ['sometimes', 'required', Rule::enum(IssueType::class)],
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority'    => ['sometimes', 'required', Rule::enum(IssuePriority::class)],
            'stage_id'    => ['nullable', 'integer', Rule::exists('stages', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('people', 'id')],
        ];
    }
}
