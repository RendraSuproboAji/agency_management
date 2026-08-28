<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08##########'),
            'service_type' => fake()->randomElement(Project::SERVICE_TYPES),
            'site_location' => fake()->city(),
            'area_sqm' => fake()->numberBetween(50, 1200),
            'message' => fake()->paragraph(),
            'status' => 'new',
            'converted_project_id' => null,
        ];
    }
}
