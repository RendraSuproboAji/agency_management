<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'number' => 'QUO/'.date('Y').'/'.fake()->unique()->numerify('####'),
            'issued_at' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'tax_percent' => 11,
            'status' => 'draft',
            'notes' => null,
        ];
    }
}
