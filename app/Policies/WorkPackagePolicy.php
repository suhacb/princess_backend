<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkPackage;

class WorkPackagePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'plans:read');
    }

    public function view(User $user, Project $project, WorkPackage $workPackage): bool
    {
        return $workPackage->project_id === $project->id
            && $this->memberCan($user, $project, 'plans:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'plans:manage');
    }

    public function update(User $user, Project $project, WorkPackage $workPackage): bool
    {
        return $workPackage->project_id === $project->id
            && $this->memberCan($user, $project, 'plans:manage');
    }

    public function delete(User $user, Project $project, WorkPackage $workPackage): bool
    {
        return $workPackage->project_id === $project->id
            && $workPackage->isDeletable()
            && $this->memberCan($user, $project, 'plans:manage');
    }

    public function authorize(User $user, Project $project, WorkPackage $workPackage): bool
    {
        return $workPackage->project_id === $project->id
            && $this->isProjectManager($user, $project);
    }

    public function accept(User $user, Project $project, WorkPackage $workPackage): bool
    {
        return $workPackage->project_id === $project->id
            && $user->person_id === $workPackage->team_manager_id;
    }

    public function complete(User $user, Project $project, WorkPackage $workPackage): bool
    {
        return $workPackage->project_id === $project->id
            && $user->person_id === $workPackage->team_manager_id;
    }

    public function cancel(User $user, Project $project, WorkPackage $workPackage): bool
    {
        return $workPackage->project_id === $project->id
            && $this->isProjectManager($user, $project);
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
