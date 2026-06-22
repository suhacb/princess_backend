<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TestSessionPlan;
use App\Models\User;

class TestSessionPlanPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:read');
    }

    public function view(User $user, Project $project, TestSessionPlan $plan): bool
    {
        return $plan->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:manage');
    }

    public function update(User $user, Project $project, TestSessionPlan $plan): bool
    {
        return $plan->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function delete(User $user, Project $project, TestSessionPlan $plan): bool
    {
        return $plan->project_id === $project->id
            && $plan->isDeletable()
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function activate(User $user, Project $project, TestSessionPlan $plan): bool
    {
        return $plan->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function complete(User $user, Project $project, TestSessionPlan $plan): bool
    {
        return $plan->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function cancel(User $user, Project $project, TestSessionPlan $plan): bool
    {
        return $plan->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
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
