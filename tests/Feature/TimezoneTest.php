<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Waktu dibaca dan ditampilkan menurut APP_TIMEZONE. Dengan UTC, jadwal pagi di
 * Indonesia jatuh "kemarin" menurut aplikasi dan hilang dari agenda.
 */
class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_configured_timezone_is_the_one_the_app_uses(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');

        $this->assertSame('Asia/Jakarta', Carbon::now()->timezoneName);
    }

    public function test_a_session_early_this_morning_still_shows_on_the_dashboard(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');

        // Pukul 09:00 WIB hari ini — pagi hari kerja, yang dalam UTC masih 02:00
        // dan mudah tersaring keluar kalau zona waktunya salah.
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00', 'Asia/Jakarta'));

        $project = Project::factory()->create(['status' => 'capture']);
        CaptureSession::factory()->create([
            'project_id' => $project->id,
            'scheduled_at' => Carbon::parse('2026-09-10 08:00:00', 'Asia/Jakarta'),
            'status' => 'scheduled',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('upcomingSessions', 1));

        Carbon::setTestNow();
    }
}
