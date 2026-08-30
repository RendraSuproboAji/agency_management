<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectScene;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectScene>
 */
class ProjectSceneFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(['Lantai 1', 'Lantai 2', 'Lobi', 'Showroom', 'Rooftop']).' '.fake()->unique()->numerify('##');

        return [
            'project_id' => Project::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'position' => 0,
            'gallery_url' => null,
            'notes' => null,
        ];
    }
}
