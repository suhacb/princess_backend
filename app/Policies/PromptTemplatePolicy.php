<?php

namespace App\Policies;

use App\Models\PromptTemplate;
use App\Models\User;

class PromptTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->person_id !== null;
    }

    public function view(User $user, PromptTemplate $promptTemplate): bool
    {
        return $user->person_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->person_id !== null;
    }
}
