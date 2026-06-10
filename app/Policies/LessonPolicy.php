<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class LessonPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'lessons-log:read');
    }

    public function view(User $user, Project $project, Lesson $lesson): bool
    {
        return $lesson->project_id === $project->id
            && $this->memberCan($user, $project, 'lessons-log:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'lessons-log:create');
    }

    public function update(User $user, Project $project, Lesson $lesson): bool
    {
        return $lesson->project_id === $project->id
            && $this->memberCan($user, $project, 'lessons-log:manage');
    }

    public function delete(User $user, Project $project, Lesson $lesson): bool
    {
        return $lesson->project_id === $project->id
            && $this->memberCan($user, $project, 'lessons-log:manage');
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
