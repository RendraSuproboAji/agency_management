<?php

namespace Tests\Feature;

use App\Models\CaptureSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SessionCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_calendar_only_returns_sessions_of_the_requested_month(): void
    {
        $user = User::factory()->create();

        $inside = CaptureSession::factory()->create(['scheduled_at' => '2026-05-14 09:00']);
        CaptureSession::factory()->create(['scheduled_at' => '2026-06-01 09:00']);
        CaptureSession::factory()->create(['scheduled_at' => '2026-04-30 23:00']);

        $this->actingAs($user)->get('/sessions?view=calendar&month=2026-05')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Sessions/Index')
                ->where('mode', 'calendar')
                ->where('calendar.month', '2026-05')
                ->where('calendar.days', 31)
                // 1 Mei 2026 jatuh pada Jumat: empat kolom kosong sebelum Senin.
                ->where('calendar.leading', 4)
                ->has('calendar.sessions', 1)
                ->where('calendar.sessions.0.id', $inside->id)
                ->where('calendar.sessions.0.date', '2026-05-14')
            );
    }

    public function test_an_unparsable_month_falls_back_to_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/sessions?view=calendar&month=besok')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('calendar.month', now()->format('Y-m'))
            );
    }

    public function test_the_table_view_stays_the_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/sessions')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('mode', 'table')->has('sessions.data'));
    }

    /**
     * Carbon::createFromFormat('Y-m', ...) mempertahankan tanggal hari ini,
     * jadi pada tanggal 31 bulan yang hanya punya 30 hari meluber ke bulan
     * berikutnya — dan Februari meleset sampai tiga hari. Tes lama lolos
     * hanya karena kebetulan memakai Mei, yang punya 31 hari.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function shortMonths(): array
    {
        return [
            'September dilihat tanggal 31' => ['2026-08-31', '2026-09', 'September 2026'],
            'Februari dilihat tanggal 31' => ['2026-01-31', '2026-02', 'Februari 2026'],
            'April dilihat tanggal 31' => ['2026-03-31', '2026-04', 'April 2026'],
            'Februari dilihat tanggal 30' => ['2026-01-30', '2026-02', 'Februari 2026'],
        ];
    }

    #[DataProvider('shortMonths')]
    public function test_a_short_month_is_shown_whatever_day_it_is_viewed_on(string $today, string $month, string $label): void
    {
        $this->travelTo($today);
        $user = User::factory()->create();

        $this->actingAs($user)->get("/sessions?view=calendar&month={$month}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('calendar.month', $month)
                ->where('calendar.label', $label));

        $this->travelBack();
    }
}
