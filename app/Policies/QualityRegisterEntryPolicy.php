<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\QualityRegisterEntry;
use App\Models\User;

class QualityRegisterEntryPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'quality-register:read');
    }

    public function view(User $user, Project $project, QualityRegisterEntry $entry): bool
    {
        return $entry->project_id === $project->id
            && $this->memberCan($user, $project, 'quality-register:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'quality-register:manage');
    }

    public function update(User $user, Project $project, QualityRegisterEntry $entry): bool
    {
        return $entry->project_id === $project->id
            && $this->memberCan($user, $project, 'quality-register:manage');
    }

    public function delete(User $user, Project $project, QualityRegisterEntry $entry): bool
    {
        return $entry->project_id === $project->id
            && $this->memberCan($user, $project, 'quality-register:manage');
    }

    private function memberCan(User $user, Project $project, string $permission): bool
    {
        $member = $this->getMember($user, $project);

        return $member && $member->role->can($permission);
    }

    private function getMember(User $user, Project $project): ?ProjectMember
    {
        if ($user->person_id === null) {
            return null;
        }

        return $project->members()->where('person_id', $user->person_id)->first();
    }
}
