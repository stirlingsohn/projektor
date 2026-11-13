<?php

namespace App\Support;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The open tasks assigned to someone, sorted into when they are due. A
 * task counts for a day or week when its date range touches it: it
 * starts, ends or runs through it. Tasks that ended earlier are overdue.
 */
class MyTasks
{
    public const BUCKETS = ['overdue', 'today', 'week', 'later', 'undated'];

    private CarbonImmutable $today;

    private CarbonImmutable $endOfWeek;

    public function __construct(private User $user, ?CarbonImmutable $today = null)
    {
        $this->today = ($today ?? CarbonImmutable::today())->startOfDay();
        $this->endOfWeek = $this->today->endOfWeek()->startOfDay();
    }

    /**
     * @return Collection<string, Collection<int, Task>>
     */
    public function grouped(): Collection
    {
        $groups = $this->tasks()->groupBy(fn (Task $task) => $this->bucket($task));

        return collect(self::BUCKETS)->mapWithKeys(fn (string $bucket) => [$bucket => $groups->get($bucket, collect())->values()]);
    }

    /**
     * What is due today, plus overdue tasks so nothing gets lost.
     *
     * @return Collection<string, Collection<int, Task>>
     */
    public function forToday(): Collection
    {
        return $this->grouped()->only(['overdue', 'today'])->filter->isNotEmpty();
    }

    /**
     * What is due this week (from today on), plus overdue tasks.
     *
     * @return Collection<string, Collection<int, Task>>
     */
    public function forThisWeek(): Collection
    {
        return $this->grouped()->only(['overdue', 'today', 'week'])->filter->isNotEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    public static function toView(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'start_date' => $task->start_date?->toDateString(),
            'due_date' => $task->due_date?->toDateString(),
            'status' => $task->status->only('name', 'color'),
            'list' => $task->taskList->only('id', 'name'),
            'project' => $task->taskList->project->only('id', 'name', 'color'),
        ];
    }

    private function bucket(Task $task): string
    {
        $start = $task->start_date ?? $task->due_date;
        $end = $task->due_date ?? $task->start_date;

        return match (true) {
            $start === null => 'undated',
            $end->lt($this->today) => 'overdue',
            $start->lte($this->today) => 'today',
            $start->lte($this->endOfWeek) => 'week',
            default => 'later',
        };
    }

    /**
     * @return Collection<int, Task>
     */
    private function tasks(): Collection
    {
        return Task::query()
            ->whereNull('completed_at')
            ->whereHas('assignees', fn ($users) => $users->whereKey($this->user->id))
            ->whereIn('task_list_id', TaskList::visibleTo($this->user)->select('id'))
            ->with(['status:id,name,color', 'taskList:id,name,project_id', 'taskList.project:id,name,color'])
            ->get()
            ->sortBy(fn (Task $task) => [($task->due_date ?? $task->start_date)?->toDateString() ?? '9999', $task->position])
            ->values();
    }
}
