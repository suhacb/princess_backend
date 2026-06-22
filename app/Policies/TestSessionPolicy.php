<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TestSession;
use App\Models\User;

class TestSessionPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:read');
    }

    public function view(User $user, Project $project, TestSession $session): bool
    {
        return $session->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:manage');
    }

    public function update(User $user, Project $project, TestSession $session): bool
    {
        return $session->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function delete(User $user, Project $project, TestSession $session): bool
    {
        return $session->project_id === $project->id
            && $session->isDeletable()
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function start(User $user, Project $project, TestSession $session): bool
    {
        return $session->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function complete(User $user, Project $project, TestSession $session): bool
    {
        return $session->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function cancel(User $user, Project $project, TestSession $session): bool
    {
        return $session->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function updateResult(User $user, Project $project, TestSession $session): bool
    {
        if ($session->project_id !== $project->id) {
            return false;
        }

        // Assigned tester can update their own results
        if ($user->person_id === $session->tester_id) {
            return $this->getMember($user, $project) !== null;
        }

        return $this->memberCan($user, $project, 'qa:manage');
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
