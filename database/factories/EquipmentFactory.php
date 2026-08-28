<?php

namespace Database\Factories;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Sony A7IV', 'DJI Mavic 3', 'Leica BLK360', 'Manfrotto 055']),
            'code' => 'EQ-'.fake()->unique()->numerify('###'),
            'category' => fake()->randomElement(Equipment::CATEGORIES),
            'serial_number' => fake()->bothify('SN-####-????'),
            'status' => 'available',
            'notes' => null,
        ];
    }
}
