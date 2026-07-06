<?php

namespace App\Http\Requests\ChangeLog;

use App\Enums\ChangeRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChangeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'request_type'       => ['required', Rule::enum(ChangeRequestType::class)],
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'impact_assessment'  => ['nullable', 'string'],
            'priority'           => ['nullable', 'string', 'max:50'],
            'issue_id'           => ['nullable', 'integer', Rule::exists('issues', 'id')],
            'implementation_due' => ['nullable', 'date'],
        ];
    }
}
