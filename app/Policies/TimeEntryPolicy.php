<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

/**
 * Whoever may edit a task may track time on it; entries can be changed by
 * the person who made them and by the owner.
 */
class TimeEntryPolicy
{
    public function update(User $user, TimeEntry $timeEntry): bool
    {
        return $user->can('editTasks', $timeEntry->task->taskList)
            && ($timeEntry->user_id === $user->id || $user->isOwner());
    }

    public function delete(User $user, TimeEntry $timeEntry): bool
    {
        return $this->update($user, $timeEntry);
    }
}
