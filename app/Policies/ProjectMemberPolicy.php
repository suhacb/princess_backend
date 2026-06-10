<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class ProjectMemberPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'people:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'project-team:manage');
    }

    public function update(User $user, Project $project, ProjectMember $member): bool
    {
        return $this->memberCan($user, $project, 'project-team:manage');
    }

    public function delete(User $user, Project $project, ProjectMember $member): bool
    {
        return $this->memberCan($user, $project, 'project-team:manage');
    }

    private function memberCan(User $user, Project $project, string $permission): bool
    {
        if ($user->person_id === null) {
            return false;
        }
        $member = $project->members()->where('person_id', $user->person_id)->first();
        return $member && $member->role->can($permission);
    }
}
