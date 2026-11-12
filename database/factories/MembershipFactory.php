<?php

namespace Database\Factories;

use App\Enums\AccessLevel;
use App\Models\Membership;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->client(),
            'project_id' => Project::factory(),
            'level' => AccessLevel::View,
        ];
    }
}
