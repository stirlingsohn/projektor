<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Only the owner manages projects for now; client access through
 * invitations follows in a later milestone.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return Project::visibleTo($user)->whereKey($project->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isOwner();
    }
}
