<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The figures on a project page: progress of its lists, the tasks due next
 * and the tracked time. Subprojects count in, but only the lists the
 * viewer may open.
 */
class ProjectDashboard
{
    private const UPCOMING_LIMIT = 8;

    private Collection $lists;

    public function __construct(private Project $project, private User $user)
    {
        $this->lists = TaskList::visibleTo($user)
            ->whereIn('project_id', $project->descendantIds()->push($project->id))
            ->with('project:id,name')
            ->get(['id', 'project_id', 'name']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $showsTime): array
    {
        return [
            'summary' => $this->summary(),
            'listStats' => $this->listStats(),
            'upcoming' => $this->upcoming(),
            'time' => $showsTime ? $this->time() : null,
        ];
    }

    /** @return Builder<Task> */
    private function tasks(): Builder
    {
        return Task::whereIn('task_list_id', $this->lists->modelKeys());
    }

    /**
     * @return array{open: int, overdue: int, done: int, total: int}
     */
    private function summary(): array
    {
        $today = today()->toDateString();
        $row = $this->tasks()->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(completed_at) as done')
            ->selectRaw('sum(case when completed_at is null and due_date < ? then 1 else 0 end) as overdue', [$today])
            ->first();

        return [
            'open' => (int) $row->total - (int) $row->done,
            'overdue' => (int) $row->overdue,
            'done' => (int) $row->done,
            'total' => (int) $row->total,
        ];
    }

    /**
     * Progress of the project's own lists, keyed by list id.
     *
     * @return array<int, array{total: int, done: int, overdue: int, next_due: ?string}>
     */
    private function listStats(): array
    {
        $today = today()->toDateString();
        $ownListIds = $this->lists->where('project_id', $this->project->id)->modelKeys();

        return Task::whereIn('task_list_id', $ownListIds)->toBase()
            ->groupBy('task_list_id')
            ->select('task_list_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('count(completed_at) as done')
            ->selectRaw('sum(case when completed_at is null and due_date < ? then 1 else 0 end) as overdue', [$today])
            ->selectRaw('min(case when completed_at is null then due_date end) as next_due')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->task_list_id => [
                'total' => (int) $row->total,
                'done' => (int) $row->done,
                'overdue' => (int) $row->overdue,
                'next_due' => $row->next_due ? substr($row->next_due, 0, 10) : null,
            ]])
            ->all();
    }

    /**
     * Open tasks with a due date, overdue ones first.
     *
     * @return list<array<string, mixed>>
     */
    private function upcoming(): array
    {
        return $this->tasks()
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->with('status:id,name,color')
            ->orderBy('due_date')
            ->orderBy('position')
            ->limit(self::UPCOMING_LIMIT)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'due_date' => $task->due_date->toDateString(),
                'status' => $task->status->only('name', 'color'),
                'list' => $this->listLabel($this->lists->find($task->task_list_id)),
            ])
            ->all();
    }

    /**
     * @return array{total: int, week: int, lists: list<array{id: int, name: string, project: ?string, seconds: int}>}
     */
    private function time(): array
    {
        $entries = fn () => TimeEntry::finished()->inLists($this->lists->modelKeys());

        $perList = $entries()
            ->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
            ->groupBy('tasks.task_list_id')
            ->selectRaw('tasks.task_list_id, sum(time_entries.seconds) as seconds')
            ->toBase()
            ->get()
            ->map(fn ($row) => [...$this->listLabel($this->lists->find($row->task_list_id)), 'seconds' => (int) $row->seconds])
            ->sortByDesc('seconds')
            ->values();

        return [
            'total' => $perList->sum('seconds'),
            'week' => (int) $entries()->where('started_at', '>=', now()->startOfWeek())->sum('seconds'),
            'lists' => $perList->all(),
        ];
    }

    /**
     * The list name, with the subproject when the list lives in one.
     *
     * @return array{id: int, name: string, project: ?string}
     */
    private function listLabel(TaskList $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'project' => $list->project_id === $this->project->id ? null : $list->project->name,
        ];
    }
}
