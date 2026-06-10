<?php

namespace App\Http\Requests\IssueLog;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class IssueLogRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'issue_type'  => ['required', Rule::enum(IssueType::class)],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority'    => ['required', Rule::enum(IssuePriority::class)],
            'stage_id'    => ['nullable', 'integer', Rule::exists('stages', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('people', 'id')],
        ];
    }

    public function rulesForUpdate(): array
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
