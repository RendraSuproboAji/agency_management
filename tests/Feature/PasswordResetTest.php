<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_staff_member_resets_their_own_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'staf@studio.test']);

        $this->post(route('password.email'), ['email' => 'staf@studio.test'])->assertRedirect();

        // Tautannya harus mengarah ke halaman setel ulang staf, bukan portal.
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            return str_contains($notification->toMail($user)->url, '/reset-password/');
        });

        $token = Password::broker('users')->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'staf@studio.test',
            'password' => 'kunci-baru-12345',
            'password_confirmation' => 'kunci-baru-12345',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('kunci-baru-12345', $user->fresh()->password));
    }

    public function test_a_client_resets_their_portal_password(): void
    {
        Notification::fake();
        $client = Client::factory()->withPortal()->create(['email' => 'klien@contoh.test']);

        $this->post(route('portal.password.email'), ['email' => 'klien@contoh.test'])->assertRedirect();

        Notification::assertSentTo($client, ResetPassword::class, function (ResetPassword $notification) use ($client) {
            return str_contains($notification->toMail($client)->url, '/portal/reset-password/');
        });

        $token = Password::broker('clients')->createToken($client);

        $this->post(route('portal.password.update'), [
            'token' => $token,
            'email' => 'klien@contoh.test',
            'password' => 'kunci-baru-12345',
            'password_confirmation' => 'kunci-baru-12345',
        ])->assertRedirect(route('portal.login'));

        $this->assertTrue(Hash::check('kunci-baru-12345', $client->fresh()->password));
    }

    /**
     * Kalau kedua broker berbagi tabel, token klien akan menimpa token staf
     * karena email adalah primary key di sana.
     */
    public function test_the_two_guards_keep_separate_tokens_for_the_same_email(): void
    {
        $user = User::factory()->create(['email' => 'sama@contoh.test']);
        $client = Client::factory()->withPortal()->create(['email' => 'sama@contoh.test']);

        $staffToken = Password::broker('users')->createToken($user);
        Password::broker('clients')->createToken($client);

        $this->assertSame(1, DB::table('password_reset_tokens')->count());
        $this->assertSame(1, DB::table('client_password_reset_tokens')->count());

        // Token staf masih berlaku walau klien meminta reset setelahnya.
        $this->post(route('password.update'), [
            'token' => $staffToken,
            'email' => 'sama@contoh.test',
            'password' => 'kunci-baru-12345',
            'password_confirmation' => 'kunci-baru-12345',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('kunci-baru-12345', $user->fresh()->password));
    }

    public function test_a_client_token_cannot_reset_a_staff_password(): void
    {
        $user = User::factory()->create(['email' => 'sama@contoh.test']);
        $client = Client::factory()->withPortal()->create(['email' => 'sama@contoh.test']);
        $clientToken = Password::broker('clients')->createToken($client);

        $this->post(route('password.update'), [
            'token' => $clientToken,
            'email' => 'sama@contoh.test',
            'password' => 'kunci-baru-12345',
            'password_confirmation' => 'kunci-baru-12345',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('kunci-baru-12345', $user->fresh()->password));
    }

    public function test_an_unknown_email_gets_the_same_answer_as_a_known_one(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'bukan-siapa-siapa@contoh.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'staf@studio.test']);
        $token = Password::broker('users')->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'staf@studio.test',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors('password');
    }
}
