<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\TaskListFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A to-do list inside a project, with its own configurable statuses.
 */
class TaskList extends Model
{
    /** @use HasFactory<TaskListFactory> */
    use HasFactory;

    /**
     * The statuses every new list starts with, as `[name, color, is_done]`.
     */
    public const DEFAULT_STATUSES = [
        ['Open', '#94a3b8', false],
        ['In progress', '#0ea5e9', false],
        ['In review', '#eab308', false],
        ['Done', '#22c55e', true],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'position',
    ];

    /**
     * The lists a user may open: all of them for the owner, otherwise those
     * in shared projects and those shared on their own.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->when(! $user->isOwner(), fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->whereIn('project_id', array_keys($user->projectAccess()))
            ->orWhereIn('id', array_keys($user->listAccess()))));
    }

    protected static function booted(): void
    {
        static::created(function (TaskList $list) {
            foreach (self::DEFAULT_STATUSES as $position => [$name, $color, $isDone]) {
                $list->statuses()->create([
                    'name' => __($name),
                    'color' => $color,
                    'is_done' => $isDone,
                    'position' => $position,
                ]);
            }
        });
    }

    /**
     * Everyone who can open the list: the owner and the people it or one of
     * its projects is shared with. Tasks can be assigned to them.
     *
     * @return Collection<int, User>
     */
    public function people(): Collection
    {
        $projectIds = $this->project->ancestors()->push($this->project)->pluck('id');

        return User::where('role', UserRole::Owner)
            ->orWhereHas('memberships', fn (Builder $memberships) => $memberships
                ->where('task_list_id', $this->id)
                ->orWhereIn('project_id', $projectIds))
            ->orderBy('name')
            ->get(['id', 'name', 'avatar_path', 'role']);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Status, $this>
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class)->orderBy('position');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }
}
