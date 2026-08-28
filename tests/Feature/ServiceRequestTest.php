<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_submit_a_request(): void
    {
        $this->post(route('public.request.store'), [
            'name' => 'Dwi Prasetyo',
            'company' => 'Studio Interior Nusantara',
            'email' => 'dwi@example.com',
            'service_type' => 'gaussian_splatting',
            'site_location' => 'Kemang, Jakarta Selatan',
            'area_sqm' => 320,
            'message' => 'Showroom dua lantai, butuh virtual tour.',
        ])->assertRedirect(route('public.request.create'));

        $this->assertDatabaseHas('service_requests', [
            'email' => 'dwi@example.com',
            'status' => 'new',
        ]);
    }

    public function test_a_filled_honeypot_is_rejected(): void
    {
        $this->post(route('public.request.store'), [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'service_type' => 'gaussian_splatting',
            'website' => 'https://spam.example.com',
        ])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_guests_cannot_read_the_request_inbox(): void
    {
        $this->get(route('requests.index'))->assertRedirect(route('login'));
    }

    public function test_converting_a_request_creates_a_client_and_a_project(): void
    {
        $user = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create([
            'company' => 'Museum Kota Lama',
            'message' => 'Digitalisasi galeri utama.',
            'service_type' => 'photogrammetry',
            'area_sqm' => 640,
        ]);

        $this->actingAs($user)
            ->post(route('requests.convert', $serviceRequest), ['title' => 'Digitalisasi Galeri Utama'])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', ['name' => 'Museum Kota Lama', 'status' => 'lead']);
        $this->assertDatabaseHas('projects', [
            'title' => 'Digitalisasi Galeri Utama',
            'status' => 'lead',
            'service_type' => 'photogrammetry',
            'brief' => 'Digitalisasi galeri utama.',
            'area_sqm' => 640,
            'owner_id' => $user->id,
        ]);

        $serviceRequest->refresh();
        $this->assertSame('converted', $serviceRequest->status);
        $this->assertNotNull($serviceRequest->converted_project_id);
    }

    public function test_converting_can_attach_to_an_existing_client(): void
    {
        $client = Client::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('requests.convert', $serviceRequest), [
                'title' => 'Project Lanjutan',
                'client_id' => $client->id,
            ])->assertRedirect();

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('projects', ['title' => 'Project Lanjutan', 'client_id' => $client->id]);
    }

    public function test_a_request_cannot_be_converted_twice(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('requests.convert', $serviceRequest), ['title' => 'Pertama']);
        $this->actingAs($user)
            ->from(route('requests.show', $serviceRequest))
            ->post(route('requests.convert', $serviceRequest), ['title' => 'Kedua'])
            ->assertSessionHasErrors('request');

        $this->assertDatabaseCount('projects', 1);
    }

    public function test_the_status_cannot_be_set_to_converted_by_hand(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('requests.status', $serviceRequest), ['status' => 'converted'])
            ->assertStatus(422);

        $this->assertSame('new', $serviceRequest->fresh()->status);
    }

    public function test_only_an_admin_can_delete_a_request(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('requests.destroy', $serviceRequest))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('requests.destroy', $serviceRequest))
            ->assertRedirect(route('requests.index'));

        $this->assertDatabaseCount('service_requests', 0);
    }
}
