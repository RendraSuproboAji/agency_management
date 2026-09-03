<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman gagal sebelumnya memakai bawaan Laravel: polos, berbahasa Inggris,
 * tanpa cangkang aplikasi, dan tanpa jalan kembali.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_page_explains_itself_in_indonesian(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Kembali ke halaman utama');
    }

    public function test_a_forbidden_page_explains_itself_in_indonesian(): void
    {
        $outsider = User::factory()->create(['role' => 'staff']);
        $project = Project::factory()->create(['owner_id' => User::factory()->create(['role' => 'staff'])]);

        $this->actingAs($outsider)
            ->get(route('projects.edit', $project))
            ->assertForbidden()
            ->assertSee('Akses ditolak');
    }

    /** Klien portal dikembalikan ke portalnya, bukan ke aplikasi staf. */
    public function test_the_way_back_matches_where_the_visitor_was(): void
    {
        $this->get('/tidak-ada')->assertSee(url('/').'"', false);
        $this->get('/portal/tidak-ada')->assertSee(url('/portal'), false);
    }

    /**
     * Statusnya sendiri sudah dipastikan terhadap server sungguhan; di sini
     * yang dijaga adalah halamannya, karena test helper selalu menyertakan
     * token CSRF yang sah sehingga 419 tidak bisa dipicu dari sini.
     */
    public function test_the_expired_session_page_tells_the_visitor_what_to_do(): void
    {
        $page = view('errors.419')->render();

        $this->assertStringContainsString('Sesi Anda berakhir', $page);
        $this->assertStringContainsString('Muat ulang', $page);
    }
}
