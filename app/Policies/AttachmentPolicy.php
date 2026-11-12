<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

/**
 * Files can be seen with the project's contents, or with their task's list
 * when only that list is shared. Clients may delete files they uploaded.
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return $user->can('viewContents', $attachment->project)
            || ($attachment->task !== null && $user->can('view', $attachment->task->taskList));
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return $attachment->user_id === $user->id && ($attachment->task
            ? $user->can('editTasks', $attachment->task->taskList)
            : $user->can('contribute', $attachment->project));
    }
}
