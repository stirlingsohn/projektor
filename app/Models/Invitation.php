<?php

namespace App\Models;

use App\Enums\AccessLevel;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invitation by email to a project or list, for someone without an
 * account yet. Accepting it creates the account and the membership.
 */
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    public const VALID_DAYS = 14;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'project_id',
        'task_list_id',
        'level',
        'token_hash',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => AccessLevel::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /** Give the invitation a new token, valid from now on, and return it. */
    public function renewToken(): string
    {
        $token = Str::random(48);
        $this->fill([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ])->save();

        return $token;
    }

    public static function findPending(string $token): ?self
    {
        return self::query()->pending()->firstWhere('token_hash', hash('sha256', $token));
    }

    /** The project or list the invitation is for. */
    public function target(): Project|TaskList
    {
        return $this->project ?? $this->taskList;
    }
}
