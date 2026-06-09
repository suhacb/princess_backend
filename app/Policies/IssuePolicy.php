<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class IssuePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'issue-log:read');
    }

    public function view(User $user, Project $project, Issue $issue): bool
    {
        return $issue->project_id === $project->id
            && $this->memberCan($user, $project, 'issue-log:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'issue-log:create');
    }

    public function update(User $user, Project $project, Issue $issue): bool
    {
        return $issue->project_id === $project->id
            && $this->memberCan($user, $project, 'issue-log:manage');
    }

    public function delete(User $user, Project $project, Issue $issue): bool
    {
        return $issue->project_id === $project->id
            && $this->memberCan($user, $project, 'issue-log:manage');
    }

    public function escalate(User $user, Project $project, Issue $issue): bool
    {
        return $issue->project_id === $project->id
            && $this->memberCan($user, $project, 'issue-log:escalate');
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
