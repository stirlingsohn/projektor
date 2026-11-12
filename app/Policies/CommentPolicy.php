<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/**
 * People edit their own comments; the owner may also remove anyone's.
 */
class CommentPolicy
{
    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id && $user->can('comment', $comment->task->taskList);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->isOwner() || $this->update($user, $comment);
    }
}
