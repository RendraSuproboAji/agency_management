<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sebelum ini tagihan yang lewat jatuh tempo menghilang dari perhatian:
 * pengingatnya mencocokkan tanggal persis, jadi klien ditagih tepat sekali
 * pada hari-H lalu senyap selamanya.
 */
class InvoiceOverdueTest extends TestCase
{
    use RefreshDatabase;

    private function overdueInvoice(int $daysAgo, array $attributes = []): Invoice
    {
        // Email unik per pemanggilan: kolomnya memang unik sejak portal
        // memakainya sebagai identitas masuk.
        $client = Client::factory()->create();
        // Deadline dijauhkan supaya pengingat deadline project tidak ikut
        // terpicu dan mengaburkan hitungan surat di tes ini.
        $project = Project::factory()->create([
            'client_id' => $client->id,
            'deadline' => now()->addYear()->toDateString(),
        ]);

        // $attributes di kiri: operator + mempertahankan kunci operand kiri.
        return Invoice::factory()->create($attributes + [
            'project_id' => $project->id,
            'status' => 'sent',
            'amount' => 5_000_000,
            'issued_at' => now()->subDays($daysAgo + 14),
            'due_at' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    public function test_an_invoice_past_its_due_date_is_chased_the_next_day(): void
    {
        Mail::fake();
        $this->overdueInvoice(1);

        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    /**
     * Menjalankan perintahnya setiap hari selama sebulan, seperti scheduler
     * sungguhan: klien ditagih pada hari-H lalu tiga kali susulan, dan setelah
     * itu berhenti — bukan sekali lalu senyap seperti sebelumnya.
     */
    public function test_a_month_of_daily_runs_chases_exactly_four_times(): void
    {
        Mail::fake();
        $invoice = $this->overdueInvoice(0);
        $dueDate = $invoice->due_at->copy();

        for ($day = 0; $day <= 30; $day++) {
            $this->travelTo($dueDate->copy()->addDays($day));
            $this->artisan('reminders:send')->assertSuccessful();
        }

        $this->travelBack();

        // Hari-H, lalu H+1, H+7, H+14.
        Mail::assertSentCount(4);
    }

    /**
     * Ambang, bukan tanggal persis: tagihan yang sudah lewat beberapa hari
     * saat pertama kali diproses tetap ditagih, tidak dilewatkan diam-diam.
     */
    public function test_an_invoice_already_days_overdue_is_still_chased(): void
    {
        Mail::fake();
        $this->overdueInvoice(3);

        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_running_twice_does_not_chase_twice(): void
    {
        Mail::fake();
        $this->overdueInvoice(7);

        $this->artisan('reminders:send')->assertSuccessful();
        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_a_settled_or_void_invoice_is_never_chased(): void
    {
        Mail::fake();

        $paid = $this->overdueInvoice(7, ['status' => 'sent']);
        $paid->payments()->create(['amount' => 5_000_000, 'paid_at' => now()->subDay(), 'method' => 'transfer']);
        $paid->refresh()->recalculateStatus();

        $this->overdueInvoice(7, ['status' => 'void']);

        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_dashboard_separates_overdue_from_upcoming(): void
    {
        $admin = User::factory()->admin()->create();
        $this->overdueInvoice(5);
        $this->overdueInvoice(-10); // jatuh tempo sepuluh hari lagi

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('overdueInvoices', 1)
                ->where('overdueCount', 1));
    }
}
