<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects:read');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can('projects:read') && $this->isMember($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->can('projects:create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('projects:update') && $this->isMember($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects:delete') && $this->isMember($user, $project);
    }

    public function setCurrentStage(User $user, Project $project): bool
    {
        return $user->can('projects:update') && $this->isMember($user, $project);
    }

    private function isMember(User $user, Project $project): bool
    {
        if ($user->person_id === null) {
            return false;
        }

        return $project->members()->where('person_id', $user->person_id)->exists();
    }
}
