<?php

namespace App\Http\Requests\ProjectMember;

use App\Enums\PersonSide;
use App\Enums\ProjectRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'required', Rule::enum(ProjectRole::class)],
            'side' => ['nullable', Rule::enum(PersonSide::class)],
        ];
    }
}
