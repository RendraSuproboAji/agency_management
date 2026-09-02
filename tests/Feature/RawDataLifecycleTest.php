<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User;
use App\Support\RawData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * raw_size_gb dan backup_location tercatat tiap sesi tetapi tidak pernah
 * ditotal, jadi tidak ada yang tahu berapa terabyte yang sedang ditahan atau
 * sesi mana yang sudah boleh dibersihkan.
 */
class RawDataLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function capture(Project $project, array $attributes = []): CaptureSession
    {
        return CaptureSession::factory()->create($attributes + [
            'project_id' => $project->id,
            'status' => 'done',
            'raw_size_gb' => 100,
            'backup_location' => 'NAS rak 2',
            'raw_purged_at' => null,
        ]);
    }

    private function deliveredProject(array $attributes = []): Project
    {
        $project = Project::factory()->create($attributes);

        Deliverable::factory()->create([
            'project_id' => $project->id,
            'status' => 'approved',
            'approved_at' => now()->subDays(200),
        ]);

        return $project;
    }

    public function test_a_session_without_a_backup_is_flagged_first(): void
    {
        $session = $this->capture($this->deliveredProject(), ['backup_location' => null]);

        // Sudah lewat masa retensi, tetapi tanpa salinan ia tidak pernah
        // dinyatakan siap dibersihkan — itu risiko kehilangan data.
        $this->assertSame('tanpa_backup', RawData::status($session));
    }

    public function test_raw_is_ready_once_the_work_is_approved_and_retention_passed(): void
    {
        $this->assertSame('siap_dibersihkan', RawData::status($this->capture($this->deliveredProject())));
    }

    public function test_an_unapproved_deliverable_holds_the_raw_data(): void
    {
        $project = Project::factory()->create();
        Deliverable::factory()->create([
            'project_id' => $project->id,
            'status' => 'approved',
            'approved_at' => now()->subDays(200),
        ]);
        Deliverable::factory()->create([
            'project_id' => $project->id,
            'version' => 2,
            'status' => 'revision',
            'approved_at' => null,
        ]);

        $this->assertSame('ditahan', RawData::status($this->capture($project)));
    }

    public function test_a_project_without_deliverables_holds_its_raw_data(): void
    {
        $this->assertSame('ditahan', RawData::status($this->capture(Project::factory()->create())));
    }

    public function test_the_client_retention_beats_the_default(): void
    {
        config(['site.raw_retention_days' => 90]);

        $client = Client::factory()->create(['raw_retention_days' => 3650]);
        $project = $this->deliveredProject(['client_id' => $client->id]);

        $this->assertSame('ditahan', RawData::status($this->capture($project)));
    }

    public function test_a_purged_session_says_so_and_leaves_the_total(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->deliveredProject();

        $kept = $this->capture($project);
        $purged = $this->capture($project, ['raw_purged_at' => now()->subDay()]);

        $this->assertSame('sudah_dibersihkan', RawData::status($purged));

        $this->actingAs($admin)->get(route('storage.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Storage/Index')
                // Dua sesi 100 GB, satu sudah dibersihkan: totalnya tinggal satu.
                ->where('totalGb', fn ($total) => (float) $total === 100.0)
                ->has('ready', 1));
    }

    public function test_marking_a_session_purged_is_recorded(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->deliveredProject(['owner_id' => $admin->id]);
        $session = $this->capture($project);

        $this->actingAs($admin)
            ->put(route('storage.purge', $session))
            ->assertRedirect();

        $this->assertNotNull($session->fresh()->raw_purged_at);
        $this->assertDatabaseHas('activities', ['action' => 'raw.purged']);
    }

    public function test_a_session_that_is_still_held_cannot_be_marked_purged(): void
    {
        $admin = User::factory()->admin()->create();
        $session = $this->capture(Project::factory()->create());

        $this->actingAs($admin)
            ->put(route('storage.purge', $session))
            ->assertSessionHasErrors('session');

        $this->assertNull($session->fresh()->raw_purged_at);
    }
}
