<?php

namespace App\Models;

use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time spent on a task, either tracked with the timer or entered by hand.
 * A running timer has no end yet; each user has at most one.
 */
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'user_id',
        'started_at',
        'ended_at',
        'seconds',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function running(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    #[Scope]
    protected function finished(Builder $query): void
    {
        $query->whereNotNull('ended_at');
    }

    /**
     * Entries on tasks in the given lists.
     *
     * @param  iterable<int>  $listIds
     */
    #[Scope]
    protected function inLists(Builder $query, iterable $listIds): void
    {
        $query->whereHas('task', fn (Builder $tasks) => $tasks->whereIn('task_list_id', $listIds));
    }

    public function stop(): void
    {
        $now = now();
        $this->update([
            'ended_at' => $now,
            'seconds' => max(1, (int) $this->started_at->diffInSeconds($now)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toView(): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'user' => $this->user->name,
            'date' => $this->started_at->toDateString(),
            'seconds' => $this->seconds,
            'note' => $this->note,
        ];
    }
}
