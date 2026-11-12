<?php

namespace App\Models;

use App\Enums\AccessLevel;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's access to a project, which includes its subprojects and lists,
 * or to a single list.
 */
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'project_id',
        'task_list_id',
        'level',
    ];

    protected function casts(): array
    {
        return [
            'level' => AccessLevel::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<TaskList, $this>
     */
    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }
}
