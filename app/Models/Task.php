<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status_id',
        'title',
        'description',
        'start_date',
        'due_date',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep `completed_at` in sync with whether the status counts as done.
        static::saving(function (Task $task) {
            if (! $task->isDirty('status_id')) {
                return;
            }

            // Look the status up again: a loaded `status` relation would still be the old one.
            $isDone = Status::findOrFail($task->status_id)->is_done;
            if ($isDone && ! $task->completed_at) {
                $task->completed_at = now();
            } elseif (! $isDone) {
                $task->completed_at = null;
            }
        });
    }

    /**
     * @return BelongsTo<TaskList, $this>
     */
    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    /**
     * @return BelongsTo<Status, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * The tasks that must be finished before this one can start.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_id')->withTimestamps();
    }

    /**
     * The tasks that can only start once this one is finished.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_id', 'task_id')->withTimestamps();
    }

    /**
     * Whether this task directly or indirectly depends on the given task.
     */
    public function dependsOn(Task $task): bool
    {
        $visited = [];
        $ids = [$this->id];

        while ($ids) {
            $visited = [...$visited, ...$ids];
            $ids = array_diff(DB::table('task_dependencies')->whereIn('task_id', $ids)->pluck('depends_on_id')->all(), $visited);
            if (in_array($task->id, $ids)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Push dependent tasks back, keeping their duration, wherever they would
     * start before the task they depend on has ended. Tasks are never pulled
     * forward, and tasks without dates are left alone.
     */
    public function pushDependents(): void
    {
        for ($queue = [$this]; $task = array_shift($queue);) {
            $end = $task->due_date ?? $task->start_date;
            if (! $end) {
                continue;
            }

            foreach ($task->dependents as $dependent) {
                $start = $dependent->start_date ?? $dependent->due_date;
                $earliestStart = $end->copy()->addDay();
                if (! $start || $start->gte($earliestStart)) {
                    continue;
                }

                $days = (int) $start->diffInDays($earliestStart);
                $dependent->start_date = $dependent->start_date?->copy()->addDays($days);
                $dependent->due_date = $dependent->due_date?->copy()->addDays($days);
                $dependent->save();
                $queue[] = $dependent;
            }
        }
    }

    /**
     * The task as the list, Kanban and Gantt views expect it.
     *
     * @return array<string, mixed>
     */
    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->latest('started_at');
    }

    public function toView(): array
    {
        return [
            'id' => $this->id,
            'status_id' => $this->status_id,
            'title' => $this->title,
            'description' => $this->description,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toJSON(),
            'depends_on' => $this->dependencies->pluck('id'),
            // Only present when loaded with `withSum`, i.e. when the viewer may see time.
            'tracked_seconds' => array_key_exists('tracked_seconds', $this->getAttributes()) ? (int) $this->tracked_seconds : null,
        ];
    }
}
