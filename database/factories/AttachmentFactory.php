<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'path' => 'attachments/'.fake()->uuid().'.txt',
            'name' => 'notes.txt',
            'size' => 12,
            'mime_type' => 'text/plain',
        ];
    }
}
