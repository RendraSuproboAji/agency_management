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
            'version' => null,
            'file_path' => null,
            'external_url' => 'https://gallery.example.com/p/'.fake()->unique()->slug(2),
            'status' => 'draft',
            'review_note' => null,
            'submitted_at' => null,
            'approved_at' => null,
        ];
    }

    /**
     * Versinya unik per project, jadi beberapa deliverable dalam satu project
     * tidak boleh sama-sama v1. Versi yang ditentukan pemanggil dibiarkan.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Deliverable $deliverable) {
            $deliverable->version ??= (int) Deliverable::withTrashed()
                ->where('project_id', $deliverable->project_id)
                ->max('version') + 1;
        });
    }
}
