<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'meetings:read');
    }

    public function view(User $user, Project $project, Meeting $meeting): bool
    {
        return $meeting->project_id === $project->id
            && $this->memberCan($user, $project, 'meetings:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'meetings:manage');
    }

    public function update(User $user, Project $project, Meeting $meeting): bool
    {
        return $meeting->project_id === $project->id
            && $this->memberCan($user, $project, 'meetings:manage');
    }

    public function delete(User $user, Project $project, Meeting $meeting): bool
    {
        return $meeting->project_id === $project->id
            && $this->memberCan($user, $project, 'meetings:manage');
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
