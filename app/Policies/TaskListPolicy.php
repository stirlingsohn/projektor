<?php

namespace App\Policies;

use App\Models\TaskList;
use App\Models\User;

/**
 * Lists, their statuses and their tasks inherit the rights on their project
 * until list-level invitations arrive in a later milestone.
 */
class TaskListPolicy
{
    public function view(User $user, TaskList $taskList): bool
    {
        return $user->can('view', $taskList->project);
    }

    public function update(User $user, TaskList $taskList): bool
    {
        return $user->can('update', $taskList->project);
    }

    public function delete(User $user, TaskList $taskList): bool
    {
        return $user->can('update', $taskList->project);
    }
}
