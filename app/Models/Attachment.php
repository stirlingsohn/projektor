<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded file in a project, optionally attached to one of its tasks.
 * Files are stored privately and only served to people who may see them.
 */
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    public const DISK = 'local';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'task_id',
        'user_id',
        'path',
        'name',
        'size',
        'mime_type',
    ];

    protected static function booted(): void
    {
        static::deleted(fn (Attachment $attachment) => Storage::disk(self::DISK)->delete($attachment->path));
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toView(): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'name' => $this->name,
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'task_title' => $this->relationLoaded('task') ? $this->task?->title : null,
            'url' => route('attachments.show', $this),
            'download_url' => route('attachments.download', $this),
            'created_at' => $this->created_at->toJSON(),
        ];
    }
}
