<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Documents inherit the rights on their project.
 */
class DocumentPolicy
{
    public function view(User $user, Document $model): bool
    {
        return $user->can('view', $model->project);
    }

    public function update(User $user, Document $model): bool
    {
        return $user->can('update', $model->project);
    }

    public function delete(User $user, Document $model): bool
    {
        return $user->can('update', $model->project);
    }
}
