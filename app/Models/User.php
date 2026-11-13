<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\HasAccess;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasAccess, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'digest_daily',
        'digest_weekly',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'avatar_path',
        'digest_daily_sent_on',
        'digest_weekly_sent_on',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['avatar'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'digest_daily' => 'boolean',
            'digest_weekly' => 'boolean',
            'digest_daily_sent_on' => 'date',
            'digest_weekly_sent_on' => 'date',
        ];
    }

    /**
     * Whether the user owns the workspace and may manage everything.
     */
    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * The profile picture URL, versioned so a new picture shows right away.
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn () => $this->avatar_path
            ? route('users.avatar', ['user' => $this->id, 'v' => substr(md5($this->avatar_path), 0, 8)])
            : null);
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }
}
