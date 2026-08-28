<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08##########'),
            'industry' => fake()->randomElement(['Properti', 'Interior', 'Museum', 'Retail', 'Konstruksi']),
            'address' => fake()->address(),
            'notes' => null,
            'status' => 'active',
            'password' => null,
            'portal_enabled' => false,
        ];
    }

    /** Klien yang sudah bisa masuk portal. */
    public function withPortal(string $password = 'portal-password'): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => $password,
            'portal_enabled' => true,
        ]);
    }
}
