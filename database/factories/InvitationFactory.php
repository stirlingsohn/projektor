<?php

namespace Database\Factories;

use App\Enums\AccessLevel;
use App\Models\Invitation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'project_id' => Project::factory(),
            'level' => AccessLevel::Comment,
            'token_hash' => hash('sha256', Str::random(48)),
            'expires_at' => now()->addDays(14),
        ];
    }
}
