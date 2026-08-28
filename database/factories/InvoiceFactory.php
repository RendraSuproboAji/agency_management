<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'quotation_id' => null,
            'number' => 'INV/'.date('Y').'/'.fake()->unique()->numerify('####'),
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
            'amount' => 10_000_000,
            'status' => 'sent',
            'notes' => null,
        ];
    }
}
