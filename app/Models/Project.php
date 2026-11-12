<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A project is a folder that can contain subprojects and, later, lists,
 * documents and files.
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'color',
        'client_can_see_time',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'client_can_see_time' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'parent_id');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Project::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    /**
     * @return HasMany<TaskList, $this>
     */
    public function taskLists(): HasMany
    {
        return $this->hasMany(TaskList::class)->orderBy('position');
    }

    /**
     * The projects a user may see. Clients get access through invitations
     * in a later milestone; until then they see nothing.
     *
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->when(! $user->isOwner(), fn (Builder $query) => $query->whereIn('id', self::visibleIdsFor($user)));
    }

    /**
     * Projects a client sees: those shared with them, with all subprojects,
     * and those holding a list that was shared with them on its own.
     *
     * @return list<int>
     */
    public static function visibleIdsFor(User $user): array
    {
        return collect(array_keys($user->projectAccess()))
            ->merge(TaskList::whereKey(array_keys($user->listAccess()))->pluck('project_id'))
            ->unique()->values()->all();
    }

    /**
     * The parent chain from the top-level project down to the direct parent.
     *
     * @return Collection<int, Project>
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();

        for ($project = $this->parent; $project; $project = $project->parent) {
            $ancestors->prepend($project);
        }

        return $ancestors;
    }

    /**
     * The IDs of all subprojects at any depth.
     *
     * @return Collection<int, int>
     */
    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderBy('position');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest();
    }

    /** Whether the user may see the time tracked in this project. */
    public function showsTimeTo(User $user): bool
    {
        return $user->isOwner() || $this->client_can_see_time;
    }

    /**
     * Seconds tracked on finished entries in this project and its subprojects.
     */
    public function trackedSeconds(): int
    {
        return (int) TimeEntry::finished()->inProjects($this->descendantIds()->push($this->id))->sum('seconds');
    }

    public function descendantIds(): Collection
    {
        $ids = collect();
        $parentIds = [$this->id];

        while ($parentIds = Project::whereIn('parent_id', $parentIds)->pluck('id')->all()) {
            $ids->push(...$parentIds);
        }

        return $ids;
    }
}
