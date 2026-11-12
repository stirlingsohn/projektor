<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->subHours(2);

        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addHour(),
            'seconds' => 3600,
        ];
    }

    public function running(): static
    {
        return $this->state(['ended_at' => null, 'seconds' => null]);
    }
}
