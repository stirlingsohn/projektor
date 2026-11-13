<?php

namespace App\Support;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Project;
use App\Models\TaskList;

/**
 * Who has access to a project or list, for the owner's share dialog.
 */
class Sharing
{
    /**
     * @return array<string, mixed>
     */
    public static function of(Project|TaskList $target): array
    {
        $column = $target instanceof Project ? 'project_id' : 'task_list_id';

        // Access that comes from a project further up is shown, but changed there.
        $project = $target instanceof Project ? $target->parent : $target->project;
        $sources = $project ? $project->ancestors()->push($project)->keyBy('id') : collect();

        return [
            'members' => Membership::where($column, $target->id)->with('user:id,name,email,avatar_path')->get()
                ->sortBy('user.name')->values()
                ->map(fn (Membership $membership) => [
                    'id' => $membership->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'avatar' => $membership->user->avatar,
                    'level' => $membership->level,
                ]),
            'inherited' => Membership::whereIn('project_id', $sources->keys())->with('user:id,name,email,avatar_path')->get()
                ->map(fn (Membership $membership) => [
                    'id' => $membership->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'avatar' => $membership->user->avatar,
                    'level' => $membership->level,
                    'source' => $sources[$membership->project_id]->name,
                    'source_id' => $membership->project_id,
                ]),
            'invitations' => Invitation::pending()->where($column, $target->id)->latest()->get()
                ->map(fn (Invitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'level' => $invitation->level,
                    'expires_at' => $invitation->expires_at->toJSON(),
                ]),
        ];
    }
}
