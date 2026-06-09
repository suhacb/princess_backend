<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->person_id !== null;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'projects:read');
    }

    public function create(User $user): bool
    {
        return $user->person_id !== null;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'projects:update');
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'projects:delete');
    }

    public function setCurrentStage(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'projects:update');
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
