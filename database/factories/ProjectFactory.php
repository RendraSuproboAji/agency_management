<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Tur 3D '.fake()->unique()->words(2, true);

        return [
            'client_id' => Client::factory(),
            'owner_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'brief' => fake()->paragraph(),
            'service_type' => fake()->randomElement(Project::SERVICE_TYPES),
            'status' => 'lead',
            'budget' => fake()->numberBetween(5, 80) * 1_000_000,
            'deadline' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'site_location' => fake()->city(),
            'area_sqm' => fake()->numberBetween(50, 2000),
            'gallery_url' => null,
        ];
    }
}
