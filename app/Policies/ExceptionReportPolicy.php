<?php

namespace App\Policies;

use App\Models\ExceptionReport;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class ExceptionReportPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'reports:read');
    }

    public function view(User $user, Project $project, ExceptionReport $report): bool
    {
        return $report->project_id === $project->id
            && $this->memberCan($user, $project, 'reports:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'reports:generate');
    }

    public function update(User $user, Project $project, ExceptionReport $report): bool
    {
        return $report->project_id === $project->id
            && $this->memberCan($user, $project, 'reports:generate');
    }

    public function delete(User $user, Project $project, ExceptionReport $report): bool
    {
        return $report->project_id === $project->id
            && $report->isDeletable()
            && $this->memberCan($user, $project, 'reports:generate');
    }

    public function submit(User $user, Project $project, ExceptionReport $report): bool
    {
        return $report->project_id === $project->id
            && $this->memberCan($user, $project, 'reports:generate');
    }

    public function close(User $user, Project $project, ExceptionReport $report): bool
    {
        return $report->project_id === $project->id
            && $this->memberCan($user, $project, 'reports:approve');
    }

    public function raiseException(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'reports:generate');
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
