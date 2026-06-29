<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Models\User;

class DocumentTemplatePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:manage');
    }

    public function update(User $user, Project $project, DocumentTemplate $template): bool
    {
        return $template->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function delete(User $user, Project $project, DocumentTemplate $template): bool
    {
        return $template->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function upload(User $user, Project $project, DocumentTemplate $template): bool
    {
        return $template->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    private function memberCan(User $user, Project $project, string $permission): bool
    {
        if ($user->person_id === null) {
            return false;
        }

        $member = $project->members()->where('person_id', $user->person_id)->first();

        return $member && $member->role->can($permission);
    }
}
