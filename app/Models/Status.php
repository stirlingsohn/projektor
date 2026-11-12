<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A task status defined in a list's settings. Tasks in a status marked
 * `is_done` count as completed.
 */
class Status extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
        'is_done',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
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
