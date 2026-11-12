<?php

namespace App\Policies;

use App\Enums\AccessLevel;
use App\Models\TaskList;
use App\Models\User;

/**
 * Clients work with the tasks of lists shared with them, directly or
 * through a project; only the owner changes the list itself.
 */
class TaskListPolicy
{
    public function view(User $user, TaskList $taskList): bool
    {
        return $user->isOwner() || $user->accessToList($taskList) !== null;
    }

    public function comment(User $user, TaskList $taskList): bool
    {
        return $user->isOwner() || $user->accessToList($taskList)?->allows(AccessLevel::Comment) === true;
    }

    /** Create, change, move and delete tasks, and track time on them. */
    public function editTasks(User $user, TaskList $taskList): bool
    {
        return $user->isOwner() || $user->accessToList($taskList)?->allows(AccessLevel::Edit) === true;
    }

    /** Rename the list, change its statuses and share it. */
    public function update(User $user, TaskList $taskList): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, TaskList $taskList): bool
    {
        return $user->isOwner();
    }
}
