<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Tiap daftar punya penyaringnya sendiri, tetapi tidak ada satu kotak cari:
 * mencari "Kemang" berarti menebak lebih dulu itu klien, project, atau
 * penawaran, lalu membuka layar yang tepat.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_term_reaches_every_kind_of_record(): void
    {
        $admin = User::factory()->admin()->create();

        $client = Client::factory()->create(['name' => 'Galeri Kemang']);
        $project = Project::factory()->create(['client_id' => $client->id, 'title' => 'Tur Kemang Raya']);
        ServiceRequest::factory()->create(['company' => 'Kemang Living']);
        Quotation::factory()->create(['project_id' => $project->id, 'number' => 'QUO/2026/KEMANG']);
        Invoice::factory()->create(['project_id' => $project->id, 'number' => 'INV/2026/KEMANG']);

        $this->actingAs($admin)
            ->get(route('search.index', ['q' => 'kemang']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Search/Index')
                ->has('results.clients', 1)
                ->has('results.projects', 1)
                ->has('results.requests', 1)
                ->has('results.quotations', 1)
                ->has('results.invoices', 1)
                ->where('total', 5));
    }

    public function test_each_group_stops_at_five(): void
    {
        $admin = User::factory()->admin()->create();
        Client::factory()->count(8)->create(['name' => 'Studio Kemang']);

        $this->actingAs($admin)
            ->get(route('search.index', ['q' => 'kemang']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('results.clients', 5)
                // Jumlah sebenarnya tetap disebut supaya jelas ada yang tidak
                // ditampilkan, dan tautan "lihat semua" punya alasan.
                ->where('counts.clients', 8));
    }

    public function test_the_see_all_links_actually_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $project = Project::factory()->create();
        Invoice::factory()->create(['project_id' => $project->id, 'number' => 'INV/2026/KEMANG']);
        Invoice::factory()->create(['project_id' => $project->id, 'number' => 'INV/2026/0002']);

        // Tautan "lihat semua" pada halaman cari mengarah ke daftar aslinya;
        // kalau daftarnya mengabaikan q, tautannya berbohong.
        $this->actingAs($admin)
            ->get('/invoices?q=KEMANG')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('invoices.data', 1));
    }

    public function test_an_empty_term_loads_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        Client::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('search.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('total', 0)
                ->has('results.clients', 0));
    }

    public function test_the_portal_guard_still_applies(): void
    {
        $this->get(route('search.index', ['q' => 'kemang']))->assertRedirect(route('login'));
    }
}
