<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in someone's undo history of a list: what the changed tasks
 * looked like before and after.
 */
class TaskChange extends Model
{
    use MassPrunable;

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(Task::KEEP_DELETED_DAYS));
    }

    protected $fillable = ['user_id', 'task_list_id', 'label', 'tasks', 'undone_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tasks' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TaskList, $this>
     */
    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }
}
