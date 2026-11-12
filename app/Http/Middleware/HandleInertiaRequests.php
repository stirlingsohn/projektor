<?php

namespace App\Http\Middleware;

use App\Models\Document;
use App\Models\Project;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    /**
     * The timer the user has running, shown in the header on every page.
     *
     * @return array<string, mixed>|null
     */
    private function runningTimer(User $user): ?array
    {
        $entry = $user->timeEntries()->running()->with('task:id,task_list_id,title')->first();

        return $entry ? [
            'id' => $entry->id,
            'task_id' => $entry->task_id,
            'task_title' => $entry->task->title,
            'list_id' => $entry->task->task_list_id,
            'started_at' => $entry->started_at->toJSON(),
        ] : null;
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'translations' => fn () => $this->translations(),
            'timer' => fn () => $user ? $this->runningTimer($user) : null,
            'projects' => fn () => $user
                ? Project::visibleTo($user)
                    ->orderBy('position')
                    ->orderBy('name')
                    ->get(['id', 'parent_id', 'name', 'color', 'client_can_see_time'])
                : [],
            'documents' => fn () => $user
                ? Document::whereHas('project', fn (Builder $query) => $query->visibleTo($user))
                    ->orderBy('position')
                    ->get(['id', 'project_id', 'title'])
                : [],
            'maxUploadMb' => config('projektor.max_upload_mb'),
            'lists' => fn () => $user
                ? TaskList::whereHas('project', fn (Builder $query) => $query->visibleTo($user))
                    ->orderBy('position')
                    ->get(['id', 'project_id', 'name'])
                : [],
        ];
    }

    /**
     * The JSON translations for the current locale, used by `t()` in the frontend.
     *
     * @return array<string, string>
     */
    private function translations(): array
    {
        $path = lang_path(app()->getLocale().'.json');

        return File::exists($path) ? File::json($path) : [];
    }
}
