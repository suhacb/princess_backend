<?php

namespace App\Policies;

use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\QaDocument;
use App\Models\User;

class DocumentVersionPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'qa:read');
    }

    public function revert(User $user, Project $project, QaDocument $document): bool
    {
        return $document->project_id === $project->id
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
