<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Stage;
use App\Models\User;

class StagePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('stages:read') && $this->isMember($user, $project);
    }

    public function view(User $user, Project $project, Stage $stage): bool
    {
        return $user->can('stages:read') && $this->isMember($user, $project);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('stages:manage') && $this->isMember($user, $project);
    }

    public function update(User $user, Project $project, Stage $stage): bool
    {
        return $user->can('stages:manage') && $this->isMember($user, $project);
    }

    public function delete(User $user, Project $project, Stage $stage): bool
    {
        return $user->can('stages:manage') && $this->isMember($user, $project);
    }

    public function transition(User $user, Project $project, Stage $stage): bool
    {
        return $user->can('stages:transition') && $this->isMember($user, $project);
    }

    private function isMember(User $user, Project $project): bool
    {
        if ($user->person_id === null) {
            return false;
        }

        return $project->members()->where('person_id', $user->person_id)->exists();
    }
}
