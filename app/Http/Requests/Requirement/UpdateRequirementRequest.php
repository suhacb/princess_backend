<?php

namespace App\Http\Requests\Requirement;

use App\Enums\RequirementPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequirementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'role'        => ['nullable', 'string', 'max:255'],
            'action'      => ['nullable', 'string', 'max:255'],
            'benefit'     => ['nullable', 'string'],
            'priority'    => ['sometimes', 'required', Rule::enum(RequirementPriority::class)],
            'source'      => ['nullable', 'string', 'max:255'],
            'owner_id'    => ['nullable', 'integer', Rule::exists('people', 'id')],
            'parent_id'   => ['nullable', 'integer', Rule::exists('requirements', 'id')],
        ];
    }
}
