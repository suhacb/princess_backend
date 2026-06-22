<?php

namespace App\Http\Requests\Requirement;

use App\Enums\RequirementPriority;
use App\Enums\RequirementType;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class RequirementRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        return [
            'type'        => ['required', Rule::enum(RequirementType::class)],
            'parent_id'   => ['nullable', 'integer', Rule::exists('requirements', 'id')],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'role'        => [
                Rule::requiredIf(fn () => $this->type === RequirementType::UserStory->value),
                'nullable', 'string', 'max:255',
            ],
            'action' => [
                Rule::requiredIf(fn () => $this->type === RequirementType::UserStory->value),
                'nullable', 'string', 'max:255',
            ],
            'benefit' => [
                Rule::requiredIf(fn () => $this->type === RequirementType::UserStory->value),
                'nullable', 'string',
            ],
            'priority' => ['required', Rule::enum(RequirementPriority::class)],
            'source'   => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'integer', Rule::exists('people', 'id')],
        ];
    }

    public function rulesForUpdate(): array
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
