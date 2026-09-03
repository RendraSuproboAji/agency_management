<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * RawData::status() dipanggil sekali per sesi, dan sebelum perbaikan ia
 * memanggil $project->deliverables()->get() — kueri baru tiap kali, sehingga
 * eager loading pemanggilnya tidak pernah terpakai.
 */
class RawDataQueryTest extends TestCase
{
    use RefreshDatabase;

    private function makeSessions(int $sessions): Project
    {
        CaptureSession::query()->forceDelete();
        Project::query()->forceDelete();

        $project = Project::factory()->create();
        Deliverable::factory()->create([
            'project_id' => $project->id,
            'status' => 'approved',
            'approved_at' => now()->subDays(200),
        ]);

        CaptureSession::factory()->count($sessions)->create([
            'project_id' => $project->id,
            'status' => 'done',
            'raw_size_gb' => 100,
            'backup_location' => 'NAS rak 2',
        ]);

        return $project;
    }

    private function queriesFor(string $route, int $sessions): int
    {
        $this->makeSessions($sessions);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->actingAs(User::factory()->admin()->create())->get(route($route))->assertOk();

        return $queries;
    }

    public function test_the_storage_page_does_not_query_per_session(): void
    {
        $few = $this->queriesFor('storage.index', 3);
        $many = $this->queriesFor('storage.index', 30);

        $this->assertSame(
            $few,
            $many,
            "Jumlah kueri tumbuh mengikuti jumlah sesi ({$few} → {$many}): halaman penyimpanan N+1.",
        );
    }

    public function test_the_full_list_is_paginated(): void
    {
        $this->makeSessions(35);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('storage.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Tabel sesi capture yang paling cepat tumbuh; memuat
                // seluruhnya ke satu layar hanya soal waktu sebelum berat.
                ->has('sessions.data', 30)
                ->has('sessions.links')
                ->where('sessions.total', 35));
    }

    public function test_the_dashboard_does_not_query_per_session(): void
    {
        $few = $this->queriesFor('dashboard', 3);
        $many = $this->queriesFor('dashboard', 30);

        $this->assertSame(
            $few,
            $many,
            "Jumlah kueri tumbuh mengikuti jumlah sesi ({$few} → {$many}): dashboard N+1.",
        );
    }
}
