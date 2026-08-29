<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_add_a_user(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('users.store'), [
            'name' => 'Rina Kapture',
            'email' => 'rina@example.com',
            'role' => 'staff',
            'password' => 'kata-sandi-baru',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'rina@example.com')->firstOrFail();
        $this->assertSame('staff', $user->role);
        $this->assertTrue(Hash::check('kata-sandi-baru', $user->password));
    }

    public function test_updating_without_a_password_keeps_the_old_one(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['password' => 'kata-sandi-lama']);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'role' => 'staff',
        ])->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertTrue(Hash::check('kata-sandi-lama', $user->password));
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from(route('users.edit', $admin))->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'staff',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_an_admin_can_be_deleted_while_another_remains(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)->delete(route('users.destroy', $other))->assertRedirect(route('users.index'));

        $this->assertNull($other->fresh());
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    /**
     * Admin terakhir terlindungi lewat pemeriksaan "akun sendiri": untuk
     * menghapus admin dibutuhkan admin lain, dan begitu ada admin lain,
     * targetnya bukan yang terakhir. Jadi inilah jalur yang benar-benar
     * menjaga sistem tetap punya admin.
     */
    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from(route('users.index'))
            ->delete(route('users.destroy', $admin))
            ->assertSessionHasErrors('user');

        $this->assertNotNull($admin->fresh());
    }

    public function test_a_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
