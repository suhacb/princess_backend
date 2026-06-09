<?php

namespace App\Http\Requests\ProjectMember;

use App\Enums\PersonSide;
use App\Enums\ProjectRole;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class ProjectMemberRequest extends DynamicRequest
{
    public function rulesForStore(): array
    {
        $project = $this->route('project');

        return [
            'person_id' => [
                'required',
                'integer',
                Rule::exists('people', 'id'),
                Rule::unique('project_members')->where(
                    fn ($q) => $q->where('project_id', $project->id)
                ),
            ],
            'role' => ['required', Rule::enum(ProjectRole::class)],
            'side' => ['nullable', Rule::enum(PersonSide::class)],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            'role' => ['sometimes', 'required', Rule::enum(ProjectRole::class)],
            'side' => ['nullable', Rule::enum(PersonSide::class)],
        ];
    }
}
