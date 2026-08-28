<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_with_portal_access_can_log_in(): void
    {
        $client = Client::factory()->withPortal()->create(['email' => 'klien@example.com']);

        $this->post(route('portal.login'), [
            'email' => 'klien@example.com',
            'password' => 'portal-password',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($client, 'client');
        $this->assertNotNull($client->fresh()->last_login_at);
    }

    public function test_a_client_without_portal_access_is_refused(): void
    {
        Client::factory()->create(['email' => 'klien@example.com', 'password' => 'portal-password']);

        $this->post(route('portal.login'), [
            'email' => 'klien@example.com',
            'password' => 'portal-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('client');
    }

    public function test_a_client_without_a_password_is_refused(): void
    {
        Client::factory()->create(['email' => 'klien@example.com', 'portal_enabled' => true]);

        $this->post(route('portal.login'), [
            'email' => 'klien@example.com',
            'password' => 'apa-saja',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('client');
    }

    public function test_portal_guests_are_sent_to_the_portal_login_not_the_staff_login(): void
    {
        $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
    }

    public function test_a_client_cannot_reach_the_internal_app(): void
    {
        $client = Client::factory()->withPortal()->create();

        $this->actingAs($client, 'client')->get(route('projects.index'))->assertRedirect(route('login'));
        $this->actingAs($client, 'client')->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_a_staff_user_cannot_reach_the_portal(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('portal.dashboard'))
            ->assertRedirect(route('portal.login'));
    }
}
