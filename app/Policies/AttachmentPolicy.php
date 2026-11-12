<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

/**
 * Files inherit the rights on their project.
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $model): bool
    {
        return $user->can('view', $model->project);
    }

    public function update(User $user, Attachment $model): bool
    {
        return $user->can('update', $model->project);
    }

    public function delete(User $user, Attachment $model): bool
    {
        return $user->can('update', $model->project);
    }
}
