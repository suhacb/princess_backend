<?php

namespace App\Policies;

use App\Models\CheckpointReport;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class CheckpointReportPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'reports:read');
    }

    public function view(User $user, Project $project, CheckpointReport $report): bool
    {
        return $report->project_id === $project->id
            && $this->memberCan($user, $project, 'reports:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'reports:generate')
            || $this->memberHasRole($user, $project, \App\Enums\ProjectRole::TeamManager);
    }

    public function update(User $user, Project $project, CheckpointReport $report): bool
    {
        return $report->project_id === $project->id
            && (
                $this->memberCan($user, $project, 'reports:generate')
                || $this->isTeamManagerOf($user, $report)
            );
    }

    public function delete(User $user, Project $project, CheckpointReport $report): bool
    {
        return $report->project_id === $project->id
            && $report->isDeletable()
            && (
                $this->memberCan($user, $project, 'reports:generate')
                || $this->isTeamManagerOf($user, $report)
            );
    }

    public function submit(User $user, Project $project, CheckpointReport $report): bool
    {
        return $report->project_id === $project->id
            && (
                $this->memberCan($user, $project, 'reports:generate')
                || $this->isTeamManagerOf($user, $report)
            );
    }

    public function acknowledge(User $user, Project $project, CheckpointReport $report): bool
    {
        return $report->project_id === $project->id
            && $this->memberCan($user, $project, 'reports:generate');
    }

    private function isTeamManagerOf(User $user, CheckpointReport $report): bool
    {
        return $user->person_id !== null
            && $report->workPackage !== null
            && $report->workPackage->team_manager_id === $user->person_id;
    }

    private function memberHasRole(User $user, Project $project, \App\Enums\ProjectRole $role): bool
    {
        $member = $this->getMember($user, $project);
        return $member && $member->role === $role;
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
