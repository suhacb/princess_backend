<?php

namespace App\Http\Requests\ChangeLog;

use App\Enums\ChangeRequestType;
use App\Enums\ChangeStatus;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class ChangeRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'request_type'     => ['required', Rule::enum(ChangeRequestType::class)],
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'impact_assessment' => ['nullable', 'string'],
            'priority'         => ['nullable', 'string', 'max:50'],
            'issue_id'         => ['nullable', 'integer', Rule::exists('issues', 'id')],
            'implementation_due' => ['nullable', 'date'],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'request_type'     => ['sometimes', 'required', Rule::enum(ChangeRequestType::class)],
            'title'            => ['sometimes', 'required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'impact_assessment' => ['nullable', 'string'],
            'priority'         => ['nullable', 'string', 'max:50'],
            'status'           => ['sometimes', 'required', Rule::enum(ChangeStatus::class)],
            'issue_id'         => ['nullable', 'integer', Rule::exists('issues', 'id')],
            'implementation_due' => ['nullable', 'date'],
            'implemented_at'   => ['nullable', 'date'],
        ];
    }
}
