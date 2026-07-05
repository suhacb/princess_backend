<?php

namespace App\Policies;

use App\Enums\QaDocumentStatus;
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
            && $document->status === QaDocumentStatus::Draft
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function openEditorSession(User $user, Project $project, QaDocument $document): bool
    {
        return $document->project_id === $project->id
            && $document->status === QaDocumentStatus::Draft
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function upload(User $user, Project $project, QaDocument $document): bool
    {
        return $document->project_id === $project->id
            && $document->status === QaDocumentStatus::Draft
            && $this->memberCan($user, $project, 'qa:manage');
    }

    public function download(User $user, Project $project, QaDocument $document): bool
    {
        return $document->project_id === $project->id
            && $this->memberCan($user, $project, 'qa:read');
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
