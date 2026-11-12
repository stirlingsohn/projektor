<?php

namespace App\Models\Concerns;

use App\Enums\AccessLevel;
use App\Models\Membership;
use App\Models\Project;
use App\Models\TaskList;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Works out what a user may see and do. The owner may do everything;
 * clients only get what their memberships grant. Access to a project
 * extends to all of its subprojects and lists.
 *
 * Results are kept for the rest of the request.
 */
trait HasAccess
{
    /** @var array{projects: array<int, AccessLevel>, lists: array<int, AccessLevel>}|null */
    private ?array $access = null;

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function accessToProject(Project $project): ?AccessLevel
    {
        return $this->access()['projects'][$project->id] ?? null;
    }

    public function accessToList(TaskList $list): ?AccessLevel
    {
        $access = $this->access();

        return AccessLevel::max($access['projects'][$list->project_id] ?? null, $access['lists'][$list->id] ?? null);
    }

    /**
     * Projects whose contents the client may see, with the level for each.
     *
     * @return array<int, AccessLevel>
     */
    public function projectAccess(): array
    {
        return $this->access()['projects'];
    }

    /**
     * Lists the client was invited to directly.
     *
     * @return array<int, AccessLevel>
     */
    public function listAccess(): array
    {
        return $this->access()['lists'];
    }

    /** Forget the cached access, e.g. after memberships changed. */
    public function refreshAccess(): void
    {
        $this->access = null;
    }

    /**
     * @return array{projects: array<int, AccessLevel>, lists: array<int, AccessLevel>}
     */
    private function access(): array
    {
        if ($this->access !== null) {
            return $this->access;
        }

        $memberships = $this->memberships()->get(['project_id', 'task_list_id', 'level']);
        $projects = [];
        foreach ($memberships->whereNotNull('project_id') as $membership) {
            $projects[$membership->project_id] = AccessLevel::max($projects[$membership->project_id] ?? null, $membership->level);
        }

        // Pass access down to subprojects, level by level.
        $parents = $projects;
        while ($parents) {
            $children = Project::whereIn('parent_id', array_keys($parents))->get(['id', 'parent_id']);
            $parents = [];
            foreach ($children as $child) {
                $level = AccessLevel::max($projects[$child->id] ?? null, $projects[$child->parent_id]);
                if (($projects[$child->id] ?? null) !== $level) {
                    $projects[$child->id] = $parents[$child->id] = $level;
                }
            }
        }

        $lists = [];
        foreach ($memberships->whereNotNull('task_list_id') as $membership) {
            $lists[$membership->task_list_id] = $membership->level;
        }

        return $this->access = ['projects' => $projects, 'lists' => $lists];
    }
}
