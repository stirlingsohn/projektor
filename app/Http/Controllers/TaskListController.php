<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Support\Sharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskListController extends Controller
{
    public function show(Request $request, TaskList $list): Response
    {
        Gate::authorize('view', $list);

        $project = $list->project;
        $showsTime = $project->showsTimeTo($request->user());
        $user = $request->user();
        $tasks = $list->tasks()->with('dependencies:id', 'assignees:id')->withCount('comments')
            ->when($showsTime, fn ($query) => $query->withSum(['timeEntries as tracked_seconds' => fn ($entries) => $entries->finished()], 'seconds'))
            ->get();

        return Inertia::render('lists/Show', [
            'list' => $list->only('id', 'project_id', 'name'),
            'ancestors' => $project->ancestors()->push($project)->filter(fn (Project $ancestor) => $user->can('view', $ancestor))->values()->map->only('id', 'name'),
            'people' => $list->people()->map->only('id', 'name', 'avatar'),
            'statuses' => $list->statuses->map->only('id', 'name', 'color', 'is_done'),
            'tasks' => $tasks->map->toView(),
            'showsTime' => $showsTime,
            'attachments' => Attachment::whereIn('task_id', $tasks->modelKeys())->latest()->get()->map->toView(),
            'comments' => Comment::whereIn('task_id', $tasks->modelKeys())->with('user:id,name,avatar_path')->oldest()->get()->map->toView(),
            'can' => [
                'manage' => $user->can('update', $list),
                'editTasks' => $user->can('editTasks', $list),
                'comment' => $user->can('comment', $list),
            ],
            'sharing' => $user->can('update', $list) ? Sharing::of($list) : null,
            'timeEntries' => $showsTime
                ? TimeEntry::finished()->whereIn('task_id', $tasks->modelKeys())->with('user:id,name')->latest('started_at')->get()->map->toView()
                : [],
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $list = $project->taskLists()->create([
            ...$request->validate(['name' => ['required', 'string', 'max:255']]),
            'position' => $project->taskLists()->max('position') + 1,
        ]);

        return to_route('lists.show', $list);
    }

    public function update(Request $request, TaskList $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        $list->update($request->validate(['name' => ['required', 'string', 'max:255']]));

        return back();
    }

    public function destroy(TaskList $list): RedirectResponse
    {
        Gate::authorize('delete', $list);

        $list->delete();

        return to_route('projects.show', $list->project_id);
    }
}
