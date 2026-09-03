<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kredensial portal adalah kunci ke seluruh data satu klien. Menyalakannya
 * harus jadi wewenang admin — kalau tidak, staf mana pun bisa membuatkan
 * dirinya sendiri akun portal milik klien lain.
 */
class ClientPortalGateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(Client $client, array $extra = []): array
    {
        // $extra harus di kiri: operator + mempertahankan kunci operand kiri.
        return $extra + [
            'name' => $client->name,
            'status' => $client->status,
        ];
    }

    public function test_a_staff_member_cannot_enable_the_portal_or_set_its_password(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $client = Client::factory()->create(['portal_enabled' => false, 'password' => null]);

        $this->actingAs($staff)
            ->put(route('clients.update', $client), $this->payload($client, [
                'portal_enabled' => true,
                'password' => 'kunci-rahasia-123',
            ]))
            ->assertForbidden();

        $client->refresh();
        $this->assertFalse($client->portal_enabled);
        $this->assertNull($client->password);
    }

    public function test_a_staff_member_may_still_edit_ordinary_client_details(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $client = Client::factory()->create(['portal_enabled' => false]);

        $this->actingAs($staff)
            ->put(route('clients.update', $client), $this->payload($client, ['name' => 'Nama Baru']))
            ->assertRedirect();

        $this->assertSame('Nama Baru', $client->fresh()->name);
    }

    public function test_an_admin_can_enable_the_portal(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create(['portal_enabled' => false]);

        $this->actingAs($admin)
            ->put(route('clients.update', $client), $this->payload($client, [
                'portal_enabled' => true,
                'password' => 'kunci-rahasia-123',
            ]))
            ->assertRedirect();

        $client->refresh();
        $this->assertTrue($client->portal_enabled);
        $this->assertTrue(Hash::check('kunci-rahasia-123', $client->password));
    }

    public function test_two_clients_cannot_share_a_portal_email(): void
    {
        $admin = User::factory()->admin()->create();
        Client::factory()->create(['email' => 'kontak@contoh.test']);
        $other = Client::factory()->create(['email' => 'lain@contoh.test']);

        $this->actingAs($admin)
            ->put(route('clients.update', $other), $this->payload($other, ['email' => 'kontak@contoh.test']))
            ->assertSessionHasErrors('email');
    }
}
