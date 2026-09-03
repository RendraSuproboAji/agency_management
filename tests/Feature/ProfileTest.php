<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_changes_their_own_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('kunci-lama-12345')]);

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'kunci-lama-12345',
            'password' => 'kunci-baru-12345',
            'password_confirmation' => 'kunci-baru-12345',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('kunci-baru-12345', $user->fresh()->password));
    }

    public function test_the_current_password_must_be_proven(): void
    {
        $user = User::factory()->create(['password' => Hash::make('kunci-lama-12345')]);

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'tebakan-salah-123',
            'password' => 'kunci-baru-12345',
            'password_confirmation' => 'kunci-baru-12345',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('kunci-lama-12345', $user->fresh()->password));
    }

    public function test_a_user_cannot_take_an_email_another_user_already_has(): void
    {
        User::factory()->create(['email' => 'sudah@studio.test']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => $user->name, 'email' => 'sudah@studio.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_a_guest_has_no_profile(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }
}
