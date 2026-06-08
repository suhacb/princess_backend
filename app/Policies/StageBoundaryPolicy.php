<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Stage;
use App\Models\StageBoundary;
use App\Models\User;

class StageBoundaryPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('boundaries:read') && $this->isMember($user, $project);
    }

    public function view(User $user, Project $project, Stage $stage, StageBoundary $boundary): bool
    {
        return $user->can('boundaries:read') && $this->isMember($user, $project);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('boundaries:manage') && $this->isMember($user, $project);
    }

    public function update(User $user, Project $project, Stage $stage, StageBoundary $boundary): bool
    {
        return $user->can('boundaries:manage') && $this->isMember($user, $project);
    }

    public function delete(User $user, Project $project, Stage $stage, StageBoundary $boundary): bool
    {
        return $user->can('boundaries:manage') && $this->isMember($user, $project);
    }

    public function submit(User $user, Project $project, Stage $stage, StageBoundary $boundary): bool
    {
        return $user->can('boundaries:submit') && $this->isMember($user, $project);
    }

    public function approveReject(User $user, Project $project, Stage $stage, StageBoundary $boundary): bool
    {
        return $user->can('boundaries:approve-reject') && $this->isMember($user, $project);
    }

    private function isMember(User $user, Project $project): bool
    {
        if ($user->person_id === null) {
            return false;
        }

        return $project->members()->where('person_id', $user->person_id)->exists();
    }
}
