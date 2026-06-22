<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'plans:read');
    }

    public function view(User $user, Project $project, Plan $plan): bool
    {
        return $plan->project_id === $project->id
            && $this->memberCan($user, $project, 'plans:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'plans:manage');
    }

    public function update(User $user, Project $project, Plan $plan): bool
    {
        return $plan->project_id === $project->id
            && $this->memberCan($user, $project, 'plans:manage');
    }

    public function delete(User $user, Project $project, Plan $plan): bool
    {
        return $plan->project_id === $project->id
            && $plan->isDeletable()
            && $this->memberCan($user, $project, 'plans:manage');
    }

    public function approve(User $user, Project $project, Plan $plan): bool
    {
        return $plan->project_id === $project->id
            && $this->isBoardRole($user, $project);
    }

    private function isBoardRole(User $user, Project $project): bool
    {
        $member = $this->getMember($user, $project);

        return $member && in_array($member->role, [
            ProjectRole::Executive,
            ProjectRole::SeniorUser,
            ProjectRole::SeniorSupplier,
        ]);
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
