<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Tidak ada satu pun ekspor di aplikasi ini, dan tidak ada laporan per
 * periode: angka keuangan hanya bisa dibaca satu layar per satu.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(string $issuedAt, float $amount, array $attributes = []): Invoice
    {
        return Invoice::factory()->create($attributes + [
            'project_id' => Project::factory()->create()->id,
            'issued_at' => $issuedAt,
            'amount' => $amount,
            'status' => 'sent',
        ]);
    }

    public function test_the_monthly_figures_match_the_rows(): void
    {
        $admin = User::factory()->admin()->create();

        $a = $this->invoice('2026-07-10', 10_000_000);
        $this->invoice('2026-07-20', 5_000_000);
        $this->invoice('2026-08-05', 7_000_000);

        Payment::create(['invoice_id' => $a->id, 'paid_at' => '2026-07-15', 'amount' => 4_000_000, 'method' => 'transfer']);
        Payment::create(['invoice_id' => $a->id, 'paid_at' => '2026-08-02', 'amount' => 1_000_000, 'method' => 'transfer']);

        $this->actingAs($admin)
            ->get(route('reports.index', ['from' => '2026-07-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Index')
                ->where('months.0.month', '2026-07')
                ->where('months.0.invoiced', fn ($v) => (float) $v === 15_000_000.0)
                ->where('months.0.received', fn ($v) => (float) $v === 4_000_000.0)
                ->where('months.1.month', '2026-08')
                ->where('months.1.invoiced', fn ($v) => (float) $v === 7_000_000.0)
                ->where('months.1.received', fn ($v) => (float) $v === 1_000_000.0)
                ->where('totals.invoiced', fn ($v) => (float) $v === 22_000_000.0)
                ->where('totals.received', fn ($v) => (float) $v === 5_000_000.0));
    }

    public function test_rows_outside_the_range_are_left_out(): void
    {
        $admin = User::factory()->admin()->create();
        $this->invoice('2026-06-30', 9_000_000);
        $this->invoice('2026-07-01', 1_000_000);

        $this->actingAs($admin)
            ->get(route('reports.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totals.invoiced', fn ($v) => (float) $v === 1_000_000.0));
    }

    public function test_the_invoice_export_lists_the_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create(['name' => 'Studio Ambar']);
        $project = Project::factory()->create(['client_id' => $client->id, 'title' => 'Tur Showroom']);

        Invoice::factory()->create([
            'project_id' => $project->id,
            'number' => 'INV/2026/0007',
            'issued_at' => '2026-07-10',
            'amount' => 12_000_000,
            'status' => 'sent',
        ]);

        $csv = $this->actingAs($admin)
            ->get(route('reports.invoices', ['from' => '2026-07-01', 'to' => '2026-07-31']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('INV/2026/0007', $csv);
        $this->assertStringContainsString('Studio Ambar', $csv);
        $this->assertStringContainsString('Tur Showroom', $csv);
        $this->assertStringContainsString('12000000.00', $csv);
    }

    public function test_a_value_that_looks_like_a_formula_is_defused(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create(['name' => '=HYPERLINK("http://jahat.example","klik")']);
        $project = Project::factory()->create(['client_id' => $client->id]);

        Invoice::factory()->create([
            'project_id' => $project->id,
            'issued_at' => '2026-07-10',
            'amount' => 1_000_000,
            'status' => 'sent',
        ]);

        $csv = $this->actingAs($admin)
            ->get(route('reports.invoices', ['from' => '2026-07-01', 'to' => '2026-07-31']))
            ->streamedContent();

        // Nilai berawalan = dieksekusi sebagai rumus saat dibuka di Excel, dan
        // nama klien bisa berasal dari formulir permintaan yang diisi luar.
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_the_payment_export_lists_the_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $invoice = $this->invoice('2026-07-10', 8_000_000, ['number' => 'INV/2026/0008']);
        Payment::create([
            'invoice_id' => $invoice->id,
            'paid_at' => '2026-07-18',
            'amount' => 3_000_000,
            'method' => 'transfer',
            'reference' => 'TRF-991',
        ]);

        $csv = $this->actingAs($admin)
            ->get(route('reports.payments', ['from' => '2026-07-01', 'to' => '2026-07-31']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('INV/2026/0008', $csv);
        $this->assertStringContainsString('TRF-991', $csv);
        $this->assertStringContainsString('3000000.00', $csv);
    }

    public function test_the_report_does_not_query_per_invoice(): void
    {
        $admin = User::factory()->admin()->create();

        $count = function (int $rows) use ($admin): int {
            Invoice::query()->forceDelete();
            Invoice::factory()->count($rows)->create(['issued_at' => '2026-07-10', 'status' => 'sent']);

            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });

            $this->actingAs($admin)
                ->get(route('reports.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
                ->assertOk();

            return $queries;
        };

        $this->assertSame($count(3), $count(30));
    }
}
