<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Support\Sharing;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        $user = $request->user();
        $viewContents = $user->can('viewContents', $project);

        return Inertia::render('projects/Show', [
            'project' => $project->only('id', 'parent_id', 'name', 'color', 'client_can_see_time'),
            'ancestors' => $project->ancestors()->filter(fn (Project $ancestor) => $user->can('view', $ancestor))->values()->map->only('id', 'name'),
            'children' => $project->children()->visibleTo($user)->get()->map->only('id', 'name', 'color'),
            'attachments' => $viewContents ? $project->attachments()->with('task:id,title')->get()->map->toView() : [],
            'trackedSeconds' => $viewContents && $project->showsTimeTo($user) ? $project->trackedSeconds() : null,
            'can' => [
                'manage' => $user->can('update', $project),
                'contribute' => $user->can('contribute', $project),
                'viewContents' => $viewContents,
            ],
            'sharing' => $user->can('update', $project) ? Sharing::of($project) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $attributes = $request->validate($this->rules());

        $project = Project::create([
            ...$attributes,
            'position' => Project::where('parent_id', $attributes['parent_id'] ?? null)->max('position') + 1,
        ]);

        return to_route('projects.show', $project);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($request->validate($this->rules($project)));

        return back();
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $deletedIds = $project->descendantIds()->push($project->id);
        // The database removes the rows, but the stored files need deleting too.
        Attachment::whereIn('project_id', $deletedIds)->each(fn (Attachment $attachment) => $attachment->delete());
        $project->delete();

        $previousRoute = Route::getRoutes()->match(Request::create(url()->previous()));
        if ($previousRoute->getName() !== 'projects.show' || ! $deletedIds->contains($previousRoute->parameter('project'))) {
            return back();
        }

        return $project->parent_id
            ? to_route('projects.show', $project->parent_id)
            : to_route('dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Project $project = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['sometimes', 'required', 'hex_color'],
            'client_can_see_time' => ['sometimes', 'boolean'],
            'parent_id' => [
                'nullable',
                Rule::exists(Project::class, 'id'),
                function (string $attribute, mixed $value, Closure $fail) use ($project) {
                    if ($project && $value && $project->descendantIds()->push($project->id)->contains($value)) {
                        $fail(__('A project cannot be moved into itself or one of its subprojects.'));
                    }
                },
            ],
        ];
    }
}
