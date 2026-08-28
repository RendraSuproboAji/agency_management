<?php

namespace Database\Factories;

use App\Models\Deliverable;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deliverable>
 */
class DeliverableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => 'Scene '.fake()->word(),
            'type' => 'splat',
            'version' => 1,
            'file_path' => null,
            'external_url' => 'https://gallery.example.com/p/'.fake()->unique()->slug(2),
            'status' => 'draft',
            'review_note' => null,
            'submitted_at' => null,
            'approved_at' => null,
        ];
    }
}
