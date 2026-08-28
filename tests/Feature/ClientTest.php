<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_staff_member_can_create_a_client(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('clients.store'), [
                'name' => 'Studio Interior Nusantara',
                'contact_name' => 'Dwi',
                'email' => 'dwi@example.com',
                'status' => 'active',
            ])->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'name' => 'Studio Interior Nusantara',
            'slug' => 'studio-interior-nusantara',
            'status' => 'active',
        ]);
    }

    public function test_creating_a_client_with_a_duplicate_name_gets_a_unique_slug(): void
    {
        Client::factory()->create(['name' => 'Museum Kota Lama', 'slug' => 'museum-kota-lama']);

        $this->actingAs(User::factory()->create())
            ->post(route('clients.store'), ['name' => 'Museum Kota Lama', 'status' => 'lead']);

        $this->assertDatabaseHas('clients', ['slug' => 'museum-kota-lama-2']);
    }

    public function test_the_client_list_can_be_searched(): void
    {
        Client::factory()->create(['name' => 'Museum Kota Lama', 'industry' => 'Kebudayaan']);
        Client::factory()->create(['name' => 'PT Properti Sejahtera', 'industry' => 'Properti']);

        $this->actingAs(User::factory()->create())
            ->get(route('clients.index', ['q' => 'Museum']))
            ->assertOk()
            ->assertSee('Museum Kota Lama')
            ->assertDontSee('PT Properti Sejahtera');
    }

    public function test_a_client_can_be_updated(): void
    {
        $client = Client::factory()->create(['status' => 'lead']);

        $this->actingAs(User::factory()->create())
            ->put(route('clients.update', $client), [
                'name' => $client->name,
                'status' => 'active',
            ])->assertRedirect();

        $this->assertSame('active', $client->fresh()->status);
    }

    public function test_only_an_admin_can_delete_a_client(): void
    {
        $client = Client::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }
}
