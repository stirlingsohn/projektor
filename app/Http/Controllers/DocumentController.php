<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function show(Request $request, Document $document): Response
    {
        Gate::authorize('view', $document);

        $project = $document->project;

        return Inertia::render('documents/Show', [
            'document' => [
                ...$document->only('id', 'project_id', 'title', 'content'),
                'updated_at' => $document->updated_at->toJSON(),
            ],
            'ancestors' => $project->ancestors()->push($project)->filter(fn (Project $ancestor) => $request->user()->can('view', $ancestor))->values()->map->only('id', 'name'),
            'canEdit' => $request->user()->can('update', $document),
        ]);
    }

    /** Create an empty document and open it. */
    public function store(Project $project): RedirectResponse
    {
        Gate::authorize('contribute', $project);

        $document = $project->documents()->create([
            'title' => __('Untitled document'),
            'position' => $project->documents()->max('position') + 1,
        ]);

        return to_route('documents.show', $document);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        Gate::authorize('update', $document);

        $document->update($request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'nullable', 'array'],
        ]));

        return back();
    }

    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $document->delete();

        return to_route('projects.show', $document->project_id);
    }
}
