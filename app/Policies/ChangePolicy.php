<?php

namespace App\Policies;

use App\Models\Change;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class ChangePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'change-log:read');
    }

    public function view(User $user, Project $project, Change $change): bool
    {
        return $change->project_id === $project->id
            && $this->memberCan($user, $project, 'change-log:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'change-log:create');
    }

    public function update(User $user, Project $project, Change $change): bool
    {
        return $change->project_id === $project->id
            && $this->memberCan($user, $project, 'change-log:manage');
    }

    public function delete(User $user, Project $project, Change $change): bool
    {
        return $change->project_id === $project->id
            && $this->memberCan($user, $project, 'change-log:manage');
    }

    public function approve(User $user, Project $project, Change $change): bool
    {
        if ($change->project_id !== $project->id) {
            return false;
        }

        $member = $this->getMember($user, $project);
        if (!$member) {
            return false;
        }

        return $member->role->can('change-log:approve-major')
            || $member->role->can('change-log:approve-minor');
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
