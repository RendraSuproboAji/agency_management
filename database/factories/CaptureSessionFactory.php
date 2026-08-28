<?php

namespace Database\Factories;

use App\Models\CaptureSession;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaptureSession>
 */
class CaptureSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'crew_id' => null,
            'scheduled_at' => fake()->dateTimeBetween('now', '+1 month'),
            'completed_at' => null,
            'location' => fake()->address(),
            'equipment' => 'Sony A7IV, tripod, lensa 16-35mm',
            'shot_count' => null,
            'weather_note' => null,
            'status' => 'scheduled',
            'notes' => null,
        ];
    }
}
