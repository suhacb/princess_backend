<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'tasks:read');
    }

    public function view(User $user, Project $project, Task $task): bool
    {
        return $task->project_id === $project->id
            && $this->memberCan($user, $project, 'tasks:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'tasks:manage');
    }

    public function update(User $user, Project $project, Task $task): bool
    {
        if ($task->project_id !== $project->id) {
            return false;
        }

        if ($this->memberCan($user, $project, 'tasks:manage')) {
            return true;
        }

        return $this->memberCan($user, $project, 'tasks:update-own')
            && $task->assigned_to === $user->person_id;
    }

    public function delete(User $user, Project $project, Task $task): bool
    {
        return $task->project_id === $project->id
            && $this->memberCan($user, $project, 'tasks:manage');
    }

    public function history(User $user, Project $project, Task $task): bool
    {
        return $task->project_id === $project->id
            && $this->memberCan($user, $project, 'tasks:read');
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
