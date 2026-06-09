<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Stage;
use App\Models\StageBoundary;
use App\Models\User;

class StageBoundaryPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'boundaries:read');
    }

    public function view(User $user, Project $project, Stage $stage, StageBoundary $boundary): bool
    {
        return $this->memberCan($user, $project, 'boundaries:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'boundaries:manage');
    }

    public function update(User $user, Project $project, Stage $stage, StageBoundary $boundary): bool
    {
        return $this->memberCan($user, $project, 'boundaries:manage');
    }

    public function delete(User $user, Project $project, Stage $stage, StageBoundary $boundary): bool
    {
        return $this->memberCan($user, $project, 'boundaries:manage');
    }

    public function submit(User $user, Project $project, Stage $stage, StageBoundary $boundary): bool
    {
        return $this->memberCan($user, $project, 'boundaries:submit');
    }

    public function approveReject(User $user, Project $project, Stage $stage, StageBoundary $boundary): bool
    {
        return $this->memberCan($user, $project, 'boundaries:approve-reject');
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
