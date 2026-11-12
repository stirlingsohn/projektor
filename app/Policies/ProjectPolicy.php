<?php

namespace App\Policies;

use App\Enums\AccessLevel;
use App\Models\Project;
use App\Models\User;

/**
 * The owner manages projects. Clients may open the projects shared with
 * them; a project that only holds a shared list shows just that list.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $user->isOwner() || in_array($project->id, Project::visibleIdsFor($user), true);
    }

    /** See everything inside: lists, documents, files and tracked time. */
    public function viewContents(User $user, Project $project): bool
    {
        return $user->isOwner() || $user->accessToProject($project) !== null;
    }

    /** Write documents and upload files. */
    public function contribute(User $user, Project $project): bool
    {
        return $user->isOwner() || $user->accessToProject($project)?->allows(AccessLevel::Edit) === true;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    /** Rename, move, share and change settings. */
    public function update(User $user, Project $project): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isOwner();
    }
}
