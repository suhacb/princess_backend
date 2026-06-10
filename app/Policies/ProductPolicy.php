<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'products:read');
    }

    public function view(User $user, Project $project, Product $product): bool
    {
        return $product->project_id === $project->id
            && $this->memberCan($user, $project, 'products:read');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->memberCan($user, $project, 'products:manage');
    }

    public function update(User $user, Project $project, Product $product): bool
    {
        return $product->project_id === $project->id
            && $this->memberCan($user, $project, 'products:manage');
    }

    public function delete(User $user, Project $project, Product $product): bool
    {
        return $product->project_id === $project->id
            && ! $product->isBaselined()
            && $this->memberCan($user, $project, 'products:manage');
    }

    public function baseline(User $user, Project $project, Product $product): bool
    {
        return $product->project_id === $project->id
            && $this->isProjectManager($user, $project);
    }

    private function isProjectManager(User $user, Project $project): bool
    {
        $member = $this->getMember($user, $project);

        return $member && $member->role === ProjectRole::ProjectManager;
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
