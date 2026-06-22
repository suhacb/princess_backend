<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\QaDocument;
use App\Models\User;

class QaDocumentPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:read');
    }

    public function view(User $user, Project $project, QaDocument $document): bool
    {
        return $document->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:manage');
    }

    public function update(User $user, Project $project, QaDocument $document): bool
    {
        return $document->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function delete(User $user, Project $project, QaDocument $document): bool
    {
        return $document->project_id === $project->id
            && $document->isDeletable()
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function sendForReview(User $user, Project $project, QaDocument $document): bool
    {
        return $document->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function reject(User $user, Project $project, QaDocument $document): bool
    {
        return $document->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function confirm(User $user, Project $project, QaDocument $document): bool
    {
        return $document->project_id === $project->id
            && $this->isConfirmer($user, $project);
    }

    private function isConfirmer(User $user, Project $project): bool
    {
        $member = $this->getMember($user, $project);
        return $member && in_array($member->role, [
            ProjectRole::ProjectAssurance,
            ProjectRole::ProjectManager,
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
