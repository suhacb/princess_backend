<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Requirement;
use App\Models\User;

class RequirementPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:read');
    }

    public function view(User $user, Project $project, Requirement $requirement): bool
    {
        return $requirement->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:manage');
    }

    public function update(User $user, Project $project, Requirement $requirement): bool
    {
        return $requirement->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function delete(User $user, Project $project, Requirement $requirement): bool
    {
        return $requirement->project_id === $project->id
            && $requirement->isDeletable()
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function review(User $user, Project $project, Requirement $requirement): bool
    {
        return $requirement->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function approve(User $user, Project $project, Requirement $requirement): bool
    {
        return $requirement->project_id === $project->id
            && $this->isApprover($user, $project);
    }

    public function reject(User $user, Project $project, Requirement $requirement): bool
    {
        return $requirement->project_id === $project->id
            && $this->isApprover($user, $project);
    }

    public function defer(User $user, Project $project, Requirement $requirement): bool
    {
        return $requirement->project_id === $project->id
            && $this->isProjectManager($user, $project);
    }

    private function isApprover(User $user, Project $project): bool
    {
        $member = $this->getMember($user, $project);
        return $member && in_array($member->role, [
            ProjectRole::ProjectAssurance,
            ProjectRole::Executive,
            ProjectRole::SeniorUser,
            ProjectRole::SeniorSupplier,
        ]);
    }

    private function isProjectManager(User $user, Project $project): bool
    {
        $member = $this->getMember($user, $project);
        return $member && $member->role === ProjectRole::ProjectManager;
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
